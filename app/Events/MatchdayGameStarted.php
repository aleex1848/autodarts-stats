<?php

namespace App\Events;

use App\Models\DartMatch;
use App\Models\Matchday;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchdayGameStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public Matchday $matchday,
        public ?DartMatch $match = null,
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
        return 'matchday.game.started';
    }

    public function broadcastWith(): array
    {
        return [
            'playing_matchday_id' => $this->user->playing_matchday_id,
            'matchday_id' => $this->matchday->id,
            'matchday_number' => $this->matchday->matchday_number,
            'match_id' => $this->match?->id,
            'success' => $this->success,
            'message' => $this->message,
        ];
    }
}
