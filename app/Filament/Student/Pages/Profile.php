<?php

namespace App\Filament\Student\Pages;

use App\Services\StudentProfileService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class Profile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.student.pages.profile';

    protected static ?string $navigationLabel = 'Профиль';

    protected static ?string $title = 'Мой профиль';

    protected static ?int $navigationSort = 999;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('avatar')
                    ->label('Аватар')
                    ->disk('s3')
                    ->visibility('public')
                    // Optimization: Do not check file existence/metadata on S3 during load
                    ->fetchFileInformation(false)
                    ->image()
                    ->avatar()
                    ->imageEditor()
                    ->directory(fn() => 'avatars/' . auth()->id())
                    ->live()
                    ->deleteUploadedFileUsing(\App\Helpers\FileUploadHelper::filamentDeleteCallback())
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('name')
                    ->label('ФИО')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->maxLength(255)
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => bcrypt($state))
                    ->helperText('Оставьте пустым, если не хотите менять пароль'),

                Forms\Components\TextInput::make('phone')
                    ->label('Телефон')
                    ->tel()
                    ->maxLength(255),

                Forms\Components\Select::make('grade')
                    ->label('Класс')
                    ->searchable(false)
                    ->options(StudentProfileService::GRADES)
                    ->dehydrateStateUsing(fn ($state) => StudentProfileService::gradeForStorage($state))
                    ->afterStateHydrated(fn ($component, $state) => $component->state(StudentProfileService::gradeForForm($state))),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(StudentProfileService::class)->update(auth()->user(), $data);

        Notification::make()
            ->success()
            ->title('Профиль обновлен')
            ->send();
    }
}
