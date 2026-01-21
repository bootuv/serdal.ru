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
                            ->disk('s3')
                            ->visibility('public')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->directory('avatars')
                            ->live()
                            ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set, $state) {
                                if (empty($state))
                                    return;

                                $processedState = [];
                                $hasChanges = false;

                                foreach ((array) $state as $file) {
                                    if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                        $mimeType = $file->getMimeType();
                                        $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
                                        $isImage = str_starts_with($mimeType, 'image/') && !str_contains($mimeType, 'gif');

                                        if ($isImage) {
                                            try {
                                                $imageContent = $file->get();
                                                $image = \Intervention\Image\Laravel\Facades\Image::read($imageContent);
                                                $image->scaleDown(640, 640);

                                                $newPath = 'avatars/' . uniqid() . '_' . time() . '.' . $extension;
                                                $encoded = $image->encodeByExtension($extension, quality: 85);

                                                \Illuminate\Support\Facades\Storage::disk('s3')->put($newPath, (string) $encoded, 'public');
                                                $processedState[] = $newPath;
                                                $hasChanges = true;
                                                continue;
                                            } catch (\Exception $e) {
                                                \Log::error('Avatar resize failed: ' . $e->getMessage());
                                            }
                                        }
                                        $newPath = $file->store('avatars', 's3');
                                        $processedState[] = $newPath;
                                        $hasChanges = true;
                                    } else {
                                        $processedState[] = $file;
                                    }
                                }

                                if ($hasChanges) {
                                    $set('avatar', count($processedState) === 1 ? $processedState[0] : $processedState);
                                }
                            }),

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

            ->modelLabel('Тип урока')
            ->pluralModelLabel('Типы уроков')
            ->emptyStateHeading('Типы уроков не добавлены')
            ->emptyStateDescription('Добавьте хотя бы один тип урока для старта.')
            ->paginated(false)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить')
                    ->createAnother(false)
                    ->visible(fn() => LessonType::where('user_id', Auth::id())->count() < 2)
                    ->modalHeading('Добавить тип урока')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Тип')
                            ->options(function () {
                                $existingTypes = LessonType::where('user_id', Auth::id())
                                    ->pluck('type')
                                    ->toArray();

                                $types = [
                                    LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                                    LessonType::TYPE_GROUP => 'Групповой',
                                ];

                                return array_diff_key($types, array_flip($existingTypes));
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                if ($state === LessonType::TYPE_INDIVIDUAL) {
                                    $set('payment_type', 'per_lesson');
                                } elseif ($state === LessonType::TYPE_GROUP) {
                                    $set('payment_type', 'monthly');
                                }
                            }),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price')->label('Цена за урок')->numeric()->suffix('₽')->required(),
                                Forms\Components\Select::make('payment_type')
                                    ->label('Тип оплаты')
                                    ->options([
                                        'per_lesson' => 'Поурочная оплата',
                                        'monthly' => 'Помесячная оплата',
                                    ])
                                    ->default('per_lesson')
                                    ->required()
                                    ->selectablePlaceholder(false),
                            ]),
                        Forms\Components\TextInput::make('duration')->label('Длительность')->numeric()->suffix('мин')->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        return $data;
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Название')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                        LessonType::TYPE_GROUP => 'Групповой',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('price')->label('Цена')->money('RUB'),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Тип оплаты')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'per_lesson' => 'Поурочная',
                        'monthly' => 'Помесячная',
                        default => $state,
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->form([
                    Forms\Components\Select::make('type')
                        ->label('Тип')
                        ->options([
                            LessonType::TYPE_INDIVIDUAL => 'Индивидуальный',
                            LessonType::TYPE_GROUP => 'Групповой',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                            if ($state === LessonType::TYPE_INDIVIDUAL) {
                                $set('payment_type', 'per_lesson');
                            } elseif ($state === LessonType::TYPE_GROUP) {
                                $set('payment_type', 'monthly');
                            }
                        }),
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('price')->label('Цена за урок')->numeric()->suffix('₽')->required(),
                            Forms\Components\Select::make('payment_type')
                                ->label('Тип оплаты')
                                ->options([
                                    'per_lesson' => 'Поурочная оплата',
                                    'monthly' => 'Помесячная оплата',
                                ])
                                ->default('per_lesson')
                                ->required()
                                ->selectablePlaceholder(false),
                        ]),
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

        // Notify all admins about teacher completing onboarding
        $admins = User::where('role', User::ROLE_ADMIN)->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\TeacherCompletedOnboarding($user));
        }

        Notification::make()
            ->title('Профиль успешно настроен!')
            ->success()
            ->send();

        return redirect()->route('filament.app.pages.dashboard');
    }
}
