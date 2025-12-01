<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('match.{matchId}', function ($user, $matchId) {
    // Prüfe ob der Benutzer das Match sehen darf
    $match = \App\Models\DartMatch::find($matchId);
    
    if (! $match) {
        return false;
    }

    // Wenn der Benutzer authentifiziert ist, prüfe die Berechtigung
    if ($user) {
        return $user->can('view', $match);
    }

    // Für nicht-authentifizierte Benutzer: nur wenn das Match öffentlich ist
    // (Anpassen je nach Anforderung)
    return true;
});
