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
                            ->columnSpanFull(),

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

        // 1. Проверка на существующего пользователя
        if (User::where('email', $data['email'])->exists()) {
            Notification::make()
                ->title('Ошибка')
                ->body('Пользователь с таким Email уже зарегистрирован.')
                ->danger()
                ->send();
            return;
        }

        // 2. Проверка на существующую заявку
        // Здесь мы проверяем не только email, но и статус. 
        // Если была отклонена - можно подать новую? Пользователь сказал: "Если учитель отправит заявку повторно, то нужно показывать сообщение...".
        // Значит, если email уже есть в базе заявок (независимо от статуса? или только pending?). 
        // Логично проверять Pending. Если Rejected, может он исправился? Но ТЗ говорит "Ваша заявка уже отправлена и находится на рассмотрении". Это для Pending.
        // А если Rejected? "Ваша заявка была отклонена". 
        // Давайте сделаем простую проверку: если есть любая запись с таким email - не пускаем. Или уточним?
        // ТЗ: "Если учитель отправит заявку повторно, то нужно показывать сообщение 'Ваша заявка уже отправлена и находится на рассмотрении'"
        // Это подразумевает, что если она отклонена, он НЕ должен видеть это сообщение. Он должен иметь возможность отправить новую?
        // Или если она отклонена, он все равно не может отправить новую с тем же email?
        // Обычно, если отклонили, email "освобождается" или блокируется навсегда.
        // Я сделаю так: Если есть заявка со статусом 'pending' -> ошибка. Если 'rejected' -> можно новую. Если 'approved' -> он уже должен быть User, и сработает первая проверка.

        $pendingApp = TeacherApplication::where('email', $data['email'])->where('status', 'pending')->first();
        if ($pendingApp) {
            Notification::make()
                ->title('Заявка уже в обработке')
                ->body('Ваша заявка уже отправлена и находится на рассмотрении')
                ->warning()
                ->send();
            return;
        }

        // Создаем заявку
        TeacherApplication::create($data);

        // Устанавливаем флаг успешной отправки
        $this->isSubmitted = true;

        Notification::make()
            ->title('Заявка успешно отправлена!')
            ->body('Мы рассмотрим её в ближайшее время и пришлем ответ на почту.')
            ->success()
            ->send();

        $this->form->fill();
    }

    public function render()
    {
        return view('livewire.become-tutor-page')->layout('components.layouts.app'); // Используем дефолтный лейаут приложения, если есть
    }
}
