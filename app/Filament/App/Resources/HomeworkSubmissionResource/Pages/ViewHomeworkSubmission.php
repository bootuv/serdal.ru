<?php

namespace App\Filament\App\Resources\HomeworkSubmissionResource\Pages;

use App\Filament\App\Resources\HomeworkSubmissionResource;
use App\Models\HomeworkSubmission;
use App\Notifications\HomeworkRevisionRequested;
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

    public array $annotatedImages = [];

    protected $listeners = ['imageAnnotated' => 'handleImageAnnotated'];

    public function handleImageAnnotated(string $path): void
    {
        $this->annotatedImages[] = $path;

        // Add to record's feedback_attachments
        $existing = $this->record->feedback_attachments ?? [];
        $existing[] = $path;
        $this->record->update(['feedback_attachments' => $existing]);

        \Filament\Notifications\Notification::make()
            ->title('Аннотация сохранена')
            ->success()
            ->send();
    }

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
                ->visible(fn() => $this->record->status !== HomeworkSubmission::STATUS_REVISION_REQUESTED)
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
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'grade' => $data['grade'],
                        'feedback' => $data['feedback'],
                        'status' => HomeworkSubmission::STATUS_GRADED,
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

            Actions\Action::make('request_revision')
                ->label('На доработку')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn() => $this->record->status === HomeworkSubmission::STATUS_SUBMITTED)
                ->form([
                    Forms\Components\RichEditor::make('feedback')
                        ->label('Комментарий (обязательно)')
                        ->required()
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            'orderedList',
                        ])
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('feedback_attachments')
                        ->label('Прикрепить файлы')
                        ->multiple()
                        ->maxSize(51200)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => HomeworkSubmission::STATUS_REVISION_REQUESTED,
                        'feedback' => $data['feedback'],
                        'feedback_attachments' => $data['feedback_attachments'] ?? null,
                    ]);

                    Notification::make()
                        ->title('Работа отправлена на доработку')
                        ->success()
                        ->send();

                    // Notify student
                    $this->record->student->notify(new HomeworkRevisionRequested($this->record->homework, $data['feedback']));

                    $this->refreshFormData(['*']);
                })
                ->modalHeading('На доработку')
                ->modalSubmitActionLabel('Отправить'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Информация о задании')
                    ->schema([
                        Infolists\Components\TextEntry::make('homework.title')
                            ->label('Задание')
                            ->url(fn() => route('filament.app.resources.homework.view', $this->record->homework))
                            ->color('primary'),

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
                            ->columnSpanFull()
                            ->visible(fn() => !empty($this->record->content)),

                        Infolists\Components\ViewEntry::make('attachments')
                            ->hiddenLabel()
                            ->view('filament.infolists.entries.file-cards')
                            ->viewData([
                                'annotatedFiles' => fn() => $this->record->annotated_files ?? [],
                                'showAnnotateButton' => true,
                                'submissionId' => fn() => $this->record->id,
                            ])
                            ->visible(fn() => !empty($this->record->attachments)),
                    ])
                    ->visible(fn() => !empty($this->record->content) || !empty($this->record->attachments)),

                Infolists\Components\Section::make('Ваша оценка')
                    ->schema([
                        Infolists\Components\TextEntry::make('grade')
                            ->label('Оценка')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success'),

                        Infolists\Components\TextEntry::make('feedback')
                            ->label('Комментарий')
                            ->html()
                            ->columnSpanFull()
                            ->placeholder('—')
                            ->visible(fn() => !empty($this->record->feedback)),
                    ])
                    ->columns(1)
                    ->visible(fn() => $this->record->grade !== null),

                // History section
                Infolists\Components\Section::make('История')
                    ->schema([
                        Infolists\Components\ViewEntry::make('activities')
                            ->hiddenLabel()
                            ->view('filament.infolists.entries.activity-timeline')
                            ->state(fn() => $this->record->activities()->with('user')->get()),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.pages.partials.image-annotator-footer');
    }
}
