<?php

namespace App\Filament\App\Resources\HomeworkSubmissionResource\Pages;

use App\Filament\App\Resources\HomeworkSubmissionResource;
use App\Models\HomeworkSubmission;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class ViewHomeworkSubmission extends ViewRecord
{
    protected static string $resource = HomeworkSubmissionResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Работа: ' . $this->record->student->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('grade')
                ->label($this->record->grade !== null ? 'Изменить оценку' : 'Оценить')
                ->icon('heroicon-o-academic-cap')
                ->color('primary')
                ->form([
                    Forms\Components\TextInput::make('grade')
                        ->label($this->record->homework->grade_label)
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue($this->record->homework->effective_max_score)
                        ->suffix('/ ' . $this->record->homework->effective_max_score)
                        ->default($this->record->grade),

                    Forms\Components\RichEditor::make('feedback')
                        ->label('Комментарий')
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            'orderedList',
                        ])
                        ->default($this->record->feedback)
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('feedback_attachments')
                        ->label('Прикрепить файлы')
                        ->multiple()
                        ->maxSize(51200)
                        ->live()
                        ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set, $state) {
                            if (empty($state))
                                return;

                            $processedState = [];
                            $hasChanges = false;

                            foreach ($state as $file) {
                                if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                    $extension = strtolower($file->getClientOriginalExtension());
                                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp']);
                                    $isGif = $extension === 'gif';

                                    $newPath = 'homework-feedback/' . $file->getFilename();

                                    if ($isImage && !$isGif) {
                                        try {
                                            $imageContent = $file->get();
                                            $image = \Intervention\Image\Laravel\Facades\Image::read($imageContent);

                                            if ($image->width() > 1920 || $image->height() > 1080) {
                                                $image->scaleDown(1920, 1080);
                                            }

                                            $newPath = 'homework-feedback/' . pathinfo($file->getFilename(), PATHINFO_FILENAME) . '_processed.' . $extension;
                                            $encoded = $image->encodeByExtension($extension, quality: 85);

                                            \Illuminate\Support\Facades\Storage::disk('s3')->put($newPath, (string) $encoded, 'public');
                                        } catch (\Exception $e) {
                                            \Log::error("Failed to resize feedback image: " . $e->getMessage());
                                            $newPath = $file->store('homework-feedback', 's3');
                                        }
                                    } else {
                                        \Illuminate\Support\Facades\Storage::disk('s3')->putFileAs('homework-feedback', $file, basename($newPath), 'public');
                                    }

                                    $processedState[] = $newPath;
                                    $hasChanges = true;
                                } else {
                                    $processedState[] = $file;
                                }
                            }

                            if ($hasChanges) {
                                $set('feedback_attachments', $processedState);
                            }
                        })
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'grade' => $data['grade'],
                        'feedback' => $data['feedback'],
                        'feedback_attachments' => $data['feedback_attachments'],
                    ]);

                    Notification::make()
                        ->title('Оценка сохранена')
                        ->success()
                        ->send();

                    // Notify student
                    $this->record->student->notify(new \App\Notifications\HomeworkGraded($this->record->homework, $data['grade']));

                    $this->refreshFormData(['*']);
                })
                ->modalHeading('Оценка работы'),

            Actions\Action::make('back')
                ->label('К заданию')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn() => route('filament.app.resources.homework.view', $this->record->homework)),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Информация о задании')
                    ->schema([
                        Infolists\Components\TextEntry::make('homework.title')
                            ->label('Задание'),

                        Infolists\Components\TextEntry::make('student.name')
                            ->label('Ученик'),

                        Infolists\Components\TextEntry::make('submitted_at')
                            ->label('Дата сдачи')
                            ->dateTime('d.m.Y H:i'),

                        Infolists\Components\TextEntry::make('homework.deadline')
                            ->label('Дедлайн')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—')
                            ->color(fn() => $this->record->homework->deadline &&
                                $this->record->submitted_at &&
                                $this->record->submitted_at->gt($this->record->homework->deadline)
                                ? 'danger' : null),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Ответ ученика')
                    ->schema([
                        Infolists\Components\TextEntry::make('content')
                            ->label('')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn() => !empty($this->record->content)),

                Infolists\Components\Section::make('Файлы ученика')
                    ->schema([
                        Infolists\Components\ViewEntry::make('attachments')
                            ->hiddenLabel()
                            ->view('filament.infolists.entries.attachments-list')
                            ->viewData([
                                'attachments' => fn($state) => is_string($state) ? json_decode($state, true) : $state,
                            ]),
                    ])
                    ->visible(fn() => !empty($this->record->attachments))
                    ->collapsible(),

                Infolists\Components\Section::make('Оценка')
                    ->schema([
                        Infolists\Components\TextEntry::make('grade')
                            ->label('Оценка')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success')
                            ->placeholder('Не оценено'),

                        Infolists\Components\TextEntry::make('feedback')
                            ->label('Комментарий')
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('—'),

                        Infolists\Components\ViewEntry::make('feedback_attachments')
                            ->hiddenLabel()
                            ->view('filament.infolists.entries.attachments-list')
                            ->viewData([
                                'attachments' => fn($state) => is_string($state) ? json_decode($state, true) : $state,
                            ])
                            ->visible(fn() => !empty($this->record->feedback_attachments)),
                    ])
                    ->columns(1),
            ]);
    }
}
