<?php

namespace App\Http\Controllers\Auth;

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

            // Find existing user by email or create a new one
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $discordUser->getName() ?? $discordUser->getNickname() ?? 'Discord User',
                    'email_verified_at' => now(),
                ]
            );

            // Update user name if it changed
            $newName = $discordUser->getName() ?? $discordUser->getNickname();
            if ($newName && $user->name !== $newName) {
                $user->update(['name' => $newName]);
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
