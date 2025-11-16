<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;

class WebhookProcessing extends ProcessWebhookJob
{
    
        public function respondToValidWebhook(Request $request, WebhookConfig $config)
        {
            Log::debug('Webhook processed successfully', [
                'request' => $request->all()
            ]);
            return response()->json(['message' => 'Webhook processed successfully']);
        }

    
}