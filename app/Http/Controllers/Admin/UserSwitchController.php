<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class UserSwitchController extends Controller
{
    /**
     * Switch to another user's account
     */
    public function switch(User $user): RedirectResponse
    {
        $currentUser = Auth::user();

        // Prevent switching to yourself
        if ($currentUser->id === $user->id) {
            return redirect()->route('admin.user-switch.index')
                ->with('error', 'Sie können nicht zu Ihrem eigenen Account wechseln.');
        }

        // Store the original user ID in session
        session(['original_user_id' => $currentUser->id]);

        // Login as the target user
        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', "Sie sind jetzt eingeloggt als {$user->name}");
    }

    /**
     * Stop user switching and return to original account
     */
    public function stop(): RedirectResponse
    {
        $originalUserId = session('original_user_id');

        if (! $originalUserId) {
            return redirect()->route('dashboard')
                ->with('error', 'Kein User-Switch aktiv.');
        }

        $originalUser = User::findOrFail($originalUserId);

        // Remove the session key
        session()->forget('original_user_id');

        // Login back as the original user
        Auth::login($originalUser);

        return redirect()->route('dashboard')
            ->with('success', 'Sie sind wieder mit Ihrem eigenen Account eingeloggt.');
    }
}
