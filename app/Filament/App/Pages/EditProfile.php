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

    protected static ?string $navigationLabel = 'Профиль';

    protected static ?string $title = 'Редактировать профиль';

    protected static string $view = 'filament.app.pages.edit-profile';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\App\Widgets\LessonTypesTableWidget::class,
        ];
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
                    ->image()
                    ->avatar()
                    ->directory('avatars'),
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

        // Extract relationships
        $subjects = $data['subjects'] ?? [];
        $directs = $data['directs'] ?? [];

        // Remove relationships from data to avoid update error on user model
        unset($data['subjects']);
        unset($data['directs']);

        $user->update($data);

        // Sync relationships
        $user->subjects()->sync($subjects);
        $user->directs()->sync($directs);

        \Filament\Notifications\Notification::make()
            ->title('Профиль обновлен')
            ->success()
            ->send();
    }
}
