<?php

namespace App\Livewire;

use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use JoisarJignesh\Bigbluebutton\Facades\Bigbluebutton;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app')]
class GuestJoinRoom extends Component implements HasForms
{
    use InteractsWithForms;

    public Room $room;
    public ?array $data = [];

    // States: 'waiting', 'name_input'
    public string $state = 'waiting';
    public bool $isRoomRunning = false;

    public function mount(Room $room): void
    {
        $this->room = $room;
        $this->configureBbb();

        // Check if room is already running
        $this->checkRoomStatus();

        // If room is running, show name input form
        if ($this->isRoomRunning) {
            $this->state = 'name_input';
        }

        $this->form->fill();
    }

    private function configureBbb(): void
    {
        $owner = $this->room->user;
        if ($owner && $owner->bbb_url && $owner->bbb_secret) {
            config([
                'bigbluebutton.BBB_SERVER_BASE_URL' => $owner->bbb_url,
                'bigbluebutton.BBB_SECURITY_SALT' => $owner->bbb_secret,
            ]);
        } else {
            $globalUrl = \App\Models\Setting::where('key', 'bbb_url')->value('value');
            $globalSecret = \App\Models\Setting::where('key', 'bbb_secret')->value('value');

            if ($globalUrl && $globalSecret) {
                config([
                    'bigbluebutton.BBB_SERVER_BASE_URL' => $globalUrl,
                    'bigbluebutton.BBB_SECURITY_SALT' => $globalSecret,
                ]);
            }
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Ваше имя')
                    ->placeholder('Введите ваше имя')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
            ])
            ->statePath('data');
    }

    #[On('echo:rooms,.room.status.updated')]
    public function onRoomStatusUpdated(): void
    {
        $this->checkRoomStatus();
    }

    public function checkRoomStatus(): void
    {
        $this->configureBbb();

        try {
            $this->isRoomRunning = Bigbluebutton::isMeetingRunning(['meetingID' => $this->room->meeting_id]);
        } catch (\Exception $e) {
            $this->isRoomRunning = false;
        }
    }

    public function joinSession(): void
    {
        // Transition to name input form when clicking "Join"
        $this->state = 'name_input';
    }

    public function submitName(): void
    {
        $data = $this->form->getState();

        // Save guest name to session
        session(['guest_name' => $data['name']]);

        // Redirect to connect (RoomController will handle the actual BBB join)
        $this->redirect(route('rooms.connect', $this->room));
    }

    public function render()
    {
        return view('livewire.guest-join-room');
    }
}
