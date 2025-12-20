<?php

namespace App\Filament\App\Pages;

use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use App\Models\LessonType;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class Onboarding extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.onboarding';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Добро пожаловать!';

    public ?array $data = [];

    public function mount()
    {
        $user = Auth::user();

        // Safety check: redirect if already completed
        if ($user && $user->is_profile_completed) {
            return redirect()->route('filament.app.pages.dashboard');
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
                    ->description('Для начала работы необходимо заполнить информацию о себе.')
                    ->schema([
                        Forms\Components\FileUpload::make('avatar')
                            ->label('Фото профиля')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->directory('avatars'),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('whatsup')->label('WhatsApp')->placeholder('+7...'),
                                Forms\Components\TextInput::make('instagram')->label('Instagram')->prefix('@'),
                                Forms\Components\TextInput::make('telegram')->label('Telegram')->prefix('@'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LessonType::query()->where('user_id', Auth::id()))
            ->heading('Типы уроков')
            ->description('Создайте хотя бы один тип урока для продолжения.')
            ->modelLabel('Тип урока')
            ->pluralModelLabel('Типы уроков')
            ->emptyStateHeading('Типы уроков не добавлены')
            ->emptyStateDescription('Создайте свой первый тип урока для старта.')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Создать')
                    ->modalHeading('Добавить тип урока')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Тип')
                            ->options([
                                'Индивидуальный' => 'Индивидуальный',
                                'Групповой' => 'Групповой',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('price')->label('Цена')->numeric()->suffix('₽')->required(),
                        Forms\Components\TextInput::make('duration')->label('Длительность')->numeric()->suffix('мин')->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        return $data;
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('Название'),
                Tables\Columns\TextColumn::make('price')->label('Цена')->money('RUB'),
                Tables\Columns\TextColumn::make('duration')->label('Длительность')->suffix(' мин'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->form([
                    Forms\Components\Select::make('type')
                        ->label('Тип')
                        ->options([
                            'Индивидуальный' => 'Индивидуальный',
                            'Групповой' => 'Групповой',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('price')->label('Цена')->numeric()->suffix('₽')->required(),
                    Forms\Components\TextInput::make('duration')->label('Длительность')->numeric()->suffix('мин')->required(),
                ]),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public function submit()
    {
        $data = $this->form->getState();
        $user = Auth::user();

        /** @var User $user */
        if ($user->lessonTypes()->count() === 0) {
            Notification::make()
                ->title('Ошибка')
                ->body('Пожалуйста, добавьте хотя бы один тип урока перед продолжением.')
                ->danger()
                ->send();
            return;
        }

        $user->update([
            'avatar' => $data['avatar'],
            'whatsup' => $data['whatsup'],
            'instagram' => $data['instagram'],
            'telegram' => $data['telegram'],
            'is_profile_completed' => true,
        ]);

        Notification::make()
            ->title('Профиль успешно настроен!')
            ->success()
            ->send();

        return redirect()->route('filament.app.pages.dashboard');
    }
}
