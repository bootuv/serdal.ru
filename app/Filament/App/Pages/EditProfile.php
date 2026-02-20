<?php

namespace App\Filament\App\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms;
use Filament\Forms\Form;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Мой профиль';

    protected static ?string $navigationGroup = '';

    protected static ?string $title = 'Редактировать профиль';

    protected static string $view = 'filament.app.pages.edit-profile';

    protected static ?int $navigationSort = 100;

    public ?array $data = [];

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            ...$user->attributesToArray(),
            'subjects' => $user->subjects->pluck('id')->toArray(),
            'directs' => $user->directs->pluck('id')->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('avatar')
                    ->label('Фото профиля')
                    ->disk('s3')
                    ->visibility('public')
                    // Optimization: Do not check file existence/metadata on S3 during load
                    // This prevents slow synchronous calls. Speed up page load.
                    ->fetchFileInformation(false)
                    ->image()
                    ->avatar()
                    ->imageEditor()
                    ->directory(fn() => 'avatars/' . auth()->id())
                    ->live()
                    ->deleteUploadedFileUsing(\App\Helpers\FileUploadHelper::filamentDeleteCallback()),
                Forms\Components\Group::make([
                    Forms\Components\TextInput::make('last_name')
                        ->label('Фамилия')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('first_name')
                        ->label('Имя')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('middle_name')
                        ->label('Отчество')
                        ->maxLength(255),
                ])->columns(3)->columnSpanFull(),
                Forms\Components\TextInput::make('status')
                    ->label('Статус')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('email')
                    ->label('Электронная почта')
                    ->required()
                    ->maxLength(255)
                    ->disabled()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('password')
                    ->label('Новый пароль')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->maxLength(255)
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => bcrypt($state))
                    ->helperText('Оставьте пустым, если не хотите менять пароль')
                    ->columnSpanFull(),


                Forms\Components\Select::make('subjects')
                    ->label('Предметы')
                    ->multiple()
                    ->options(\App\Models\Subject::all()->pluck('name', 'id'))
                    ->columnSpanFull(),

                Forms\Components\Select::make('directs')
                    ->label('Направления')
                    ->multiple()
                    ->options(\App\Models\Direct::all()->pluck('name', 'id'))
                    ->columnSpanFull(),

                Forms\Components\Select::make('grade')
                    ->label('Классы')
                    ->multiple()
                    ->options([
                        'preschool' => 'Дошкольники',
                        ...array_combine(range(1, 11), array_map(fn($i) => "$i класс", range(1, 11))),
                        'adults' => 'Взрослые',
                    ])
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('about')
                    ->label('О себе')
                    ->columnSpan(2),
                Forms\Components\RichEditor::make('extra_info')
                    ->label('Дополнительная информация')
                    ->columnSpan(2),

                Forms\Components\Group::make([
                    Forms\Components\TextInput::make('phone')->tel()->label('Телефон'),
                    Forms\Components\TextInput::make('whatsup')->tel()->label('WhatsApp'),
                    Forms\Components\TextInput::make('instagram')->label('Instagram'),
                    Forms\Components\TextInput::make('telegram')->label('Telegram'),
                ])->columns(2)->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        // Process Avatar
        // Check if avatar is set and not empty (it will be empty if user didn't upload new one)
        if (!empty($data['avatar'])) {
            $processed = \App\Helpers\FileUploadHelper::processFiles(
                $data['avatar'],
                'avatars',
                640,
                640
            );
            $data['avatar'] = $processed[0] ?? null;
        } else {
            // If empty, unset it to preserve existing avatar
            unset($data['avatar']);
        }

        // Extract relationships
        $subjects = $data['subjects'] ?? [];
        $directs = $data['directs'] ?? [];

        // Remove relationships from data to avoid update error on user model
        unset($data['subjects']);
        unset($data['directs']);

        // Remove password if empty (double check, though dehydrated handles this usually)
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        // Sync relationships
        $user->subjects()->sync($subjects);
        $user->directs()->sync($directs);

        // If password was updated, we must re-login to keep session
        if (isset($data['password'])) {
            \Illuminate\Support\Facades\Auth::login($user);
        }

        \Filament\Notifications\Notification::make()
            ->title('Профиль обновлен')
            ->success()
            ->send();
    }
}
