<?php

namespace App\Events;

use App\Models\Player;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerIdentified implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public ?Player $player = null,
        public bool $success = false,
        public ?string $message = null
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->user->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.identified';
    }

    public function broadcastWith(): array
    {
        return [
            'is_identifying' => $this->user->is_identifying,
            'player_id' => $this->player?->id,
            'player_name' => $this->player?->name,
            'success' => $this->success,
            'message' => $this->message,
        ];
    }
}
