<?php

namespace App\Services;

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Turn;
use App\Support\WebhookProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;

class MatchReprocessingService
{
    public function reprocessMatch(DartMatch $match): void
    {
        DB::transaction(function () use ($match) {
            Log::info('Starting match reprocessing', [
                'match_id' => $match->id,
                'autodarts_match_id' => $match->autodarts_match_id,
            ]);

            // Get all leg IDs for this match
            $legIds = $match->legs()->pluck('id');

            // 1. Delete all DartThrow entries (via Turns -> Legs -> Match)
            $turnIds = Turn::whereIn('leg_id', $legIds)->pluck('id');
            $throwsDeleted = DartThrow::whereIn('turn_id', $turnIds)->delete();
            Log::debug('Deleted throws', ['count' => $throwsDeleted]);

            // 2. Delete all Turn entries (via Legs -> Match)
            $turnsDeleted = Turn::whereIn('leg_id', $legIds)->delete();
            Log::debug('Deleted turns', ['count' => $turnsDeleted]);

            // 3. Delete leg_player pivot entries (via Legs -> Match)
            $legPlayerDeleted = DB::table('leg_player')
                ->whereIn('leg_id', $legIds)
                ->delete();
            Log::debug('Deleted leg_player entries', ['count' => $legPlayerDeleted]);

            // 4. Delete all Leg entries (directly via match_id)
            $legsDeleted = Leg::where('match_id', $match->id)->delete();
            Log::debug('Deleted legs', ['count' => $legsDeleted]);

            // 5. Delete match_player pivot entries (directly via match_id)
            $matchPlayerDeleted = DB::table('match_player')
                ->where('match_id', $match->id)
                ->delete();
            Log::debug('Deleted match_player entries', ['count' => $matchPlayerDeleted]);

            // Reset match status fields (but keep the match itself)
            // Use default values instead of null for non-nullable columns
            $match->update([
                'winner_player_id' => null,
                'finished_at' => null,
                'base_score' => 501,
                'in_mode' => 'Straight',
                'out_mode' => 'Straight',
                'bull_mode' => '25/50',
                'max_rounds' => 20,
            ]);

            // Find all WebhookCalls for this match
            $webhookCalls = WebhookCall::whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.matchId')) = ?",
                [$match->autodarts_match_id]
            )->orderBy('created_at')->get();

            Log::info('Found webhook calls for reprocessing', [
                'count' => $webhookCalls->count(),
            ]);

            // Reprocess all WebhookCalls
            foreach ($webhookCalls as $webhookCall) {
                try {
                    $job = new WebhookProcessing($webhookCall);
                    $job->handle();
                } catch (\Exception $e) {
                    Log::error('Error reprocessing webhook call', [
                        'webhook_call_id' => $webhookCall->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            Log::info('Match reprocessing completed', [
                'match_id' => $match->id,
                'webhook_calls_processed' => $webhookCalls->count(),
            ]);

            // If this match is assigned to a fixture, update the fixture after reprocessing
            $fixture = \App\Models\MatchdayFixture::where('dart_match_id', $match->id)->first();
            if ($fixture) {
                // Refresh match to get latest data after reprocessing
                $match->refresh();
                $match->load('players');
                
                // Only update fixture if match is finished
                if ($match->finished_at !== null) {
                    try {
                        app(\App\Actions\AssignMatchToFixture::class)->handle($match, $fixture);
                        Log::info('Fixture updated after match reprocessing', [
                            'fixture_id' => $fixture->id,
                            'match_id' => $match->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to update fixture after match reprocessing', [
                            'fixture_id' => $fixture->id,
                            'match_id' => $match->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    Log::debug('Match not finished after reprocessing, skipping fixture update', [
                        'fixture_id' => $fixture->id,
                        'match_id' => $match->id,
                    ]);
                }
            }
        });
    }
}
