<?php

namespace App\Actions;

use App\Enums\LeagueStatus;
use App\Models\Season;
use App\Models\SeasonParticipant;
use App\Services\LeagueScheduler;
use Illuminate\Support\Facades\DB;

class SplitSeason
{
    public function __construct(
        protected LeagueScheduler $scheduler
    ) {}

    public function handle(Season $season, array $splits): array
    {
        if ($season->status !== LeagueStatus::Registration->value) {
            throw new \InvalidArgumentException('Nur Saisons im Registrierungsstatus können gesplittet werden.');
        }

        $createdSeasons = [];

        DB::transaction(function () use ($season, $splits, &$createdSeasons) {
            foreach ($splits as $index => $split) {
                $newSeason = Season::create([
                    'league_id' => $season->league_id,
                    'name' => $season->name.' '.($index + 1),
                    'slug' => $season->slug.'-'.($index + 1),
                    'description' => $season->description,
                    'max_players' => $split['max_players'] ?? $season->max_players,
                    'mode' => $season->mode,
                    'variant' => $season->variant,
                    'match_format' => $season->match_format,
                    'registration_deadline' => $season->registration_deadline,
                    'days_per_matchday' => $season->days_per_matchday,
                    'status' => LeagueStatus::Active->value,
                    'parent_season_id' => $season->id,
                    'created_by_user_id' => $season->created_by_user_id,
                ]);

                // Create participants for this split
                foreach ($split['player_ids'] as $playerId) {
                    SeasonParticipant::create([
                        'season_id' => $newSeason->id,
                        'player_id' => $playerId,
                    ]);
                }

                // Generate matchdays
                $this->scheduler->generateMatchdays($newSeason, $newSeason->participants);

                $createdSeasons[] = $newSeason;
            }

            // Mark original season as completed/split
            $season->update(['status' => LeagueStatus::Completed->value]);
        });

        return $createdSeasons;
    }
}
