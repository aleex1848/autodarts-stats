# Implementierungs-Prompt: Dart-Spiel Webhook-Processing System

Dieser Prompt beschreibt die vollständige Implementierung eines Laravel-basierten Systems zur Verarbeitung von Dart-Spiel-Webhooks von Autodarts, Speicherung der Spieldaten und Berechnung von Statistiken.

## Übersicht

Das System verarbeitet Webhooks von Autodarts, die Dart-Spiele in Echtzeit übertragen. Es speichert alle Spieldaten (Matches, Legs, Turns, Throws) und berechnet umfangreiche Statistiken für Spieler und Matches.

## Technische Voraussetzungen

- Laravel 12
- PHP 8.2+
- MySQL/MariaDB
- `spatie/laravel-webhook-client` Package (Version 3.4+)
- Queue-System (für asynchrones Webhook-Processing)

## 1. Datenbankstruktur

### 1.1 Tabellen erstellen

#### `players` Tabelle
```php
Schema::create('players', function (Blueprint $table) {
    $table->id();
    $table->uuid('autodarts_user_id')->unique();
    $table->string('name');
    $table->string('email')->nullable();
    $table->string('country', 2)->nullable();
    $table->string('avatar_url')->nullable();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();
    
    $table->index('autodarts_user_id');
});
```

#### `matches` Tabelle
```php
Schema::create('matches', function (Blueprint $table) {
    $table->id();
    $table->uuid('autodarts_match_id')->unique();
    $table->string('variant')->default('X01');
    $table->string('type')->default('Online');
    
    // Settings
    $table->integer('base_score')->default(501);
    $table->string('in_mode')->default('Straight');
    $table->string('out_mode')->default('Straight');
    $table->string('bull_mode')->default('25/50');
    $table->integer('max_rounds')->default(20);
    
    // Status
    $table->foreignId('winner_player_id')->nullable()->constrained('players')->nullOnDelete();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('finished_at')->nullable();
    $table->timestamps();
    
    $table->index('autodarts_match_id');
    $table->index('finished_at');
});
```

#### `match_player` Pivot-Tabelle
```php
Schema::create('match_player', function (Blueprint $table) {
    $table->id();
    $table->foreignId('match_id')->constrained()->cascadeOnDelete();
    $table->foreignId('player_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('player_index')->default(0);
    
    // Stats
    $table->unsignedInteger('legs_won')->default(0);
    $table->unsignedInteger('sets_won')->default(0);
    $table->unsignedTinyInteger('final_position')->nullable();
    $table->decimal('match_average', 6, 2)->nullable();
    $table->decimal('average_until_170', 6, 2)->nullable();
    $table->decimal('first_9_average', 6, 2)->nullable();
    $table->decimal('checkout_rate', 5, 4)->nullable();
    $table->unsignedInteger('checkout_attempts')->nullable();
    $table->unsignedInteger('checkout_hits')->nullable();
    $table->unsignedInteger('best_checkout_points')->nullable();
    $table->unsignedInteger('total_180s')->default(0);
    $table->unsignedInteger('darts_thrown')->nullable();
    $table->unsignedInteger('busted_count')->default(0);
    
    $table->timestamps();
    
    $table->unique(['match_id', 'player_id']);
    $table->index(['match_id', 'player_id']);
});
```

#### `legs` Tabelle
```php
Schema::create('legs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('match_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('leg_number');
    $table->unsignedInteger('set_number')->default(1);
    $table->foreignId('winner_player_id')->nullable()->constrained('players')->nullOnDelete();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('finished_at')->nullable();
    $table->timestamps();
    
    $table->index(['match_id', 'leg_number']);
});
```

#### `leg_player` Pivot-Tabelle
```php
Schema::create('leg_player', function (Blueprint $table) {
    $table->id();
    $table->foreignId('leg_id')->constrained()->cascadeOnDelete();
    $table->foreignId('player_id')->constrained()->cascadeOnDelete();
    $table->decimal('average', 6, 2)->nullable();
    $table->decimal('average_until_170', 6, 2)->nullable();
    $table->decimal('first_9_average', 6, 2)->nullable();
    $table->decimal('checkout_rate', 5, 4)->nullable();
    $table->unsignedInteger('darts_thrown')->nullable();
    $table->unsignedInteger('checkout_attempts')->nullable();
    $table->unsignedInteger('checkout_hits')->nullable();
    $table->unsignedInteger('best_checkout_points')->nullable();
    $table->unsignedInteger('busted_count')->nullable();
    $table->timestamps();
    
    $table->unique(['leg_id', 'player_id']);
    $table->index(['leg_id', 'player_id']);
});
```

#### `turns` Tabelle
```php
Schema::create('turns', function (Blueprint $table) {
    $table->id();
    $table->foreignId('leg_id')->constrained()->cascadeOnDelete();
    $table->foreignId('player_id')->constrained()->cascadeOnDelete();
    $table->uuid('autodarts_turn_id')->unique();
    $table->unsignedInteger('round_number');
    $table->unsignedInteger('turn_number');
    $table->integer('points')->default(0); // Signed für negative Werte (Bull-Off)
    $table->integer('score_after')->nullable(); // Signed für negative Werte
    $table->boolean('busted')->default(false);
    $table->timestamp('started_at')->nullable();
    $table->timestamp('finished_at')->nullable();
    $table->timestamps();
    
    $table->index(['leg_id', 'round_number']);
    $table->index('autodarts_turn_id');
});
```

#### `throws` Tabelle
```php
Schema::create('throws', function (Blueprint $table) {
    $table->id();
    $table->foreignId('turn_id')->constrained()->cascadeOnDelete();
    $table->uuid('autodarts_throw_id');
    $table->foreignId('webhook_call_id')->nullable()->constrained('webhook_calls')->nullOnDelete();
    
    // Throw data
    $table->unsignedTinyInteger('dart_number');
    $table->unsignedTinyInteger('segment_number')->nullable();
    $table->unsignedTinyInteger('multiplier')->default(1);
    $table->unsignedInteger('points')->default(0);
    $table->string('segment_name')->nullable();
    $table->string('segment_bed')->nullable();
    
    // Coordinates
    $table->decimal('coords_x', 10, 8)->nullable();
    $table->decimal('coords_y', 10, 8)->nullable();
    
    // Correction tracking
    $table->boolean('is_corrected')->default(false);
    $table->timestamp('corrected_at')->nullable();
    $table->foreignId('corrected_by_throw_id')->nullable()->constrained('throws')->nullOnDelete();
    
    $table->timestamps();
    
    $table->index(['turn_id', 'dart_number']);
    $table->index('is_corrected');
    $table->index('webhook_call_id');
});
```

#### `bull_offs` Tabelle
```php
Schema::create('bull_offs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('match_id')->constrained()->cascadeOnDelete();
    $table->foreignId('player_id')->constrained()->cascadeOnDelete();
    $table->uuid('autodarts_turn_id')->unique();
    $table->integer('score')->comment('Negative score indicating distance from bull');
    $table->timestamp('thrown_at');
    $table->timestamps();
    
    $table->index('match_id');
    $table->index('player_id');
});
```

## 2. Eloquent Models

### 2.1 Player Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Player extends Model
{
    protected $fillable = [
        'autodarts_user_id',
        'name',
        'email',
        'country',
        'avatar_url',
        'user_id',
    ];

    public function matches(): BelongsToMany
    {
        return $this->belongsToMany(DartMatch::class, 'match_player', 'player_id', 'match_id')
            ->using(MatchPlayer::class)
            ->withPivot([
                'player_index',
                'legs_won',
                'sets_won',
                'final_position',
                'match_average',
                'average_until_170',
                'first_9_average',
                'checkout_rate',
                'checkout_attempts',
                'checkout_hits',
                'best_checkout_points',
                'total_180s',
                'darts_thrown',
                'busted_count',
            ])
            ->withTimestamps();
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }

    public function throws(): HasManyThrough
    {
        return $this->hasManyThrough(DartThrow::class, Turn::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 2.2 DartMatch Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class DartMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'autodarts_match_id',
        'variant',
        'type',
        'base_score',
        'in_mode',
        'out_mode',
        'bull_mode',
        'max_rounds',
        'winner_player_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'match_player', 'match_id', 'player_id')
            ->using(MatchPlayer::class)
            ->withPivot([
                'player_index',
                'legs_won',
                'sets_won',
                'final_position',
                'match_average',
                'average_until_170',
                'first_9_average',
                'checkout_rate',
                'checkout_attempts',
                'checkout_hits',
                'best_checkout_points',
                'total_180s',
                'darts_thrown',
                'busted_count',
            ])
            ->withTimestamps();
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(Leg::class, 'match_id');
    }

    public function bullOffs(): HasMany
    {
        return $this->hasMany(BullOff::class, 'match_id');
    }

    public function scopeFinished(Builder $query): void
    {
        $query->whereNotNull('finished_at');
    }

    public function scopeOngoing(Builder $query): void
    {
        $query->whereNull('finished_at');
    }
}
```

### 2.3 Leg Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Leg extends Model
{
    protected $fillable = [
        'match_id',
        'leg_number',
        'set_number',
        'winner_player_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DartMatch::class, 'match_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }

    public function legPlayers(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'leg_player', 'leg_id', 'player_id')
            ->withPivot([
                'average',
                'average_until_170',
                'first_9_average',
                'checkout_rate',
                'darts_thrown',
                'checkout_attempts',
                'checkout_hits',
                'best_checkout_points',
                'busted_count',
            ])
            ->withTimestamps();
    }
}
```

### 2.4 Turn Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turn extends Model
{
    protected $fillable = [
        'leg_id',
        'player_id',
        'autodarts_turn_id',
        'round_number',
        'turn_number',
        'points',
        'score_after',
        'busted',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'busted' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function leg(): BelongsTo
    {
        return $this->belongsTo(Leg::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function throws(): HasMany
    {
        return $this->hasMany(DartThrow::class, 'turn_id');
    }
}
```

### 2.5 DartThrow Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Spatie\WebhookClient\Models\WebhookCall;

class DartThrow extends Model
{
    protected $table = 'throws';

    protected $fillable = [
        'turn_id',
        'autodarts_throw_id',
        'webhook_call_id',
        'dart_number',
        'segment_number',
        'multiplier',
        'points',
        'segment_name',
        'segment_bed',
        'coords_x',
        'coords_y',
        'is_corrected',
        'corrected_at',
        'corrected_by_throw_id',
    ];

    protected function casts(): array
    {
        return [
            'is_corrected' => 'boolean',
            'corrected_at' => 'datetime',
            'coords_x' => 'decimal:8',
            'coords_y' => 'decimal:8',
        ];
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    public function webhookCall(): BelongsTo
    {
        return $this->belongsTo(WebhookCall::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(DartThrow::class, 'corrected_by_throw_id');
    }

    public function scopeNotCorrected(Builder $query): void
    {
        $query->where('is_corrected', false);
    }

    public function scopeCorrected(Builder $query): void
    {
        $query->where('is_corrected', true);
    }
}
```

### 2.6 BullOff Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BullOff extends Model
{
    protected $fillable = [
        'match_id',
        'player_id',
        'autodarts_turn_id',
        'score',
        'thrown_at',
    ];

    protected function casts(): array
    {
        return [
            'thrown_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DartMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
```

### 2.7 MatchPlayer Pivot Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Pivot
{
    protected $table = 'match_player';

    protected $fillable = [
        'match_id',
        'player_id',
        'player_index',
        'legs_won',
        'sets_won',
        'final_position',
        'match_average',
        'average_until_170',
        'first_9_average',
        'checkout_rate',
        'checkout_attempts',
        'checkout_hits',
        'best_checkout_points',
        'total_180s',
        'darts_thrown',
        'busted_count',
    ];

    protected function casts(): array
    {
        return [
            'match_average' => 'decimal:2',
            'average_until_170' => 'decimal:2',
            'first_9_average' => 'decimal:2',
            'checkout_rate' => 'decimal:4',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(DartMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
```

## 3. Webhook-Konfiguration

### 3.1 Webhook-Client konfigurieren

Installiere das Package:
```bash
composer require spatie/laravel-webhook-client
```

Publiziere die Konfiguration:
```bash
php artisan vendor:publish --provider="Spatie\WebhookClient\WebhookClientServiceProvider"
```

### 3.2 Webhook-Route einrichten

In `routes/api.php`:
```php
Route::webhooks('webhooks')->middleware('auth:sanctum');
```

### 3.3 Webhook-Processing Job erstellen

Erstelle `app/Support/WebhookProcessing.php`:

```php
namespace App\Support;

use App\Models\BullOff;
use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Player;
use App\Models\Turn;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;

class WebhookProcessing extends ProcessWebhookJob
{
    public function handle(): void
    {
        $event = $this->webhookCall->payload['event'];
        Log::debug("event received: $event");

        switch ($event) {
            case 'throw':
                $this->handleThrow();
                break;
            case 'match_state':
                $this->handleMatchState();
                break;
        }
    }

    // Siehe vollständige Implementierung weiter unten
}
```

## 4. Webhook-Event-Verarbeitung

### 4.1 Throw-Event verarbeiten

Das `throw` Event wird für jeden einzelnen Dart-Wurf gesendet. Wichtig: Wenn bereits ein `match_state` Event für das Match existiert, sollten `throw` Events ignoriert werden, da alle Daten bereits aus `match_state` kommen.

**Logik:**
1. Prüfe, ob bereits ein `match_state` Event für dieses Match existiert → wenn ja, ignorieren
2. Match finden oder erstellen
3. Player finden oder erstellen (mit deduplizierter UUID-Logik für Bots)
4. Prüfen, ob es ein Bull-Off Wurf ist
5. Leg finden oder erstellen
6. Turn finden oder erstellen
7. DartThrow erstellen

### 4.2 Match-State-Event verarbeiten

Das `match_state` Event enthält den vollständigen Zustand des Matches und sollte bevorzugt werden.

**Logik:**
1. Match finden oder erstellen
2. Match-Settings aktualisieren
3. **Spieler synchronisieren** (wichtig: zuerst!)
   - Für jeden Spieler im Match:
     - UUID finden oder generieren (für Bots: deterministische UUID)
     - Player finden oder erstellen
     - Match-Player-Pivot aktualisieren mit Statistiken
4. Turns aus `match_state` verarbeiten
5. Legs aktualisieren (Winner bestimmen, Statistiken speichern)
6. Match-Status aktualisieren (finished_at, winner_player_id)
7. Finale Positionen berechnen

### 4.3 Wichtige Details

#### Bot-UUID-Generierung
Bots haben keine echte UUID. Generiere deterministische UUIDs basierend auf dem Bot-Namen:
- "Bot Level 2" → `00000000-0000-0000-0001-000000002`
- Andere Bots → Hash-basierte UUID: `00000000-0000-0000-0002-{hash}`

#### Bull-Off Erkennung
Bull-Off ist der Wurf auf die Bullseye vor Spielbeginn. Kriterien:
- Round 1, Leg 1, Set 1
- Wurf auf Segment 25 (Bull)
- Keine normalen Turns existieren noch
- Bull-Off muss im Match aktiviert sein (erkennbar an negativen gameScores oder bullDistance in stats)

Bull-Off Würfe werden in der `bull_offs` Tabelle gespeichert, nicht als normale Turns.

#### Korrektur-Tracking
Wenn ein DartThrow korrigiert wird (z.B. falsch erkannt), wird:
- Der alte Throw mit `is_corrected = true` markiert
- Ein neuer Throw erstellt
- Die Beziehung über `corrected_by_throw_id` verknüpft

#### Race Conditions
Das System muss robust gegen Race Conditions sein:
- Verwendung von `firstOrCreateWithRetry()` mit Retry-Logik
- Deadlock-Erkennung und Retry
- Transaktionen für atomare Operationen

## 5. Statistik-Berechnungen

### 5.1 Match-Statistiken

Erstelle `app/Support/MatchStatisticsCalculator.php`:

```php
namespace App\Support;

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Turn;

class MatchStatisticsCalculator
{
    public static function calculateAndUpdate(DartMatch $match): void
    {
        $players = $match->players;

        foreach ($players as $player) {
            // 3-Dart Average: (total points / number of darts) * 3
            $throwStats = DartThrow::query()
                ->join('turns', 'throws.turn_id', '=', 'turns.id')
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->where('legs.match_id', $match->id)
                ->where('turns.player_id', $player->id)
                ->where('throws.is_corrected', false)
                ->selectRaw('COUNT(*) as throw_count, SUM(throws.points) as total_points')
                ->first();

            $matchAverage = null;
            if ($throwStats && $throwStats->throw_count > 0) {
                $matchAverage = round(((float) $throwStats->total_points / (int) $throwStats->throw_count) * 3, 2);
            }

            // Checkout Rate: successful checkouts / checkout attempts
            // Checkout attempt = turn where score_after <= 170
            // Successful checkout = turn where score_after = 0
            $checkoutStats = Turn::query()
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->where('legs.match_id', $match->id)
                ->where('turns.player_id', $player->id)
                ->whereNotNull('turns.score_after')
                ->where('turns.score_after', '<=', 170)
                ->selectRaw('COUNT(*) as checkout_attempts, SUM(CASE WHEN turns.score_after = 0 THEN 1 ELSE 0 END) as successful_checkouts')
                ->first();

            $checkoutRate = null;
            if ($checkoutStats && $checkoutStats->checkout_attempts > 0) {
                $rate = (float) $checkoutStats->successful_checkouts / (int) $checkoutStats->checkout_attempts;
                $checkoutRate = round($rate, 4);
            }

            // Count 180s (three triple 20s in one turn)
            $turnsWithTriples = Turn::query()
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->join('throws', 'turns.id', '=', 'throws.turn_id')
                ->where('legs.match_id', $match->id)
                ->where('turns.player_id', $player->id)
                ->where('throws.is_corrected', false)
                ->where('throws.segment_number', 20)
                ->where('throws.multiplier', 3)
                ->selectRaw('turns.id')
                ->groupBy('turns.id')
                ->havingRaw('COUNT(*) = 3')
                ->pluck('turns.id');

            $total180s = $turnsWithTriples->count();

            // Count busted turns
            $bustedCount = Turn::query()
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->where('legs.match_id', $match->id)
                ->where('turns.player_id', $player->id)
                ->where('turns.busted', true)
                ->count();

            // Update match_player pivot
            $match->players()->updateExistingPivot($player->id, [
                'match_average' => $matchAverage,
                'checkout_rate' => $checkoutRate,
                'total_180s' => $total180s,
                'busted_count' => $bustedCount,
            ]);
        }
    }
}
```

### 5.2 Leg-Statistiken

Erstelle `app/Support/LegStatisticsCalculator.php`:

```php
namespace App\Support;

use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Turn;
use Illuminate\Support\Facades\DB;

class LegStatisticsCalculator
{
    public static function calculateAndUpdate(Leg $leg): void
    {
        $players = $leg->match->players;

        foreach ($players as $player) {
            // 3-Dart Average für diesen Leg
            $throwStats = DartThrow::query()
                ->join('turns', 'throws.turn_id', '=', 'turns.id')
                ->where('turns.leg_id', $leg->id)
                ->where('turns.player_id', $player->id)
                ->where('throws.is_corrected', false)
                ->selectRaw('COUNT(*) as throw_count, SUM(throws.points) as total_points')
                ->first();

            $average = null;
            if ($throwStats && $throwStats->throw_count > 0) {
                $average = round(((float) $throwStats->total_points / (int) $throwStats->throw_count) * 3, 2);
            }

            // Checkout Rate für diesen Leg
            $checkoutStats = Turn::query()
                ->where('leg_id', $leg->id)
                ->where('player_id', $player->id)
                ->whereNotNull('score_after')
                ->where('score_after', '<=', 170)
                ->selectRaw('COUNT(*) as checkout_attempts, SUM(CASE WHEN score_after = 0 THEN 1 ELSE 0 END) as successful_checkouts')
                ->first();

            $checkoutRate = null;
            $checkoutAttempts = null;
            $checkoutHits = null;
            if ($checkoutStats && $checkoutStats->checkout_attempts > 0) {
                $rate = (float) $checkoutStats->successful_checkouts / (int) $checkoutStats->checkout_attempts;
                $checkoutRate = round($rate, 4);
                $checkoutAttempts = (int) $checkoutStats->checkout_attempts;
                $checkoutHits = (int) $checkoutStats->successful_checkouts;
            }

            $dartsThrown = (int) ($throwStats->throw_count ?? 0);

            // Count busted turns
            $bustedCount = Turn::query()
                ->where('leg_id', $leg->id)
                ->where('player_id', $player->id)
                ->where('busted', true)
                ->count();

            // Update leg_player pivot
            DB::table('leg_player')->updateOrInsert(
                [
                    'leg_id' => $leg->id,
                    'player_id' => $player->id,
                ],
                [
                    'average' => $average,
                    'checkout_rate' => $checkoutRate,
                    'darts_thrown' => $dartsThrown > 0 ? $dartsThrown : null,
                    'checkout_attempts' => $checkoutAttempts,
                    'checkout_hits' => $checkoutHits,
                    'busted_count' => $bustedCount > 0 ? $bustedCount : null,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }
}
```

## 6. Webhook-Processing Implementierung

Die vollständige `WebhookProcessing` Klasse ist sehr umfangreich. Hier sind die wichtigsten Methoden:

### 6.1 Helper-Methoden

```php
// Retry-Logik für Race Conditions
protected function firstOrCreateWithRetry(string $modelClass, array $attributes, array $values = [], int $maxAttempts = 3): mixed
{
    // Implementierung mit Deadlock-Erkennung und Retry
    // Siehe Original-Code für vollständige Implementierung
}

// Player finden oder erstellen mit deduplizierter UUID-Logik
protected function findOrCreatePlayer(string $userId, string $name, array $additionalValues = []): Player
{
    // 1. Versuche zuerst nach autodarts_user_id zu finden
    // 2. Wenn nicht gefunden, suche nach Name (für Bots)
    // 3. Wenn nicht gefunden, erstelle neuen Player
    // 4. Handle UUID-Konflikte für Bots
}

// Bot-UUID generieren (deterministisch)
protected function generateBotUuid(string $botName): string
{
    // "Bot Level 2" → "00000000-0000-0000-0001-000000002"
    // Andere Bots → Hash-basierte UUID
}

// Bull-Off Erkennung
protected function isBullOffThrow(DartMatch $match, array $data): bool
{
    // Prüfe: Round 1, Leg 1, Set 1, Segment 25, keine normalen Turns
}

// Leg-Winner bestimmen
protected function updateLegs(DartMatch $match, array $matchData): void
{
    // Finde Winner durch score_after = 0
    // Fallback: Letzter Turn im Leg
    // Verifiziere gegen legs_won Statistiken
}
```

### 6.2 Wichtige Verarbeitungslogik

**Throw-Event:**
- Ignoriere, wenn `match_state` bereits existiert
- Erstelle/finde Match, Player, Leg, Turn
- Erstelle DartThrow mit Retry-Logik
- Handle Bull-Off separat

**Match-State-Event:**
- Synchronisiere zuerst alle Spieler
- Verarbeite alle Turns aus `match_state`
- Aktualisiere Legs (Winner, Statistiken)
- Aktualisiere Match-Status
- Berechne finale Positionen

**Korrektur-Handling:**
- Wenn Throw-Daten sich ändern, markiere alten Throw als korrigiert
- Erstelle neuen Throw
- Verknüpfe über `corrected_by_throw_id`

## 7. Webhook-Signatur-Validierung

Erstelle `app/Support/WebhooksSignatureValidator.php`:

```php
namespace App\Support;

use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Illuminate\Http\Request;

class WebhooksSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        // Implementiere Validierung basierend auf Autodarts-Signatur
        // Für Entwicklung: return true;
        return true;
    }
}
```

## 8. Konfiguration

### 8.1 Webhook-Client Config

In `config/webhook-client.php`:

```php
'configs' => [
    'default' => [
        'name' => 'autodarts',
        'signing_secret' => env('WEBHOOK_SECRET'),
        'signature_header_name' => 'Signature',
        'signature_validator' => \App\Support\WebhooksSignatureValidator::class,
        'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
        'webhook_response' => \Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo::class,
        'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => \App\Support\WebhookProcessing::class,
    ],
],
```

### 8.2 Queue-Konfiguration

Webhooks sollten über Queues verarbeitet werden. Stelle sicher, dass:
- `QUEUE_CONNECTION` in `.env` gesetzt ist
- Queue-Worker läuft: `php artisan queue:work`

## 9. Wichtige Besonderheiten

### 9.1 Timestamp-Parsing
Autodarts sendet manchmal ungültige Timestamps (`0001-01-01 00:00:00`). Diese müssen als `null` behandelt werden.

### 9.2 Player-Index vs. Array-Index
- `player_index` in `match_player` ist der Index im `players` Array (0, 1, 2, ...)
- `gameWinner` im Webhook ist ebenfalls ein Array-Index
- Achte auf Verwechslungen zwischen `playerId` (spiel-spezifisch) und `userId` (eindeutig)

### 9.3 Statistik-Extraktion
Wenn `match_state` Statistiken enthält (`stats` Array), verwende diese direkt. Ansonsten berechne sie aus Turns/Throws.

### 9.4 Leg-Winner-Bestimmung
1. Suche Turn mit `score_after = 0` und `finished_at` nicht null
2. Fallback: Turn mit `score_after = 0` ohne `finished_at`
3. Fallback: Turn mit niedrigstem `score_after`
4. Verifiziere gegen `legs_won` Statistiken
5. Fallback: Letzter Turn im Leg (wenn Match beendet)

## 10. Testing

Erstelle Tests für:
- Webhook-Processing (Throw-Event)
- Webhook-Processing (Match-State-Event)
- Bull-Off Erkennung
- Korrektur-Tracking
- Statistik-Berechnungen
- Race Condition Handling
- Bot-UUID-Generierung

## 11. Deployment-Checkliste

- [ ] Datenbank-Migrationen ausführen
- [ ] Webhook-Client konfigurieren
- [ ] Queue-Worker einrichten
- [ ] Webhook-Endpoint in Autodarts konfigurieren
- [ ] Signature-Validierung testen
- [ ] Logging überprüfen
- [ ] Performance testen (viele gleichzeitige Webhooks)

## 12. Wichtige Hinweise

1. **Race Conditions**: Das System muss robust gegen gleichzeitige Webhook-Verarbeitung sein. Verwende Retry-Logik und Transaktionen.

2. **Deduplizierung**: Bots erhalten deterministische UUIDs, um Duplikate zu vermeiden.

3. **Korrekturen**: Das System trackt Korrekturen von Würfen und behält die Historie.

4. **Bull-Off**: Wird separat behandelt und nicht als normaler Turn gespeichert.

5. **Statistiken**: Werden sowohl aus Webhook-Daten extrahiert als auch berechnet, wenn nicht verfügbar.

6. **Performance**: Webhooks sollten asynchron über Queues verarbeitet werden.

Dieser Prompt enthält alle notwendigen Informationen zur Implementierung des Systems. Die vollständige `WebhookProcessing` Klasse ist sehr umfangreich (~1600 Zeilen) und sollte basierend auf dem Original-Code implementiert werden, wobei alle hier beschriebenen Logiken berücksichtigt werden.


