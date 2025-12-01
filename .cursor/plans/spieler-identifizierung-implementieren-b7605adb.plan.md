<!-- b7605adb-1984-48bc-87c8-6a101c1b40a5 659439b0-85ec-462a-8b60-05642b9bc4fd -->
# Spieler-Identifizierung implementieren

## Übersicht

Benutzer können ihren Autodarts-Spieler mit ihrem Account verknüpfen, indem sie einen Webhook senden, während der Identifizierungsmodus aktiv ist. Der Webhook wird normal verarbeitet, aber wenn ein `match_state` Event eintrifft und der Benutzer im Identifizierungsmodus ist, wird der Spieler ohne "Bot Level" im Namen automatisch mit dem Benutzer verknüpft.

## Implementierungsschritte

### 1. Datenbank-Migration

- Migration erstellen: `add_is_identifying_to_users_table`
- Spalte `is_identifying` (boolean, default: false) zur `users` Tabelle hinzufügen

### 2. User Model erweitern

- `is_identifying` zum `$fillable` Array in `app/Models/User.php` hinzufügen
- Optional: Cast für `is_identifying` als boolean

### 3. WebhookProcessing erweitern

- In `app/Support/WebhookProcessing.php` die `handleMatchState()` Methode erweitern
- Nach der Spieler-Synchronisierung prüfen, ob der authentifizierte User (aus Sanctum Token) `is_identifying = true` hat
- Wenn ja:
  - Den Spieler finden, der NICHT "Bot Level" im Namen hat (und eine `userId` hat)
  - Diesen Spieler mit `user_id` verknüpfen: `$player->update(['user_id' => $user->id])`
  - Identifizierungsmodus deaktivieren: `$user->update(['is_identifying' => false])`
  - Event für Reverb broadcasten (siehe Schritt 5)

### 4. Volt-Komponente für Identifizierungsseite

- Neue Volt-Komponente erstellen: `resources/views/livewire/settings/identify.php`
- Komponente sollte:
  - Den aktuellen Identifizierungsstatus anzeigen (`$user->is_identifying`)
  - Einen Button "Identifizierung starten" anzeigen, wenn nicht aktiv
  - Einen Button "Identifizierung abbrechen" anzeigen, wenn aktiv
  - Status-Updates über Reverb Events empfangen (siehe Schritt 5)
  - Anweisungen anzeigen, wie der Webhook gesendet werden soll

### 5. Reverb Event für Identifizierungsstatus

- Neues Event erstellen: `app/Events/PlayerIdentified.php`
- Event sollte `ShouldBroadcast` implementieren
- Broadcast auf privaten Channel: `private-user.{user_id}` oder `user.{user_id}`
- Event sollte den Status (`is_identifying`, `player_id` wenn erfolgreich) enthalten
- Event in `WebhookProcessing` nach erfolgreicher Verknüpfung broadcasten

### 6. Route hinzufügen

- In `routes/web.php` neue Route hinzufügen:
  ```php
  Volt::route('settings/identify', 'settings.identify')->name('identify.edit');
  ```


### 7. Settings-Layout erweitern

- In `resources/views/components/settings/layout.blade.php` neuen Navigation-Item hinzufügen:
  ```blade
  <flux:navlist.item :href="route('identify.edit')" wire:navigate>{{ __('Spieler identifizieren') }}</flux:navlist.item>
  ```


### 8. Broadcast Channel Authorization

- In `routes/channels.php` (oder entsprechendem File) privaten Channel für User-Events definieren:
  ```php
  Broadcast::channel('user.{userId}', function ($user, $userId) {
      return (int) $user->id === (int) $userId;
  });
  ```


### 9. Volt-Komponente: Reverb Event Listener

- In der `identify.php` Volt-Komponente `getListeners()` Methode hinzufügen:
  ```php
  public function getListeners(): array
  {
      return [
          "echo-private:user." . Auth::id() . ",.player.identified" => 'refreshStatus',
      ];
  }
  ```


## Wichtige Details

### Webhook-Verarbeitung

- Der Webhook wird normal über `WebhookProcessing` verarbeitet
- Der authentifizierte User wird aus dem Sanctum Token extrahiert (über `$request->user()` im Webhook-Controller)
- Die Logik zur Spieler-Verknüpfung erfolgt NACH der normalen Webhook-Verarbeitung

### Bot-Erkennung

- Spieler mit Namen, die mit "Bot Level" beginnen, werden ignoriert
- Nur Spieler mit einer echten `userId` (nicht Bot-UUID) werden verknüpft

### Fehlerbehandlung

- Wenn kein passender Spieler gefunden wird, sollte der Identifizierungsmodus trotzdem deaktiviert werden (mit Fehlermeldung)
- Wenn mehrere nicht-Bot-Spieler gefunden werden, den ersten nehmen oder Fehler werfen

## Dateien die geändert/erstellt werden

1. `database/migrations/YYYY_MM_DD_HHMMSS_add_is_identifying_to_users_table.php` (neu)
2. `app/Models/User.php` (erweitern)
3. `app/Support/WebhookProcessing.php` (erweitern)
4. `app/Events/PlayerIdentified.php` (neu)
5. `resources/views/livewire/settings/identify.php` (neu)
6. `routes/web.php` (erweitern)
7. `resources/views/components/settings/layout.blade.php` (erweitern)
8. `routes/channels.php` (erweitern oder erstellen, falls nicht vorhanden)

### To-dos

- [ ] Migration erstellen: is_identifying Spalte zur users Tabelle hinzufügen
- [ ] User Model erweitern: is_identifying Feld hinzufügen
- [ ] WebhookProcessing erweitern: Identifizierungslogik in handleMatchState() implementieren
- [ ] PlayerIdentified Event erstellen für Reverb Broadcast
- [ ] Volt-Komponente settings.identify erstellen mit UI und Reverb Listener
- [ ] Route für /settings/identify hinzufügen
- [ ] Settings-Layout Navigation erweitern
- [ ] Broadcast Channel Authorization für user.{userId} Channel hinzufügen