<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Password;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Group;
use App\Filament\Resources\UserResource\RelationManagers\LessonTypesRelationManager;
use Filament\Tables\Filters\SelectFilter;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'Пользователь';

    protected static ?string $pluralModelLabel = 'Пользователи';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('avatar')
                    ->label('Фото профиля')
                    ->image()
                    ->avatar()
                    ->directory('avatars'),
                TextInput::make('name')
                    ->label('Имя')
                    ->required()
                    ->maxLength(255),
                TextInput::make('status')
                    ->label('Статус')
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Электронная почта')
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->label('Имя пользователя')
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => bcrypt($state)),
                Select::make('role')
                    ->label('Роль')
                    ->options([
                        User::ROLE_ADMIN => 'Администратор',
                        User::ROLE_MENTOR => 'Ментор',
                        User::ROLE_TUTOR => 'Репетитор',
                        User::ROLE_STUDENT => 'Учащийся',
                    ])
                    ->required(),
                Select::make('subjects')
                    ->label('Предметы')
                    ->multiple()
                    ->relationship('subjects', 'name'),
                Select::make('directs')
                    ->label('Направления')
                    ->multiple()
                    ->relationship('directs', 'name'),
                Select::make('grade')
                    ->label('Классы')
                    ->multiple()
                    ->options([
                        'preschool' => 'Дошкольники',
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        4 => 4,
                        5 => 5,
                        6 => 6,
                        7 => 7,
                        8 => 8,
                        9 => 9,
                        10 => 10,
                        11 => 11,
                        'adults' => 'Взрослые',
                    ]),
                RichEditor::make('about')
                    ->label('О себе')
                    ->columnSpan(2),
                RichEditor::make('extra_info')
                    ->label('Дополнительная информация')
                    ->columnSpan(2),

                Group::make([
                    TextInput::make('phone')->tel()->label('Телефон'),
                    TextInput::make('whatsup')->tel()->label('WhatsApp'),
                    TextInput::make('instagram')->label('Instagram'),
                    TextInput::make('telegram')->label('Telegram'),
                ])->columns(2)->columnSpanFull(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Имя'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('username')->label('Имя пользователя'),
                TextColumn::make('role')->label('Роль'),
                // SelectColumn::make('role')
                //     ->options([
                //         User::ROLE_ADMIN => 'Администратор',
                //         User::ROLE_MENTOR => 'Ментор',
                //         User::ROLE_TUTOR => 'Тьютор',
                //     ]),
                TextColumn::make('created_at')->label('Создан'),
                TextColumn::make('updated_at')->label('Обновлен'),
            ])
            ->filters([
                SelectFilter::make('user_type')
                    ->label('Тип пользователя')
                    ->multiple()
                    ->options([
                        'mentor' => 'Ментор',
                        'tutor' => 'Репетитор',
                    ]),
                SelectFilter::make('direct')
                    ->label('Направление')
                    ->multiple()
                    ->relationship('directs', 'name'),
                SelectFilter::make('subject')
                    ->label('Предмет')
                    ->multiple()
                    ->relationship('subjects', 'name'),
                SelectFilter::make('grade')
                    ->label('Класс')
                    ->multiple()
                    ->options([
                        'preschool' => 'Дошкольники',
                        '1' => '1 класс',
                        '2' => '2 класс',
                        '3' => '3 класс',
                        '4' => '4 класс',
                        '5' => '5 класс',
                        '6' => '6 класс',
                        '7' => '7 класс',
                        '8' => '8 класс',
                        '9' => '9 класс',
                        '10' => '10 класс',
                        '11' => '11 класс',
                        'adults' => 'Взрослые',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            LessonTypesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
