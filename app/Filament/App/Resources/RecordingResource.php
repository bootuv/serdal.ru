<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\RecordingResource\Pages;
use App\Models\Recording;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class RecordingResource extends Resource
{
    protected static ?string $model = Recording::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationLabel = 'Записи';

    protected static ?string $modelLabel = 'Запись';

    protected static ?string $pluralModelLabel = 'Записи';

    protected static ?int $navigationSort = 7;

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
                        if (!empty($record->vk_video_url)) {
                            return 'Готово';
                        } elseif (!empty($record->url) && str_contains($record->url, '/playback/video/')) {
                            // Has BBB video URL but not yet on VK
                            return 'Отправка в VK';
                        } elseif (!empty($record->url)) {
                            // Presentation format - available on BBB
                            return 'Готово';
                        } else {
                            // Processing by BBB
                            return 'Обработка';
                        }
                    })
                    ->colors([
                        'success' => 'Готово',
                        'info' => 'Отправка в VK',
                        'warning' => 'Обработка',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'Готово',
                        'heroicon-m-arrow-path' => 'Отправка в VK',
                        'heroicon-m-clock' => 'Обработка',
                    ]),
            ])
            ->filters([])
            ->searchable()
            ->defaultSort('start_time', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Посмотреть')
                    ->icon('heroicon-m-play')
                    ->color('success')
                    ->url(fn(Recording $record) => static::getUrl('view', ['record' => $record]))
                    ->visible(fn(Recording $record) => !empty($record->vk_video_url)),

                Tables\Actions\Action::make('open_bbb')
                    ->label('Открыть в BBB')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Recording $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn(Recording $record) => empty($record->vk_video_url) && !empty($record->url)),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Recording $record) {
                        // Delete from BBB First
                        try {
                            // Configure BBB from global settings
                            $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
                            $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
                            if ($globalUrl && $globalSecret) {
                                config([
                                    'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                                    'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                                ]);
                            }

                            \Log::info('Attempting to delete recording from BBB', [
                                'record_id' => $record->record_id,
                                'bbb_url' => config('bigbluebutton.BBB_SERVER_BASE_URL')
                            ]);

                            // Debug: Check if recording exists and its state before deleting
                            try {
                                $check = Bigbluebutton::getRecordings(['recordID' => $record->record_id, 'state' => 'any']);
                                \Log::info('BBB Check Before Delete', ['record_id' => $record->record_id, 'check_result' => $check]);
                            } catch (\Exception $e) {
                                \Log::error('BBB Check Before Delete Failed', ['error' => $e->getMessage()]);
                            }

                            $response = Bigbluebutton::deleteRecordings(['recordID' => $record->record_id]);
                            \Log::info('BBB Delete Recording Response', ['record_id' => $record->record_id, 'response' => $response]);

                            if ($response instanceof \Illuminate\Support\Collection) {
                                $messageKey = $response->get('messageKey');
                                $returnCode = $response->get('returncode');

                                if ($messageKey === 'notFound' || $returnCode === 'SUCCESS') {
                                    return;
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::error('BBB Delete Recording Error', ['record_id' => $record->record_id, 'error' => $e->getMessage()]);
                            // Don't throw - allow local delete even if BBB delete fails
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecordings::route('/'),
            'view' => Pages\ViewRecording::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Scope to User's Rooms
        $userMeetingIds = Room::where('user_id', auth()->id())->pluck('meeting_id');
        return parent::getEloquentQuery()->whereIn('meeting_id', $userMeetingIds);
    }
}
