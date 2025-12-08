<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class DiscordController extends Controller
{
    /**
     * Redirect the user to the Discord authentication page.
     */
    public function redirect()
    {
        return Socialite::driver('discord')->redirect();
    }

    /**
     * Obtain the user information from Discord.
     */
    public function callback()
    {
        try {
            $discordUser = Socialite::driver('discord')->user();

            $email = $discordUser->getEmail();

            if (! $email) {
                return redirect()->route('login')->withErrors([
                    'discord' => __('Discord hat keine E-Mail-Adresse zurückgegeben. Bitte stelle sicher, dass dein Discord-Account eine E-Mail-Adresse hat.'),
                ]);
            }

            $discordUsername = $discordUser->getNickname();
            $discordId = $discordUser->getId();

            // If user is already logged in, update their Discord info
            if (Auth::check()) {
                $user = Auth::user();
                
                // Update user name, discord_username and discord_id if they changed
                $newName = $discordUser->getName() ?? $discordUsername;
                $updateData = [];

                if ($newName && $user->name !== $newName) {
                    $updateData['name'] = $newName;
                }

                if ($discordUsername && $user->discord_username !== $discordUsername) {
                    $updateData['discord_username'] = $discordUsername;
                }

                if ($discordId && $user->discord_id !== $discordId) {
                    $updateData['discord_id'] = $discordId;
                }

                if (!empty($updateData)) {
                    $user->update($updateData);
                }

                return redirect()->route('profile.edit')->with('status', 'discord-linked');
            }

            // Find existing user by email or create a new one
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $discordUser->getName() ?? $discordUsername ?? 'Discord User',
                    'email_verified_at' => now(),
                    'discord_username' => $discordUsername,
                    'discord_id' => $discordId,
                ]
            );

            // Assign "Spieler" role to newly created users
            if ($user->wasRecentlyCreated && ! $user->hasRole(RoleName::Spieler->value)) {
                $user->assignRole(RoleName::Spieler->value);
            }

            // Update user name, discord_username and discord_id if they changed
            $newName = $discordUser->getName() ?? $discordUsername;
            $updateData = [];

            if ($newName && $user->name !== $newName) {
                $updateData['name'] = $newName;
            }

            if ($discordUsername && $user->discord_username !== $discordUsername) {
                $updateData['discord_username'] = $discordUsername;
            }

            if ($discordId && $user->discord_id !== $discordId) {
                $updateData['discord_id'] = $discordId;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            Auth::login($user, true);

            return redirect()->intended('/dashboard');
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'discord' => __('Die Discord-Anmeldung ist fehlgeschlagen. Bitte versuche es erneut.'),
            ]);
        }
    }
}
