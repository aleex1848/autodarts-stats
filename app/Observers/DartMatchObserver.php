<?php

namespace App\Observers;

use App\Events\MatchUpdated;
use App\Models\DartMatch;
use App\Models\MatchdayFixture;
use Illuminate\Support\Facades\Log;

class DartMatchObserver
{
    public function updated(DartMatch $match): void
    {
        broadcast(new MatchUpdated($match));

        // If match was just finished and has a fixture, update the fixture with full stats
        if ($match->wasChanged('finished_at') && $match->finished_at !== null) {
            $fixture = MatchdayFixture::where('dart_match_id', $match->id)->first();

            if ($fixture && ($fixture->home_legs_won === null || $fixture->away_legs_won === null)) {
                // Fixture exists but is missing leg stats - update it
                try {
                    app(\App\Actions\AssignMatchToFixture::class)->handle($match, $fixture);
                    Log::info('Fixture updated after match finished', [
                        'fixture_id' => $fixture->id,
                        'match_id' => $match->id,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to update fixture after match finished', [
                        'fixture_id' => $fixture->id,
                        'match_id' => $match->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
