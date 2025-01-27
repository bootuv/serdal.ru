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

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('avatar')
                    ->image()
                    ->avatar()
                    ->directory('avatars'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('status')
                    ->maxLength(255),
                TextInput::make('email')
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => bcrypt($state)),
                Select::make('role')
                    ->label('Роль')
                    ->options([
                        User::ROLE_ADMIN => 'Администратор',
                        User::ROLE_MENTOR => 'Ментор',
                        User::ROLE_TUTOR => 'Репетитор',
                        User::ROLE_STUDENT => 'Студент',
                    ])
                    ->required(),
                Select::make('subjects')
                    ->multiple()
                    ->relationship('subjects', 'name'),
                Select::make('directs')
                    ->multiple()
                    ->relationship('directs', 'name'),
                Select::make('grade')
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
                    ->columnSpan(2),
                RichEditor::make('extra_info')
                    ->columnSpan(2),

                Group::make([   
                    TextInput::make('phone')->tel() ,
                    TextInput::make('whatsup')->tel(),
                    TextInput::make('instagram'),
                    TextInput::make('telegram'),
                ])->columns(2)->columnSpanFull(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
                TextColumn::make('username'),
                TextColumn::make('role'),
                // SelectColumn::make('role')
                //     ->options([
                //         User::ROLE_ADMIN => 'Администратор',
                //         User::ROLE_MENTOR => 'Ментор',
                //         User::ROLE_TUTOR => 'Тьютор',
                //     ]),
                TextColumn::make('created_at'),
                TextColumn::make('updated_at'),
            ])
            ->filters([
                //
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
