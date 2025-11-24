<?php

namespace App\Actions;

use App\Enums\LeagueStatus;
use App\Models\League;
use App\Models\LeagueParticipant;
use App\Services\LeagueScheduler;
use Illuminate\Support\Facades\DB;

class SplitLeague
{
    public function __construct(
        protected LeagueScheduler $scheduler
    ) {}

    public function handle(League $league, array $splits): array
    {
        if ($league->status !== LeagueStatus::Registration->value) {
            throw new \InvalidArgumentException('Nur Ligen im Registrierungsstatus können gesplittet werden.');
        }

        $createdLeagues = [];

        DB::transaction(function () use ($league, $splits, &$createdLeagues) {
            foreach ($splits as $index => $split) {
                $newLeague = League::create([
                    'name' => $league->name.' '.($index + 1),
                    'description' => $league->description,
                    'max_players' => $split['max_players'] ?? $league->max_players,
                    'mode' => $league->mode,
                    'variant' => $league->variant,
                    'match_format' => $league->match_format,
                    'registration_deadline' => $league->registration_deadline,
                    'days_per_matchday' => $league->days_per_matchday,
                    'status' => LeagueStatus::Active->value,
                    'parent_league_id' => $league->id,
                    'created_by_user_id' => $league->created_by_user_id,
                ]);

                // Create participants for this split
                foreach ($split['player_ids'] as $playerId) {
                    LeagueParticipant::create([
                        'league_id' => $newLeague->id,
                        'player_id' => $playerId,
                    ]);
                }

                // Generate matchdays
                $this->scheduler->generateMatchdays($newLeague, $newLeague->participants);

                $createdLeagues[] = $newLeague;
            }

            // Mark original league as completed/split
            $league->update(['status' => LeagueStatus::Completed->value]);
        });

        return $createdLeagues;
    }
}
