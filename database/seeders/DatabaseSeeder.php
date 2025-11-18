<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $player = User::firstOrCreate(
            ['email' => 'player@example.com'],
            [
                'name' => 'Player User',
                'password' => 'password',
            ],
        );

        if (! $player->hasRole(RoleName::Spieler->value)) {
            $player->assignRole(RoleName::Spieler->value);
        }
    }
}
