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
                    ->dateTime()
                    ->timezone('Europe/Moscow')
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
                Tables\Columns\TextColumn::make('download')
                    ->label('Скачать')
                    ->getStateUsing(function (Recording $record) {
                        // Check if MP4 is available
                        $mp4Url = null;
                        $presentationUrl = null;

                        if (!empty($record->raw_data['playback']['format'])) {
                            $formats = $record->raw_data['playback']['format'];
                            if (!isset($formats[0])) {
                                $formats = [$formats];
                            }
                            foreach ($formats as $format) {
                                if (isset($format['type']) && $format['type'] === 'video' && isset($format['url'])) {
                                    $mp4Url = $format['url'];
                                    break;
                                } elseif (isset($format['type']) && $format['type'] === 'presentation' && isset($format['url'])) {
                                    $presentationUrl = $format['url'];
                                }
                            }
                        }

                        if ($mp4Url) {
                            return '<a href="' . $mp4Url . '" target="_blank" class="text-primary-600 hover:underline">Скачать MP4</a>';
                        } elseif ($presentationUrl) {
                            return '<span class="text-amber-600">MP4 недоступен</span>';
                        } else {
                            return '<span class="text-gray-500">Обработка...</span>';
                        }
                    })
                    ->html()
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
                // Sync happens in ListPages mount
            ])
            ->defaultSort('start_time', 'desc')
            ->actions([
                Tables\Actions\Action::make('play')
                    ->label('Смотреть')
                    ->icon('heroicon-o-play')
                    ->url(fn(Recording $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn(Recording $record) => !empty($record->url)),
                Tables\Actions\Action::make('download')
                    ->label('Скачать MP4')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(function (Recording $record) {
                        // Extract MP4 URL from raw_data
                        if (!empty($record->raw_data['playback']['format'])) {
                            $formats = $record->raw_data['playback']['format'];
                            // Handle single format or array of formats
                            if (!isset($formats[0])) {
                                $formats = [$formats];
                            }
                            foreach ($formats as $format) {
                                if (isset($format['type']) && $format['type'] === 'video' && isset($format['url'])) {
                                    return $format['url'];
                                }
                            }
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->visible(function (Recording $record) {
                        if (!empty($record->raw_data['playback']['format'])) {
                            $formats = $record->raw_data['playback']['format'];
                            if (!isset($formats[0])) {
                                $formats = [$formats];
                            }
                            foreach ($formats as $format) {
                                if (isset($format['type']) && $format['type'] === 'video') {
                                    return true;
                                }
                            }
                        }
                        return false;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Recording $record) {
                        // Delete from BBB First
                        try {
                            // Configure BBB (Similar logic to Sync - Refactor ideally)
                            $user = auth()->user();
                            if ($user->bbb_url && $user->bbb_secret) {
                                config([
                                    'bigbluebutton.BBB_SERVER_BASE_URL' => $user->bbb_url,
                                    'bigbluebutton.BBB_SECURITY_SALT' => $user->bbb_secret,
                                ]);
                            } else {
                                $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
                                $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');
                                if ($globalUrl && $globalSecret) {
                                    config(['bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl, 'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret]);
                                }
                            }

                            Bigbluebutton::deleteRecordings(['recordID' => $record->record_id]);
                        } catch (\Exception $e) {
                            // Log error but allow DB delete or halt?
                            // Halt to prevent de-sync
                            Notification::make()->title('Ошибка удаления с сервера BBB')->body($e->getMessage())->danger()->send();
                            // throw $e; // Prevent DB delete
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
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Scope to User's Rooms
        $userMeetingIds = Room::where('user_id', auth()->id())->pluck('meeting_id');
        return parent::getEloquentQuery()->whereIn('meeting_id', $userMeetingIds);
    }
}
