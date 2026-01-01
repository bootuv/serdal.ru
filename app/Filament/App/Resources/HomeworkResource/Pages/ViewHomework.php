<?php

namespace App\Filament\App\Resources\HomeworkResource\Pages;

use App\Filament\App\Resources\HomeworkResource;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class ViewHomework extends ViewRecord
{
    protected static string $resource = HomeworkResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Информация о задании')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->label('Название'),

                        Infolists\Components\TextEntry::make('room.name')
                            ->label('Урок')
                            ->placeholder('Не привязано к уроку'),

                        Infolists\Components\TextEntry::make('deadline')
                            ->label('Срок сдачи')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Без ограничений')
                            ->color(fn(Homework $record) => $record->is_overdue ? 'danger' : null),

                        Infolists\Components\IconEntry::make('is_visible')
                            ->label('Видимо для учеников')
                            ->boolean(),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Описание')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Файлы задания')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('attachments')
                            ->hiddenLabel()
                            ->schema([
                                Infolists\Components\TextEntry::make('')
                                    ->getStateUsing(fn($state) => basename($state))
                                    ->url(fn($state) => \Storage::url($state))
                                    ->openUrlInNewTab(),
                            ])
                            ->columns(1),
                    ])
                    ->visible(fn(Homework $record) => !empty($record->attachments))
                    ->collapsed(),

                Infolists\Components\Section::make('Сданные работы')
                    ->schema([
                        Infolists\Components\ViewEntry::make('submissions_table')
                            ->view('filament.infolists.entries.homework-submissions-table')
                            ->viewData([
                                'record' => fn() => $this->record,
                            ]),
                    ]),
            ]);
    }
}
