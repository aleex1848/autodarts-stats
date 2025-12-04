<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    /**
     * Get the number of latest matches to display.
     */
    public static function getLatestMatchesCount(): int
    {
        $value = Setting::get('dashboard.latest_matches_count', '5');

        return (int) $value;
    }

    /**
     * Set the number of latest matches to display.
     */
    public static function setLatestMatchesCount(int $count): void
    {
        Setting::set('dashboard.latest_matches_count', (string) $count);
    }

    /**
     * Get the number of running matches to display.
     */
    public static function getRunningMatchesCount(): int
    {
        $value = Setting::get('dashboard.running_matches_count', '5');

        return (int) $value;
    }

    /**
     * Set the number of running matches to display.
     */
    public static function setRunningMatchesCount(int $count): void
    {
        Setting::set('dashboard.running_matches_count', (string) $count);
    }

    /**
     * Get the number of upcoming matches to display.
     */
    public static function getUpcomingMatchesCount(): int
    {
        $value = Setting::get('dashboard.upcoming_matches_count', '5');

        return (int) $value;
    }

    /**
     * Set the number of upcoming matches to display.
     */
    public static function setUpcomingMatchesCount(int $count): void
    {
        Setting::set('dashboard.upcoming_matches_count', (string) $count);
    }

    /**
     * Get the number of platform news to display.
     */
    public static function getPlatformNewsCount(): int
    {
        $value = Setting::get('dashboard.platform_news_count', '5');

        return (int) $value;
    }

    /**
     * Set the number of platform news to display.
     */
    public static function setPlatformNewsCount(int $count): void
    {
        Setting::set('dashboard.platform_news_count', (string) $count);
    }

    /**
     * Get the number of league news to display.
     */
    public static function getLeagueNewsCount(): int
    {
        $value = Setting::get('dashboard.league_news_count', '5');

        return (int) $value;
    }

    /**
     * Set the number of league news to display.
     */
    public static function setLeagueNewsCount(int $count): void
    {
        Setting::set('dashboard.league_news_count', (string) $count);
    }
}
