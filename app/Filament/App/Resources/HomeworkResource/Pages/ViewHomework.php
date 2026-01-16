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
                    ->compact()
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->label('Название')
                            ->columnSpan(1),

                        Infolists\Components\TextEntry::make('type_label')
                            ->label('Тип')
                            ->badge()
                            ->color(fn(Homework $record): string => $record->type_color)
                            ->icon(fn(Homework $record): string => $record->type_icon)
                            ->columnSpan(1),

                        Infolists\Components\TextEntry::make('room.name')
                            ->label('Урок')
                            ->placeholder('Не привязано')
                            ->columnSpan(1),

                        Infolists\Components\TextEntry::make('deadline')
                            ->label('Срок сдачи')
                            ->formatStateUsing(fn($state) => $state ? $state->translatedFormat('j F, H:i') : null)
                            ->placeholder('—')
                            ->color(fn(Homework $record) => $record->is_overdue ? 'danger' : null)
                            ->columnSpan(1),

                        Infolists\Components\IconEntry::make('is_visible')
                            ->label('Видимо')
                            ->boolean()
                            ->columnSpan(1),

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
                    ->columns(4),

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
