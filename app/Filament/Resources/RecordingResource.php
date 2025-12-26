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
                Tables\Columns\TextColumn::make('meeting_id')
                    ->label('ID Встречи')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Начало')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('participants')
                    ->label('Участники')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('published')
                    ->label('Опубликовано')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('published')
                    ->label('Опубликовано'),
            ])
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

                            // Fetch ALL
                            $response = Bigbluebutton::getRecordings(['state' => 'any']);
                            $recs = collect($response);

                            $count = 0;
                            foreach ($recs as $rec) {
                                $r = (array) $rec;
                                Recording::updateOrCreate(
                                    ['record_id' => $r['recordID']],
                                    [
                                        'meeting_id' => $r['meetingID'],
                                        'name' => $r['name'],
                                        'published' => $r['published'] === 'true' || $r['published'] === true,
                                        'start_time' => isset($r['startTime']) ? \Carbon\Carbon::createFromTimestamp($r['startTime'] / 1000) : null,
                                        'end_time' => isset($r['endTime']) ? \Carbon\Carbon::createFromTimestamp($r['endTime'] / 1000) : null,
                                        'participants' => $r['participants'] ?? 0,
                                        'url' => isset($r['playback']['format']['url']) ? $r['playback']['format']['url'] : (isset($r['playback']['format'][0]['url']) ? $r['playback']['format'][0]['url'] : null),
                                        'raw_data' => $r,
                                    ]
                                );
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
                Tables\Actions\Action::make('play')
                    ->label('Смотреть')
                    ->icon('heroicon-o-play')
                    ->url(fn(Recording $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn(Recording $record) => !empty($record->url)),
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
        ];
    }
}
