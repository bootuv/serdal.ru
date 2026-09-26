<?php

namespace App\Filament\Student\Resources\HomeworkResource\Pages;

use App\Filament\Student\Resources\HomeworkResource;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Services\HomeworkSubmissionService;
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
                ->label(fn() => $submission?->status === HomeworkSubmission::STATUS_REVISION_REQUESTED ? 'Пересдать работу' : 'Сдать работу')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn() => HomeworkSubmissionService::canSubmit($submission))
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
                        ->directory(fn() => HomeworkSubmissionService::DIRECTORY . '/' . auth()->id())
                        ->visibility('public')
                        // Optimization: Do not check file existence/metadata on S3 during load
                        ->fetchFileInformation(false)
                        ->acceptedFileTypes(HomeworkSubmissionService::ACCEPTED_MIMES)
                        ->maxSize(HomeworkSubmissionService::MAX_FILE_KB)
                        ->afterStateUpdated(\App\Helpers\FileUploadHelper::filamentCallback('attachments', HomeworkSubmissionService::DIRECTORY, 1920, 1080, 85, true))
                        ->deleteUploadedFileUsing(\App\Helpers\FileUploadHelper::filamentDeleteCallback())
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
                    app(HomeworkSubmissionService::class)->submit(
                        $this->record,
                        auth()->user(),
                        $data['content'] ?? null,
                        (array) ($data['attachments'] ?? []),
                    );

                    Notification::make()
                        ->title('Работа сдана')
                        ->body('Ваш ответ отправлен на проверку')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
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
                            ->formatStateUsing(fn($state) => format_datetime($state))
                            ->placeholder('Без ограничений')
                            ->color(fn(Homework $record) => $record->is_overdue ? 'danger' : null),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Описание')
                            ->html()
                            ->columnSpanFull(),

                        Infolists\Components\Fieldset::make('Файлы задания')
                            ->schema([
                                Infolists\Components\ViewEntry::make('attachments')
                                    ->hiddenLabel()
                                    ->view('filament.infolists.entries.attachments-list')
                                    ->viewData([
                                        'attachments' => fn($state) => is_string($state) ? json_decode($state, true) : $state,
                                    ]),
                            ])
                            ->columnSpanFull()
                            ->visible(fn(Homework $record) => !empty($record->attachments)),
                    ])
                    ->columns(3),

                // Submission section
                Infolists\Components\Section::make('Моя работа')
                    ->schema([
                        Infolists\Components\TextEntry::make('submission_status')
                            ->label('Статус')
                            ->getStateUsing(fn() => $submission?->status_label ?? 'Не сдано')
                            ->badge()
                            ->color(fn() => $submission?->status_color ?? 'gray'),

                        Infolists\Components\TextEntry::make('submission_submitted_at')
                            ->label('Дата сдачи')
                            ->getStateUsing(fn() => format_datetime($submission?->submitted_at))
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('submission_grade')
                            ->label('Оценка')
                            ->getStateUsing(fn() => $submission?->grade !== null
                                ? $this->record->formatGrade($submission->grade)
                                : null)
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

                        Infolists\Components\ViewEntry::make('my_files')
                            ->hiddenLabel()
                            ->view('filament.infolists.entries.file-cards')
                            ->state($submission?->attachments)
                            ->viewData([
                                // Пометки учителя ученик видит только после проверки (как в новом кабинете)
                                'annotatedFiles' => $submission?->marksVisibleToStudent() ? ($submission->annotated_files ?? []) : [],
                                'showAnnotateButton' => false,
                                'submissionId' => $submission?->id,
                            ])
                            ->columnSpanFull()
                            ->visible($submission && !empty($submission->attachments)),
                    ])
                    ->columns(3),

                // Feedback section (shown prominently when revision requested)
                Infolists\Components\Section::make('Комментарий учителя')
                    ->schema([
                        Infolists\Components\TextEntry::make('submission_feedback')
                            ->hiddenLabel()
                            ->getStateUsing(fn() => $submission?->feedback)
                            ->html()
                            ->columnSpanFull(),

                        Infolists\Components\ViewEntry::make('submission_feedback_attachments')
                            ->hiddenLabel()
                            ->view('filament.infolists.entries.attachments-list')
                            ->viewData([
                                'attachments' => fn() => is_string($submission?->feedback_attachments)
                                    ? json_decode($submission?->feedback_attachments, true)
                                    : $submission?->feedback_attachments,
                            ])
                            ->visible(fn() => !empty($submission?->feedback_attachments)),
                    ])
                    ->visible(fn() => !empty($submission?->feedback))
                    ->icon(fn() => $submission?->status === HomeworkSubmission::STATUS_REVISION_REQUESTED ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('danger'),

                // History section
                Infolists\Components\Section::make('История')
                    ->schema([
                        Infolists\Components\ViewEntry::make('submission_activities')
                            ->hiddenLabel()
                            ->view('filament.infolists.entries.activity-timeline')
                            ->state(fn() => $submission?->activities()->with('user')->get() ?? collect()),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn() => $submission !== null),
            ]);
    }

    protected function getSubmission(): ?HomeworkSubmission
    {
        return $this->record->submissions()
            ->where('student_id', auth()->id())
            ->first();
    }
}
