<?php

namespace App\Livewire;

use App\Models\Direct;
use App\Models\Subject;
use App\Models\TeacherApplication;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Livewire\Component;

use Livewire\Attributes\Layout;

#[Layout('components.layouts.app')]
class BecomeTutorPage extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public bool $isSubmitted = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Личные данные')
                    ->schema([
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
                                ->required() // Теперь обязательно
                                ->maxLength(255),
                        ])->columns(3)->columnSpanFull(),

                        Forms\Components\TextInput::make('email')
                            ->label('Электронная почта')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->unique('users', 'email')
                            ->validationMessages([
                                'unique' => 'Пользователь с таким Email уже зарегистрирован.',
                            ])
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if (\App\Models\TeacherApplication::where('email', $value)->where('status', 'pending')->exists()) {
                                            $fail('Ваша заявка уже отправлена и находится на рассмотрении.');
                                        }
                                    };
                                },
                            ]),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->label('Телефон')
                            ->required()
                            ->columnSpanFull(), // Выносим телефон из группы соцсетей
                    ]),

                Forms\Components\Section::make('Профессиональные навыки')
                    ->schema([
                        Forms\Components\Select::make('subjects')
                            ->label('Предметы')
                            ->multiple()
                            ->options(Subject::all()->pluck('name', 'id'))
                            ->preload()
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('directs')
                            ->label('Направления')
                            ->multiple()
                            ->options(Direct::all()->pluck('name', 'id'))
                            ->preload()
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('grade')
                            ->label('Классы')
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
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('about') // Changed to Textarea for simple public form, or RichEditor? Use Textarea for simplicity first.
                            ->label('О себе')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        // Создаем заявку
        $application = TeacherApplication::create($data);

        // Отправка уведомления администраторам
        $admins = \App\Models\User::where('role', \App\Models\User::ROLE_ADMIN)->get();

        foreach ($admins as $admin) {
            // Database notification
            $admin->notify(new \App\Notifications\TeacherApplicationReceived($application));

            // Email notification
            try {
                \Illuminate\Support\Facades\Mail::to($admin->email)
                    ->send(new \App\Mail\NewTeacherApplicationMail($application));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Ошибка отправки уведомления администратору (' . $admin->email . '): ' . $e->getMessage());
            }
        }

        // Устанавливаем флаг успешной отправки
        $this->isSubmitted = true;

        // Очищаем форму
        $this->form->fill();

        Notification::make()
            ->title('Заявка успешно отправлена!')
            ->body('Мы рассмотрим её в ближайшее время и пришлем ответ на почту.')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.become-tutor-page');
    }
}
