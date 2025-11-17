<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;

class WebhookProcessing extends ProcessWebhookJob
{            

        public function handle()
        {
            $event = $this->webhookCall->payload['event'];
            Log::debug("event received: $event"); // contains an instance of `WebhookCall`
            switch ($event) {
                case 'throw':
                    $this->handleThrow();
                    break;
                case 'match_state':
                    $this->handleMatchState();
                    break;
            }
        }

    public function handleThrow()
    {
        Log::debug("throw received",[
            'matchId' => $this->webhookCall->payload['matchId'],
            'player' => $this->webhookCall->payload['data']['playerName'],
            'player_id' => $this->webhookCall->payload['data']['playerId'],
            'hit' => $this->webhookCall->payload['data']['throw']['segment']['name'],
            'multiplier' => $this->webhookCall->payload['data']['throw']['segment']['multiplier'],
            'number' => $this->webhookCall->payload['data']['throw']['segment']['number'],
        ]);
    }

    public function handleMatchState()
    {
        Log::debug("match_state",[
            'matchId' => $this->webhookCall->payload['matchId'],
        ]);
    }
}