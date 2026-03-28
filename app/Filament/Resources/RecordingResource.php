<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecordingResource\Pages;
use App\Models\Recording;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Filament\Notifications\Notification;

class RecordingResource extends Resource
{
    protected static ?string $model = Recording::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationLabel = 'Записи';

    protected static ?string $modelLabel = 'Запись';

    protected static ?string $pluralModelLabel = 'Записи';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->disabled(),
                Forms\Components\TextInput::make('meeting_id')
                    ->label('ID встречи')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Начало')
                    ->formatStateUsing(fn($state) => format_datetime(\Carbon\Carbon::parse($state)->setTimezone('Europe/Moscow')))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('participants')
                    ->label('Участники')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status_label')
                    ->label('Статус')
                    ->badge()
                    ->getStateUsing(function (Recording $record) {
                        if (!empty($record->s3_url)) {
                            return 'Готово';
                        } elseif (!empty($record->url) && str_contains($record->url, '/playback/video/')) {
                            return 'Загрузка';
                        } elseif (!empty($record->url)) {
                            return 'Готово';
                        } else {
                            return 'Обработка';
                        }
                    })
                    ->colors([
                        'success' => 'Готово',
                        'info' => 'Загрузка',
                        'warning' => 'Обработка',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'Готово',
                        'heroicon-m-arrow-path' => 'Загрузка',
                        'heroicon-m-clock' => 'Обработка',
                    ]),
            ])
            ->filters([])
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->persistFiltersInSession()
            ->searchable()
            ->headerActions([
                Tables\Actions\Action::make('sync')
                    ->label('Синхронизировать')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        try {
                            // Admin Sync uses GLOBAL settings
                            $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
                            $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
                            if ($globalUrl && $globalSecret) {
                                config([
                                    'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                                    'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                                ]);
                            }

                            // Fetch ALL - skip deleted & unpublished
                            $response = Bigbluebutton::getRecordings(['state' => 'published,processing']);
                            $recs = collect($response);

                            $count = 0;
                            foreach ($recs as $rec) {
                                $r = (array) $rec;

                                $meetingID = trim((string) $r['meetingID']);
                                $recordID = trim((string) $r['recordID']);
                                $name = trim((string) $r['name']);
                                $publishedStr = trim((string) ($r['published'] ?? 'false'));
                                $state = trim((string) ($r['state'] ?? 'unknown'));
                                $startTimeRaw = trim((string) ($r['startTime'] ?? ''));
                                $endTimeRaw = trim((string) ($r['endTime'] ?? ''));

                                $isPublished = ($publishedStr === 'true' || $publishedStr === '1');
                                $startTime = $startTimeRaw ? \Carbon\Carbon::createFromTimestamp($startTimeRaw / 1000) : null;

                                // Filter out "zombie" recordings
                                if (in_array($state, ['deleted', 'unpublished']) || (!$isPublished && (!$startTime || $startTime->lt(now()->subHours(24))))) {
                                    continue;
                                }

                                $recording = Recording::withTrashed()->where('record_id', $recordID)->first();

                                if ($recording) {
                                    if ($recording->trashed()) {
                                        continue;
                                    }
                                } else {
                                    $recording = new Recording(['record_id' => $recordID]);
                                }

                                $urlFormat = $r['playback']['format'] ?? [];
                                $url = null;
                                if (isset($urlFormat['url'])) {
                                    $url = $urlFormat['url'];
                                } elseif (isset($urlFormat[0]['url'])) {
                                    $url = $urlFormat[0]['url'];
                                }

                                $recording->fill([
                                    'meeting_id' => $meetingID,
                                    'name' => $name,
                                    'published' => $isPublished,
                                    'start_time' => $startTime,
                                    'end_time' => $endTimeRaw ? \Carbon\Carbon::createFromTimestamp($endTimeRaw / 1000) : null,
                                    'participants' => (int) trim((string) ($r['participants'] ?? '0')),
                                    'url' => $url ? trim((string) $url) : null,
                                    'raw_data' => json_decode(json_encode($r), true),
                                ]);
                                $recording->save();

                                // Cleanup placeholder if exists for this meeting
                                Recording::where('meeting_id', $meetingID)
                                    ->where('record_id', 'like', '%-placeholder-%')
                                    ->delete();
                                $count++;
                            }

                            Notification::make()
                                ->title("Синхронизировано {$count} записей")
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Ошибка синхронизации')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('start_time', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Посмотреть')
                    ->icon('heroicon-m-play')
                    ->color('success')
                    ->url(fn(Recording $record) => static::getUrl('view', ['record' => $record]))
                    ->visible(fn(Recording $record) => !empty($record->s3_url)),

                Tables\Actions\Action::make('open_bbb')
                    ->label('Открыть в BBB')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Recording $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn(Recording $record) => empty($record->s3_url) && !empty($record->url)),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Recording $record) {
                        try {
                            // Admin Delete using Global
                            $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
                            $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
                            if ($globalUrl && $globalSecret) {
                                config(['bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl, 'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret]);
                            }

                            Bigbluebutton::deleteRecordings(['recordID' => $record->record_id]);
                        } catch (\Exception $e) {
                            Notification::make()->title('Ошибка удаления с сервера BBB')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecordings::route('/'),
            'view' => Pages\ViewRecording::route('/{record}'),
        ];
    }
}
