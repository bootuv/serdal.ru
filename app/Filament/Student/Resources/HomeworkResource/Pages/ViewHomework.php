<?php

namespace App\Filament\Student\Resources\HomeworkResource\Pages;

use App\Filament\Student\Resources\HomeworkResource;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ViewHomework extends ViewRecord
{
    protected static string $resource = HomeworkResource::class;

    public ?array $submissionData = [];

    public function getTitle(): string|Htmlable
    {
        return $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        $submission = $this->getSubmission();

        return [
            Actions\Action::make('submit')
                ->label('Сдать работу')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn() => !$submission || !$submission->submitted_at)
                ->form([
                    Forms\Components\RichEditor::make('content')
                        ->label('Ответ')
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            'orderedList',
                            'link',
                        ])
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('attachments')
                        ->label('Прикрепить файлы')
                        ->multiple()
                        ->disk('s3')
                        ->directory('homework-submissions')
                        ->visibility('public')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ])
                        ->maxSize(51200)
                        ->columnSpanFull(),
                ])
                ->fillForm(function () use ($submission) {
                    if ($submission) {
                        return [
                            'content' => $submission->content,
                            'attachments' => $submission->attachments,
                        ];
                    }
                    return [];
                })
                ->action(function (array $data) {
                    $submission = HomeworkSubmission::updateOrCreate(
                        [
                            'homework_id' => $this->record->id,
                            'student_id' => auth()->id(),
                        ],
                        [
                            'content' => $data['content'],
                            'attachments' => $data['attachments'],
                            'submitted_at' => now(),
                        ]
                    );

                    Notification::make()
                        ->title('Работа сдана')
                        ->body('Ваш ответ отправлен на проверку')
                        ->success()
                        ->send();

                    // Notify teacher
                    Notification::make()
                        ->title('Новая работа')
                        ->body(auth()->user()->name . ' сдал(а) работу: ' . $this->record->title)
                        ->icon('heroicon-o-clipboard-document-check')
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label('Проверить')
                                ->button()
                                ->url(route('filament.app.resources.homework.view', $this->record)),
                        ])
                        ->sendToDatabase($this->record->teacher)
                        ->broadcast($this->record->teacher);

                    $this->refreshFormData(['*']);
                })
                ->modalHeading('Сдать работу')
                ->modalSubmitActionLabel('Отправить'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $submission = $this->getSubmission();

        return $infolist
            ->schema([
                Infolists\Components\Section::make('Задание')
                    ->schema([
                        Infolists\Components\TextEntry::make('teacher.name')
                            ->label('Учитель'),

                        Infolists\Components\TextEntry::make('room.name')
                            ->label('Урок')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('deadline')
                            ->label('Срок сдачи')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Без ограничений')
                            ->color(fn(Homework $record) => $record->is_overdue ? 'danger' : null),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Описание')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Файлы задания')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('attachments')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('')
                                    ->getStateUsing(fn($state) => basename($state ?? ''))
                                    ->url(fn($state) => $state ? \Storage::url($state) : null)
                                    ->openUrlInNewTab(),
                            ])
                            ->columns(1),
                    ])
                    ->visible(fn(Homework $record) => !empty($record->attachments))
                    ->collapsible(),

                // Submission section
                Infolists\Components\Section::make('Моя работа')
                    ->schema([
                        Infolists\Components\TextEntry::make('submission_status')
                            ->label('Статус')
                            ->getStateUsing(function () use ($submission) {
                                if (!$submission || !$submission->submitted_at) {
                                    return 'Не сдано';
                                }
                                if ($submission->grade !== null) {
                                    return 'Оценено';
                                }
                                return 'На проверке';
                            })
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Не сдано' => 'gray',
                                'На проверке' => 'warning',
                                'Оценено' => 'success',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('submission_submitted_at')
                            ->label('Дата сдачи')
                            ->getStateUsing(fn() => $submission?->submitted_at?->format('d.m.Y H:i'))
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('submission_grade')
                            ->label('Оценка')
                            ->getStateUsing(fn() => $submission?->grade)
                            ->placeholder('—')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success')
                            ->visible(fn() => $submission?->grade !== null),

                        Infolists\Components\TextEntry::make('submission_content')
                            ->label('Мой ответ')
                            ->getStateUsing(fn() => $submission?->content)
                            ->html()
                            ->columnSpanFull()
                            ->visible(fn() => !empty($submission?->content)),

                        Infolists\Components\TextEntry::make('submission_feedback')
                            ->label('Комментарий учителя')
                            ->getStateUsing(fn() => $submission?->feedback)
                            ->html()
                            ->columnSpanFull()
                            ->visible(fn() => !empty($submission?->feedback)),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getSubmission(): ?HomeworkSubmission
    {
        return $this->record->submissions()
            ->where('student_id', auth()->id())
            ->first();
    }
}
