<?php

namespace App\Filament\App\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class Onboarding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.onboarding';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Добро пожаловать!';

    public ?array $data = [];

    public function mount(): void
    {
        $user = Auth::user();

        // Safety check: redirect if already completed
        if ($user && $user->is_profile_completed) {
            redirect('/app');
        }

        $this->form->fill([
            'avatar' => $user->avatar,
            'whatsup' => $user->whatsup,
            'instagram' => $user->instagram,
            'telegram' => $user->telegram,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Заполните профиль')
                    ->description('Для начала работы необходимо заполнить информацию о себе и указать стоимость занятий.')
                    ->schema([
                        Forms\Components\FileUpload::make('avatar')
                            ->label('Фото профиля')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->directory('avatars')
                            ->required(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('whatsup')->label('WhatsApp')->placeholder('+7...'),
                                Forms\Components\TextInput::make('instagram')->label('Instagram')->prefix('@'),
                                Forms\Components\TextInput::make('telegram')->label('Telegram')->prefix('@'),
                            ]),

                        Forms\Components\Repeater::make('lessonTypes')
                            ->label('Типы и стоимость занятий')
                            ->schema([
                                Forms\Components\TextInput::make('type')
                                    ->label('Название (например: Индивидуально)')
                                    ->required(),
                                Forms\Components\TextInput::make('price')
                                    ->label('Цена')
                                    ->numeric()
                                    ->suffix('₽')
                                    ->required(),
                                Forms\Components\TextInput::make('duration')
                                    ->label('Длительность')
                                    ->numeric()
                                    ->suffix('мин')
                                    ->required(),
                            ])
                            ->required()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columnSpanFull()
                            ->grid(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = Auth::user();

        /** @var User $user */
        $user->update([
            'avatar' => $data['avatar'],
            'whatsup' => $data['whatsup'],
            'instagram' => $data['instagram'],
            'telegram' => $data['telegram'],
            'is_profile_completed' => true,
        ]);

        // Сохраняем типы уроков
        $user->lessonTypes()->delete();
        $user->lessonTypes()->createMany($data['lessonTypes']);

        Notification::make()
            ->title('Профиль успешно настроен!')
            ->success()
            ->send();

        $this->redirect('/app');
    }
}
