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
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->getStateUsing(function (Recording $record) {
                        if (!empty($record->vk_video_url)) {
                            // Uploaded to VK - show "Посмотреть" button
                            return '<a href="' . $record->vk_video_url . '" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>Посмотреть</a>';
                        } elseif (!empty($record->url)) {
                            // BBB processed, uploading to VK
                            return '<span class="inline-flex items-center gap-1.5 text-info-600"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>Отправка в VK...</span>';
                        } else {
                            // Processing by BBB
                            return '<span class="inline-flex items-center gap-1.5 text-warning-600"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>Обработка...</span>';
                        }
                    })
                    ->html(),
            ])
            ->filters([])
            ->searchable()
            ->defaultSort('start_time', 'desc')
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->before(function (Recording $record) {
                        // Delete from BBB First
                        try {
                            // Configure BBB
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
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Scope to User's Rooms
        $userMeetingIds = Room::where('user_id', auth()->id())->pluck('meeting_id');
        return parent::getEloquentQuery()->whereIn('meeting_id', $userMeetingIds);
    }
}
