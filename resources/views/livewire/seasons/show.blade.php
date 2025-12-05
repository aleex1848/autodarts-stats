<?php

use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Season;
use App\Services\LeagueStandingsCalculator;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public Season $season;

    #[Url(as: 'activeTab')]
    public string $activeTab = 'overview';
    public ?int $playerId = null;
    public ?int $playingMatchdayId = null;
    public ?string $message = null;
    public bool $success = false;
    public bool $showConfirmModal = false;
    public ?int $selectedMatchdayId = null;
    public bool $isGeneratingAI = false;

    public function mount(Season $season): void
    {
        $this->season = $season->load([
            'league',
            'participants.player',
            'matchdays.fixtures.homePlayer.user',
            'matchdays.fixtures.awayPlayer.user',
            'matchdays.fixtures.dartMatch',
            'matchdays.news' => function ($query) use ($season) {
                $query->where('season_id', $season->id)
                    ->where('is_published', true)
                    ->whereNull('matchday_fixture_id'); // Nur Spieltags-News, keine Fixture-News
            },
            'matchdays.fixtures.news' => function ($query) use ($season) {
                $query->where('season_id', $season->id)
                    ->where('is_published', true);
            },
        ]);
        
        $this->playerId = Auth::user()?->player?->id;
        $this->refreshStatus();        
        
        // Validate activeTab value
        $validTabs = ['overview', 'schedule', 'standings', 'results', 'news'];
        if (!in_array($this->activeTab, $validTabs)) {
            $this->activeTab = 'overview';
        }
    }

    public function getListeners(): array
    {
        return [
            "echo-private:user." . Auth::id() . ",.matchday.game.started" => 'handleMatchdayGameStarted',
        ];
    }

    public function refreshStatus(): void
    {
        $user = Auth::user();
        $this->playingMatchdayId = $user->playing_matchday_id ?? null;
    }

    public function handleMatchdayGameStarted(array $data): void
    {
        $this->playingMatchdayId = $data['playing_matchday_id'] ?? null;
        $this->success = $data['success'] ?? false;
        $this->message = $data['message'] ?? null;

        Auth::user()->refresh();
        $this->refreshStatus();
    }

    public function updatedShowConfirmModal($value): void
    {
        if (! $value) {
            // Modal wurde geschlossen, reset selectedMatchdayId
            $this->selectedMatchdayId = null;
        }
    }

    public function openConfirmModal(int $matchdayId): void
    {
        $matchday = \App\Models\Matchday::with('season')->find($matchdayId);
        
        if (! $matchday || ! $matchday->season) {
            // Wenn keine Season-Einstellungen vorhanden, direkt starten
            $this->startMatchday($matchdayId);
            return;
        }
        
        $season = $matchday->season;
        $hasGameSettings = $season->base_score ||
            $season->in_mode ||
            $season->out_mode ||
            $season->bull_mode ||
            $season->max_rounds ||
            $season->bull_off ||
            $season->match_mode_type;
        
        if (! $hasGameSettings) {
            // Wenn keine Einstellungen vorhanden, direkt starten
            $this->startMatchday($matchdayId);
            return;
        }
        
        $this->selectedMatchdayId = $matchdayId;
        $this->showConfirmModal = true;
    }

    public function startMatchday(int $matchdayId): void
    {
        $user = Auth::user();
        $matchday = \App\Models\Matchday::find($matchdayId);

        if (! $matchday) {
            $this->addError('matchday', 'Spieltag nicht gefunden.');
            $this->showConfirmModal = false;
            $this->selectedMatchdayId = null;
            return;
        }

        // Validate user is participant of the season
        if (! $user->player) {
            $this->addError('matchday', 'Du musst zuerst einen Spieler mit deinem Account verknüpfen.');
            $this->showConfirmModal = false;
            $this->selectedMatchdayId = null;
            return;
        }

        $isParticipant = $this->season->participants()
            ->where('player_id', $user->player->id)
            ->exists();

        if (! $isParticipant) {
            $this->addError('matchday', 'Du bist kein Teilnehmer dieser Saison.');
            $this->showConfirmModal = false;
            $this->selectedMatchdayId = null;
            return;
        }

        // Validate matchday belongs to this season
        if ($matchday->season_id !== $this->season->id) {
            $this->addError('matchday', 'Dieser Spieltag gehört nicht zu dieser Saison.');
            $this->showConfirmModal = false;
            $this->selectedMatchdayId = null;
            return;
        }

        // Validate matchday is relevant (not past)
        if (! $matchday->isCurrentlyActive() && ! $matchday->isUpcoming()) {
            $this->addError('matchday', 'Dieser Spieltag ist bereits vorbei.');
            $this->showConfirmModal = false;
            $this->selectedMatchdayId = null;
            return;
        }

        // If order is required, check if all previous matchdays are complete
        if ($this->season->requiresMatchdayOrder()) {
            $previousMatchdays = $this->season->matchdays()
                ->where('matchday_number', '<', $matchday->matchday_number)
                ->where('is_return_round', $matchday->is_return_round)
                ->get();

            $incompleteMatchdays = $previousMatchdays->filter(function ($previousMatchday) {
                return ! $previousMatchday->isComplete();
            });

            if ($incompleteMatchdays->isNotEmpty()) {
                $incompleteNumbers = $incompleteMatchdays->pluck('matchday_number')->join(', ');
                $this->addError('matchday', "Spieltag {$matchday->matchday_number} kann erst gestartet werden, wenn alle vorherigen Spieltage komplett sind. Noch nicht komplett: {$incompleteNumbers}.");
                $this->showConfirmModal = false;
                $this->selectedMatchdayId = null;
                return;
            }
        }

        // Set playing_matchday_id
        $user->update(['playing_matchday_id' => $matchday->id]);
        $this->playingMatchdayId = $matchday->id;
        $this->message = "Spieltag {$matchday->matchday_number} gestartet. Das nächste eingehende Spiel wird diesem Spieltag zugeordnet.";
        $this->success = true;
        $this->showConfirmModal = false;
        $this->selectedMatchdayId = null;
        
        // Clear any previous errors
        $this->resetErrorBag();
    }

    public function generateAIMatchdayReport(int $matchdayId): void
    {
        $matchday = \App\Models\Matchday::findOrFail($matchdayId);
        
        if (! ($this->season->isAdmin(Auth::user()) || $this->season->league->isAdmin(Auth::user()))) {
            abort(403);
        }

        $this->isGeneratingAI = true;

        try {
            $openAIService = app(OpenAIService::class);
            $content = $openAIService->generateMatchdayReport($matchday);

            $title = $this->extractTitleFromContent($content);
            $excerpt = $this->extractExcerptFromContent($content);

            $spieltagsberichtCategory = NewsCategory::where('slug', 'spieltagsbericht')->first();

            $news = News::create([
                'type' => 'league',
                'title' => $title,
                'slug' => Str::slug($title),
                'content' => $content,
                'excerpt' => $excerpt,
                'league_id' => $this->season->league_id,
                'season_id' => $this->season->id,
                'category_id' => $spieltagsberichtCategory?->id,
                'matchday_id' => $matchdayId,
                'is_published' => true,
                'created_by_user_id' => auth()->id(),
            ]);

            $this->dispatch('notify', title: __('KI-News erfolgreich erstellt'));
        } catch (\Exception $e) {
            $this->dispatch('notify', title: __('Fehler bei der KI-Generierung'), description: $e->getMessage(), variant: 'danger');
        } finally {
            $this->isGeneratingAI = false;
        }
    }

    protected function extractTitleFromContent(string $content): string
    {
        $lines = explode("\n", trim($content));
        $firstLine = trim($lines[0] ?? '');

        $firstLine = preg_replace('/^#+\s*/', '', $firstLine);

        if (strlen($firstLine) > 100) {
            $sentences = preg_split('/([.!?]+)/', $firstLine, 2, PREG_SPLIT_DELIM_CAPTURE);
            $firstLine = trim($sentences[0] . ($sentences[1] ?? ''));
        }

        if (empty($firstLine) || strlen($firstLine) < 10) {
            return __('Spieltagsbericht');
        }

        return $firstLine;
    }

    protected function extractExcerptFromContent(string $content): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($content));
        $firstParagraph = trim($paragraphs[0] ?? '');

        if (strlen($firstParagraph) > 500) {
            $firstParagraph = substr($firstParagraph, 0, 497) . '...';
        }

        return $firstParagraph ?: substr(strip_tags($content), 0, 200);
    }

    public function stopMatchday(): void
    {
        $user = Auth::user();
        $user->update(['playing_matchday_id' => null]);
        $this->playingMatchdayId = null;
        $this->message = 'Spieltag-Modus beendet.';
        $this->success = true;
    }

    public function simulateFixture(int $fixtureId): void
    {
        // Prüfe, ob in Production
        if (app()->environment('production')) {
            $this->dispatch('notify', title: __('Nicht verfügbar'), description: __('Simulation ist in Production nicht verfügbar.'), variant: 'danger');
            return;
        }

        // Prüfe Admin-Berechtigung
        if (! ($this->season->isAdmin(Auth::user()) || $this->season->league->isAdmin(Auth::user()))) {
            $this->dispatch('notify', title: __('Zugriff verweigert'), description: __('Du hast keine Berechtigung, Matches zu simulieren.'), variant: 'danger');
            return;
        }

        $fixture = \App\Models\MatchdayFixture::with(['homePlayer', 'awayPlayer', 'matchday.season'])->find($fixtureId);

        if (! $fixture) {
            $this->dispatch('notify', title: __('Fehler'), description: __('Fixture nicht gefunden.'), variant: 'danger');
            return;
        }

        // Prüfe, ob Fixture scheduled ist
        if ($fixture->status !== 'scheduled') {
            $this->dispatch('notify', title: __('Fehler'), description: __('Nur scheduled Matches können simuliert werden.'), variant: 'danger');
            return;
        }

        // Prüfe, ob Fixture bereits ein Match hat
        if ($fixture->dart_match_id !== null) {
            $this->dispatch('notify', title: __('Fehler'), description: __('Dieses Match wurde bereits gespielt.'), variant: 'danger');
            return;
        }

        try {
            $simulationService = app(\App\Services\MatchSimulationService::class);
            $simulationService->simulateMatch($fixture);

            // Lade die Saison neu, um die Änderungen zu sehen
            $this->season->refresh();
            $this->season->load([
                'league',
                'participants.player',
                'matchdays.fixtures.homePlayer.user',
                'matchdays.fixtures.awayPlayer.user',
                'matchdays.fixtures.dartMatch',
            ]);

            $this->dispatch('notify', title: __('Erfolg'), description: __('Match wurde erfolgreich simuliert.'), variant: 'success');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Match simulation failed', [
                'fixture_id' => $fixtureId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', title: __('Fehler'), description: __('Fehler bei der Simulation: ' . $e->getMessage()), variant: 'danger');
        }
    }

    public function simulateMatchday(int $matchdayId): void
    {
        // Prüfe, ob in Production
        if (app()->environment('production')) {
            $this->dispatch('notify', title: __('Nicht verfügbar'), description: __('Simulation ist in Production nicht verfügbar.'), variant: 'danger');
            return;
        }

        // Prüfe Admin-Berechtigung
        if (! ($this->season->isAdmin(Auth::user()) || $this->season->league->isAdmin(Auth::user()))) {
            $this->dispatch('notify', title: __('Zugriff verweigert'), description: __('Du hast keine Berechtigung, Matches zu simulieren.'), variant: 'danger');
            return;
        }

        $matchday = \App\Models\Matchday::with(['fixtures.homePlayer', 'fixtures.awayPlayer', 'season'])->find($matchdayId);

        if (! $matchday) {
            $this->dispatch('notify', title: __('Fehler'), description: __('Spieltag nicht gefunden.'), variant: 'danger');
            return;
        }

        // Prüfe, ob Spieltag zu dieser Saison gehört
        if ($matchday->season_id !== $this->season->id) {
            $this->dispatch('notify', title: __('Fehler'), description: __('Dieser Spieltag gehört nicht zu dieser Saison.'), variant: 'danger');
            return;
        }

        // Finde alle scheduled Fixtures für diesen Spieltag
        $scheduledFixtures = $matchday->fixtures()
            ->where('status', 'scheduled')
            ->whereNull('dart_match_id')
            ->with(['homePlayer', 'awayPlayer'])
            ->get();

        if ($scheduledFixtures->isEmpty()) {
            $this->dispatch('notify', title: __('Info'), description: __('Keine scheduled Matches für diesen Spieltag gefunden.'), variant: 'info');
            return;
        }

        $simulationService = app(\App\Services\MatchSimulationService::class);
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($scheduledFixtures as $fixture) {
            try {
                $simulationService->simulateMatch($fixture);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Fixture {$fixture->id}: " . $e->getMessage();
                \Illuminate\Support\Facades\Log::error('Match simulation failed', [
                    'fixture_id' => $fixture->id,
                    'matchday_id' => $matchdayId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Lade die Saison neu, um die Änderungen zu sehen
        $this->season->refresh();
        $this->season->load([
            'league',
            'participants.player',
            'matchdays.fixtures.homePlayer.user',
            'matchdays.fixtures.awayPlayer.user',
            'matchdays.fixtures.dartMatch',
        ]);

        if ($errorCount === 0) {
            $this->dispatch('notify', title: __('Erfolg'), description: __('Alle :count Matches wurden erfolgreich simuliert.', ['count' => $successCount]), variant: 'success');
        } else {
            $message = __(':success erfolgreich, :errors Fehler.', [
                'success' => $successCount,
                'errors' => $errorCount,
            ]);
            if (count($errors) <= 3) {
                $message .= ' ' . implode(' ', $errors);
            }
            $this->dispatch('notify', title: __('Teilweise erfolgreich'), description: $message, variant: 'warning');
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        
        // Different logic based on schedule mode
        if ($this->season->matchday_schedule_mode === \App\Enums\MatchdayScheduleMode::UnlimitedNoOrder) {
            // For unlimited no order: get all incomplete matchdays where user has a fixture
            $relevantMatchdays = $this->season->matchdays()
                ->where('is_playoff', false)
                ->orderBy('matchday_number')
                ->get()
                ->filter(function ($matchday) {
                    return ! $matchday->isComplete();
                })
                ->map(function ($matchday) {
                    $fixture = null;
                    if ($this->playerId) {
                        $fixture = \App\Models\MatchdayFixture::where('matchday_id', $matchday->id)
                            ->where(function ($query) {
                                $query->where('home_player_id', $this->playerId)
                                    ->orWhere('away_player_id', $this->playerId);
                            })
                            ->with(['homePlayer.user', 'awayPlayer.user', 'dartMatch'])
                            ->first();
                    }
                    return [
                        'matchday' => $matchday,
                        'fixture' => $fixture,
                    ];
                })
                ->filter(function ($item) {
                    // Only include matchdays where user has a fixture
                    return $item['fixture'] !== null;
                })
                ->values();

            return [
                'matchdays' => $this->season->matchdays()->orderBy('matchday_number')->get(),
                'standings' => app(LeagueStandingsCalculator::class)->calculateStandings($this->season),
                'myParticipant' => $this->playerId
                    ? $this->season->participants()->where('player_id', $this->playerId)->first()
                    : null,
                'myFixtures' => $this->playerId
                    ? $this->season->matchdays()
                        ->with(['fixtures' => function ($query) {
                            $query->where('home_player_id', $this->playerId)
                                ->orWhere('away_player_id', $this->playerId);
                        }])
                        ->get()
                        ->pluck('fixtures')
                        ->flatten()
                    : collect(),
                'nextMatchday' => null,
                'nextMatchdayFixture' => null,
                'relevantMatchdays' => $relevantMatchdays,
            ];
        } else {
            // For timed and unlimited with order: show next relevant matchday
            $nextMatchday = $user ? $this->season->getNextRelevantMatchday($user) : null;
            $nextMatchdayFixture = null;

            if ($nextMatchday && $this->playerId) {
                $nextMatchdayFixture = \App\Models\MatchdayFixture::where('matchday_id', $nextMatchday->id)
                    ->where(function ($query) {
                        $query->where('home_player_id', $this->playerId)
                            ->orWhere('away_player_id', $this->playerId);
                    })
                    ->with(['homePlayer.user', 'awayPlayer.user', 'dartMatch'])
                    ->first();
            }

            return [
                'matchdays' => $this->season->matchdays()->orderBy('matchday_number')->get(),
                'standings' => app(LeagueStandingsCalculator::class)->calculateStandings($this->season),
                'myParticipant' => $this->playerId
                    ? $this->season->participants()->where('player_id', $this->playerId)->first()
                    : null,
                'myFixtures' => $this->playerId
                    ? $this->season->matchdays()
                        ->with(['fixtures' => function ($query) {
                            $query->where('home_player_id', $this->playerId)
                                ->orWhere('away_player_id', $this->playerId);
                        }])
                        ->get()
                        ->pluck('fixtures')
                        ->flatten()
                    : collect(),
                'nextMatchday' => $nextMatchday,
                'nextMatchdayFixture' => $nextMatchdayFixture,
                'relevantMatchdays' => collect(),
            ];
        }
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $season->name }}</flux:heading>
            <flux:subheading>
                {{ $season->description ?? __('Saison-Details') }}
                @if ($season->league)
                    - <a href="{{ route('leagues.show', $season->league) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">{{ $season->league->name }}</a>
                @endif
            </flux:subheading>
        </div>

        <flux:button variant="ghost" :href="route('leagues.show', $season->league)" wire:navigate>
            {{ __('Zurück zur Liga') }}
        </flux:button>
    </div>

    @if ($season->getBannerPath())
        <div class="overflow-hidden rounded-xl pointer-events-none">
            <img src="{{ Storage::url($season->getBannerPath()) }}" alt="{{ $season->name }}" class="w-full h-auto max-h-64 object-cover" />
        </div>
    @endif

    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700 relative z-20">
        <button
            wire:click="$set('activeTab', 'overview')"
            class="px-4 py-2 text-sm font-medium transition-colors relative z-10 {{ $activeTab === 'overview' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Übersicht') }}
        </button>

        <button
            wire:click="$set('activeTab', 'schedule')"
            class="px-4 py-2 text-sm font-medium transition-colors relative z-10 {{ $activeTab === 'schedule' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Spielplan') }}
        </button>

        <button
            wire:click="$set('activeTab', 'standings')"
            class="px-4 py-2 text-sm font-medium transition-colors relative z-10 {{ $activeTab === 'standings' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Tabelle') }}
        </button>

        <button
            wire:click="$set('activeTab', 'results')"
            class="px-4 py-2 text-sm font-medium transition-colors relative z-10 {{ $activeTab === 'results' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Ergebnisse') }}
        </button>

        @if ($season->isAdmin(Auth::user()) || $season->league->isAdmin(Auth::user()))
            <button
                wire:click="$set('activeTab', 'news')"
                class="px-4 py-2 text-sm font-medium transition-colors relative z-10 {{ $activeTab === 'news' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
            >
                {{ __('News') }}
            </button>
        @endif

    </div>

    @if ($activeTab === 'overview')
        <div class="space-y-6">
            @if ($nextMatchday || $relevantMatchdays->isNotEmpty())
                {{-- Grid mit Saison-Informationen und Schnelleinstieg --}}
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Saison-Informationen --}}
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="lg" class="mb-4">{{ __('Saison-Informationen') }}</flux:heading>

                        <dl class="grid gap-4 md:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                                <dd class="mt-1">
                                    <flux:badge :variant="match($season->status) {
                                        'registration' => 'primary',
                                        'active' => 'success',
                                        'completed' => 'subtle',
                                        'cancelled' => 'danger',
                                        default => 'subtle'
                                    }">
                                        {{ __(ucfirst($season->status)) }}
                                    </flux:badge>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Teilnehmer') }}</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $season->participants->count() }} / {{ $season->max_players }} {{ __('Spieler') }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Modus') }}</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ match($season->mode) {
                                        'single_round' => __('Nur Hinrunde'),
                                        'double_round' => __('Hin & Rückrunde'),
                                        default => $season->mode
                                    } }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Spielformat') }}</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ match($season->match_format) {
                                        'best_of_3' => 'Best of 3',
                                        'best_of_5' => 'Best of 5',
                                        default => $season->match_format
                                    } }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Schnelleinstieg --}}
                @php
                    $hasUncompletedMatchday = false;
                    if ($nextMatchday) {
                        $fixtureCompleted = $nextMatchdayFixture && ($nextMatchdayFixture->dart_match_id !== null || $nextMatchdayFixture->status === 'completed');
                        if (!$fixtureCompleted) {
                            $hasUncompletedMatchday = true;
                        }
                    } else {
                        $hasUncompletedMatchday = $relevantMatchdays->contains(function ($item) {
                            $fixture = $item['fixture'];
                            return !($fixture && ($fixture->dart_match_id !== null || $fixture->status === 'completed'));
                        });
                    }
                @endphp
                <div class="rounded-xl border {{ $hasUncompletedMatchday ? 'border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 dark:border-green-800 dark:from-green-950/30 dark:to-emerald-950/30' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' }} p-6 shadow-sm">
                    <flux:heading size="lg" class="mb-4 {{ $hasUncompletedMatchday ? 'text-green-900 dark:text-green-100' : 'text-neutral-900 dark:text-neutral-100' }}">{{ __('Schnelleinstieg') }}</flux:heading>
                    @if ($hasUncompletedMatchday)
                        <p class="mb-4 text-sm text-green-700 dark:text-green-300">{{ __('Starte dein Spiel für diesen Spieltag') }}</p>
                    @endif

                    @if ($message)
                        <div class="mb-4 rounded-lg border p-3 {{ $success ? 'border-green-300 bg-green-100 dark:border-green-700 dark:bg-green-900/30' : 'border-red-300 bg-red-100 dark:border-red-700 dark:bg-red-900/30' }}">
                            <p class="text-sm font-medium {{ $success ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }}">
                                {{ $message }}
                            </p>
                        </div>
                    @endif
                    
                    @error('matchday')
                        <div class="mb-4 rounded-lg border border-red-300 bg-red-100 p-3 dark:border-red-700 dark:bg-red-900/30">
                            <p class="text-sm font-medium text-red-900 dark:text-red-100">
                                {{ $message }}
                            </p>
                        </div>
                    @enderror

                    <div class="space-y-4">
                        @if ($nextMatchday)
                            @php
                                $isActive = $nextMatchday->isCurrentlyActive();
                                $isPlaying = $playingMatchdayId === $nextMatchday->id;
                                $fixtureCompleted = $nextMatchdayFixture && ($nextMatchdayFixture->dart_match_id !== null || $nextMatchdayFixture->status === 'completed');
                            @endphp

                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <a href="{{ route('seasons.show', $season) }}" wire:navigate>
                                        <flux:badge size="sm" variant="subtle" class="hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer">
                                            {{ __('Spieltag :number', ['number' => $nextMatchday->matchday_number]) }}
                                        </flux:badge>
                                    </a>
                                    @if ($isActive)
                                        <flux:badge size="sm" variant="success">
                                            {{ __('Aktiv') }}
                                        </flux:badge>
                                    @elseif ($nextMatchday->isUpcoming())
                                        <flux:badge size="sm" variant="primary">
                                            {{ __('Bevorstehend') }}
                                        </flux:badge>
                                    @endif
                                </div>
                                
                                @if ($season->league)
                                    <a href="{{ route('leagues.show', $season->league) }}" wire:navigate class="block mt-1">
                                        <p class="text-sm font-medium text-neutral-700 dark:text-neutral-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            {{ $season->league->name }}
                                        </p>
                                    </a>
                                @endif

                                @if ($nextMatchdayFixture)
                                    <div class="mt-2 flex items-center gap-2 text-sm">
                                        <span class="font-medium {{ $nextMatchdayFixture->home_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                            @if ($nextMatchdayFixture->homePlayer?->user)
                                                <a href="{{ route('users.show', $nextMatchdayFixture->homePlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                    {{ $nextMatchdayFixture->homePlayer->name }}
                                                </a>
                                            @else
                                                {{ $nextMatchdayFixture->homePlayer->name }}
                                            @endif
                                        </span>
                                        <span class="text-zinc-500 dark:text-zinc-400">vs</span>
                                        <span class="font-medium {{ $nextMatchdayFixture->away_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                            @if ($nextMatchdayFixture->awayPlayer?->user)
                                                <a href="{{ route('users.show', $nextMatchdayFixture->awayPlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                    {{ $nextMatchdayFixture->awayPlayer->name }}
                                                </a>
                                            @else
                                                {{ $nextMatchdayFixture->awayPlayer->name }}
                                            @endif
                                        </span>
                                    </div>
                                @endif

                                @if ($nextMatchday->deadline_at)
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Deadline: :date', ['date' => $nextMatchday->deadline_at->format('d.m.Y H:i')]) }}
                                    </p>
                                @endif

                                @if ($isPlaying)
                                    <p class="mt-2 text-sm font-medium text-blue-600 dark:text-blue-400">
                                        {{ __('Warte auf eingehendes Spiel...') }}
                                    </p>
                                @endif

                                @if (!$fixtureCompleted)
                                    <div class="flex gap-2 mt-4">
                                        @if ($isPlaying)
                                            <flux:button
                                                variant="danger"
                                                wire:click="stopMatchday"
                                                wire:loading.attr="disabled"
                                            >
                                                {{ __('Abbrechen') }}
                                            </flux:button>
                                        @else
                                            <flux:button
                                                variant="primary"
                                                color="{{ $isActive ? 'green' : 'blue' }}"
                                                wire:click="openConfirmModal({{ $nextMatchday->id }})"
                                                wire:loading.attr="disabled"
                                            >
                                                {{ $isActive ? __('Jetzt spielen') : __('Spiel starten') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                @elseif ($nextMatchdayFixture->dartMatch)
                                    <div class="flex gap-2 mt-4">
                                        <flux:button
                                            variant="outline"
                                            :href="route('matches.show', $nextMatchdayFixture->dartMatch)"
                                            wire:navigate
                                        >
                                            {{ __('Match ansehen') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @elseif ($relevantMatchdays->isNotEmpty())
                            @foreach ($relevantMatchdays as $item)
                                @php
                                    $matchday = $item['matchday'];
                                    $fixture = $item['fixture'];
                                    $isActive = $matchday->isCurrentlyActive();
                                    $isPlaying = $playingMatchdayId === $matchday->id;
                                    $fixtureCompleted = $fixture && ($fixture->dart_match_id !== null || $fixture->status === 'completed');
                                @endphp

                                <div class="rounded-xl border-2 {{ $fixtureCompleted ? 'border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-zinc-800' : 'border-green-200 bg-white dark:border-green-800 dark:bg-zinc-900' }} p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-2">
                                                <flux:badge size="sm" variant="subtle">
                                                    {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }}
                                                </flux:badge>
                                                @if ($isActive)
                                                    <flux:badge size="sm" variant="success">
                                                        {{ __('Aktiv') }}
                                                    </flux:badge>
                                                @endif
                                            </div>

                                            @if ($fixture)
                                                <div class="mt-2 flex items-center gap-2 text-sm">
                                                    <span class="font-medium {{ $fixture->home_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                                        @if ($fixture->homePlayer?->user)
                                                            <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                                {{ $fixture->homePlayer->name }}
                                                            </a>
                                                        @else
                                                            {{ $fixture->homePlayer->name }}
                                                        @endif
                                                    </span>
                                                    <span class="text-zinc-500 dark:text-zinc-400">vs</span>
                                                    <span class="font-medium {{ $fixture->away_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                                        @if ($fixture->awayPlayer?->user)
                                                            <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                                {{ $fixture->awayPlayer->name }}
                                                            </a>
                                                        @else
                                                            {{ $fixture->awayPlayer->name }}
                                                        @endif
                                                    </span>
                                                </div>
                                            @endif

                                            @if ($isPlaying)
                                                <p class="mt-2 text-sm font-medium text-blue-600 dark:text-blue-400">
                                                    {{ __('Warte auf eingehendes Spiel...') }}
                                                </p>
                                            @endif
                                        </div>

                                        @if (!$fixtureCompleted)
                                            <div class="flex flex-col gap-2">
                                                @if ($isPlaying)
                                                    <flux:button
                                                        variant="danger"
                                                        size="sm"
                                                        wire:click="stopMatchday"
                                                        wire:loading.attr="disabled"
                                                    >
                                                        {{ __('Abbrechen') }}
                                                    </flux:button>
                                                @else
                                                    <flux:button
                                                        variant="primary"
                                                        size="sm"
                                                        color="green"
                                                        wire:click="openConfirmModal({{ $matchday->id }})"
                                                        wire:loading.attr="disabled"
                                                    >
                                                        {{ __('Jetzt spielen') }}
                                                    </flux:button>
                                                @endif
                                            </div>
                                        @elseif ($fixture->dartMatch)
                                            <div class="flex flex-col gap-2">
                                                <flux:button
                                                    variant="outline"
                                                    size="sm"
                                                    :href="route('matches.show', $fixture->dartMatch)"
                                                    wire:navigate
                                                >
                                                    {{ __('Match ansehen') }}
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                </div>
            @else
                {{-- Nur Saison-Informationen, wenn kein Spieltag ansteht --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg" class="mb-4">{{ __('Saison-Informationen') }}</flux:heading>

                    <dl class="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                            <dd class="mt-1">
                                <flux:badge :variant="match($season->status) {
                                    'registration' => 'primary',
                                    'active' => 'success',
                                    'completed' => 'subtle',
                                    'cancelled' => 'danger',
                                    default => 'subtle'
                                }">
                                    {{ __(ucfirst($season->status)) }}
                                </flux:badge>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Teilnehmer') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $season->participants->count() }} / {{ $season->max_players }} {{ __('Spieler') }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Modus') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ match($season->mode) {
                                    'single_round' => __('Nur Hinrunde'),
                                    'double_round' => __('Hin & Rückrunde'),
                                    default => $season->mode
                                } }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Spielformat') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ match($season->match_format) {
                                    'best_of_3' => 'Best of 3',
                                    'best_of_5' => 'Best of 5',
                                    default => $season->match_format
                                } }}
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif

            {{-- Season Standings --}}
            @if ($myParticipant)
                <livewire:season-standings-single :season="$season" />
            @endif
        </div>
    @endif

    @if ($activeTab === 'schedule')
        <div class="space-y-4">
            @forelse ($matchdays as $matchday)
                <div wire:key="matchday-{{ $matchday->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="md">
                                {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }}
                                @if ($matchday->is_return_round)
                                    <span class="text-sm font-normal text-zinc-500">({{ __('Rückrunde') }})</span>
                                @endif
                            </flux:heading>
                            @if ($matchday->deadline_at)
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Deadline: :date', ['date' => $matchday->deadline_at->format('d.m.Y')]) }}
                                </p>
                            @endif
                        </div>
                        @if ($season->isAdmin(Auth::user()) || $season->league->isAdmin(Auth::user()))
                            <div class="flex gap-2">
                                @php
                                    // Prüfe nur News, die speziell für den Spieltag sind (ohne Fixture)
                                    $matchdayNews = $matchday->news->firstWhere('matchday_fixture_id', null);
                                    $scheduledFixturesCount = $matchday->fixtures->where('status', 'scheduled')->whereNull('dart_match_id')->count();
                                    $isProduction = app()->environment('production');
                                @endphp
                                @if ($scheduledFixturesCount > 0)
                                    @if ($isProduction)
                                        <flux:button
                                            size="sm"
                                            variant="outline"
                                            icon="play"
                                            disabled
                                            title="{{ __('Simulation ist in Production nicht verfügbar') }}"
                                        >
                                            {{ __('Alle simulieren (:count)', ['count' => $scheduledFixturesCount]) }}
                                        </flux:button>
                                    @else
                                        <flux:button
                                            size="sm"
                                            variant="outline"
                                            icon="play"
                                            wire:click="simulateMatchday({{ $matchday->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="simulateMatchday({{ $matchday->id }})"
                                        >
                                            <span wire:loading.remove wire:target="simulateMatchday({{ $matchday->id }})">
                                                {{ __('Alle simulieren (:count)', ['count' => $scheduledFixturesCount]) }}
                                            </span>
                                            <span wire:loading wire:target="simulateMatchday({{ $matchday->id }})">
                                                {{ __('Simuliere...') }}
                                            </span>
                                        </flux:button>
                                    @endif
                                @endif
                                @if ($matchdayNews)
                                    <flux:button
                                        size="sm"
                                        variant="outline"
                                        icon="document-text"
                                        :href="route('news.show', $matchdayNews)"
                                        wire:navigate
                                    >
                                        {{ __('News anzeigen') }}
                                    </flux:button>
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="outline"
                                        icon="document-plus"
                                        :href="route('seasons.show', $season) . '?activeTab=news&createNews=1&urlMatchdayId=' . $matchday->id"
                                        wire:navigate
                                    >
                                        {{ __('News erstellen') }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="outline"
                                        icon="sparkles"
                                        wire:click="generateAIMatchdayReport({{ $matchday->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="generateAIMatchdayReport({{ $matchday->id }})"
                                    >
                                        <span wire:loading.remove wire:target="generateAIMatchdayReport({{ $matchday->id }})">
                                            {{ __('KI News erstellen') }}
                                        </span>
                                        <span wire:loading wire:target="generateAIMatchdayReport({{ $matchday->id }})">
                                            {{ __('Generiere...') }}
                                        </span>
                                    </flux:button>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="space-y-2">
                        @forelse ($matchday->fixtures as $fixture)
                            <div wire:key="fixture-{{ $fixture->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700 {{ ($fixture->home_player_id == $playerId || $fixture->away_player_id == $playerId) ? 'bg-blue-50 dark:bg-blue-950' : '' }}">
                                <div class="flex-1">
                                    <span class="font-medium {{ $fixture->home_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : '' }}">
                                        @if ($fixture->homePlayer?->user)
                                            <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                {{ $fixture->homePlayer->name }}
                                            </a>
                                        @else
                                            {{ $fixture->homePlayer->name }}
                                        @endif
                                    </span>
                                    <span class="mx-2 text-zinc-500">vs</span>
                                    <span class="font-medium {{ $fixture->away_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : '' }}">
                                        @if ($fixture->awayPlayer?->user)
                                            <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                {{ $fixture->awayPlayer->name }}
                                            </a>
                                        @else
                                            {{ $fixture->awayPlayer->name }}
                                        @endif
                                    </span>
                                </div>

                                <div class="flex items-center gap-4">
                                    @if ($fixture->status === 'completed')
                                        <span class="text-sm font-medium">
                                            {{ $fixture->home_legs_won }} : {{ $fixture->away_legs_won }}
                                        </span>
                                        @if ($fixture->dartMatch)
                                            <flux:button
                                                size="xs"
                                                variant="outline"
                                                :href="route('matches.show', $fixture->dartMatch)"
                                                wire:navigate
                                            >
                                                {{ __('Match') }}
                                            </flux:button>
                                        @endif
                                    @else
                                        <flux:badge size="sm" :variant="$fixture->status === 'overdue' ? 'danger' : 'subtle'">
                                            {{ __(ucfirst($fixture->status)) }}
                                        </flux:badge>
                                        @if ($fixture->status === 'scheduled' && ($season->isAdmin(Auth::user()) || $season->league->isAdmin(Auth::user())))
                                            @if (app()->environment('production'))
                                                <flux:button
                                                    size="xs"
                                                    variant="outline"
                                                    disabled
                                                    title="{{ __('Simulation ist in Production nicht verfügbar') }}"
                                                >
                                                    {{ __('Simulieren') }}
                                                </flux:button>
                                            @else
                                                <flux:button
                                                    size="xs"
                                                    variant="outline"
                                                    wire:click="simulateFixture({{ $fixture->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="simulateFixture({{ $fixture->id }})"
                                                >
                                                    <span wire:loading.remove wire:target="simulateFixture({{ $fixture->id }})">
                                                        {{ __('Simulieren') }}
                                                    </span>
                                                    <span wire:loading wire:target="simulateFixture({{ $fixture->id }})">
                                                        {{ __('Simuliere...') }}
                                                    </span>
                                                </flux:button>
                                            @endif
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">{{ __('Keine Spiele geplant') }}</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Spieltage verfügbar.') }}
                </flux:callout>
            @endforelse
        </div>
    @endif

    @if ($activeTab === 'standings')
        <div class="space-y-4">
            @if ($standings->count() > 0)
                <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Pos.') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Spieler') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Pkt.') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Sp.') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('S') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('N') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Legs') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($standings as $standing)
                                <tr wire:key="standing-{{ $standing->id }}" class="{{ $standing->player_id == $playerId ? 'bg-blue-50 dark:bg-blue-950' : '' }}">
                                    <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->final_position ?? $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->player->name }}
                                        @if ($standing->player_id == $playerId)
                                            <flux:badge size="sm" variant="primary" class="ml-2">{{ __('Du') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->points }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_played }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_won }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_lost }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->legs_won }}:{{ $standing->legs_lost }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Tabelle verfügbar.') }}
                </flux:callout>
            @endif
        </div>
    @endif

    @if ($activeTab === 'results')
        <div class="space-y-4">
            @php
                $completedFixtures = $matchdays->pluck('fixtures')->flatten()->filter(fn($f) => $f->status === 'completed');
            @endphp

            @forelse ($completedFixtures as $fixture)
                <div wire:key="result-{{ $fixture->id }}" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Spieltag :number', ['number' => $fixture->matchday->matchday_number]) }}
                            </div>
                            <div class="mt-1 font-medium">
                                <span class="{{ $fixture->winner_player_id == $fixture->home_player_id ? 'text-green-600 dark:text-green-400' : '' }}">
                                    @if ($fixture->homePlayer?->user)
                                        <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                            {{ $fixture->homePlayer->name }}
                                        </a>
                                    @else
                                        {{ $fixture->homePlayer->name }}
                                    @endif
                                </span>
                                <span class="mx-2">vs</span>
                                <span class="{{ $fixture->winner_player_id == $fixture->away_player_id ? 'text-green-600 dark:text-green-400' : '' }}">
                                    @if ($fixture->awayPlayer?->user)
                                        <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                            {{ $fixture->awayPlayer->name }}
                                        </a>
                                    @else
                                        {{ $fixture->awayPlayer->name }}
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-xl font-bold">
                                    {{ $fixture->home_legs_won }} : {{ $fixture->away_legs_won }}
                                </div>
                                @if ($fixture->played_at)
                                    <div class="text-xs text-zinc-500">
                                        {{ $fixture->played_at->format('d.m.Y') }}
                                    </div>
                                @endif
                            </div>

                            @if ($fixture->dartMatch)
                                <flux:button
                                    size="xs"
                                    variant="outline"
                                    :href="route('matches.show', $fixture->dartMatch)"
                                    wire:navigate
                                >
                                    {{ __('Details') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Ergebnisse vorhanden.') }}
                </flux:callout>
            @endforelse
        </div>
    @endif

    {{-- Confirmation Modal mit Season-Einstellungen --}}
    @if ($selectedMatchdayId && $showConfirmModal)
        @php
            $selectedMatchday = \App\Models\Matchday::with('season')->find($selectedMatchdayId);
            $selectedSeason = $selectedMatchday?->season ?? $this->season;
            $hasGameSettings = $selectedSeason && (
                $selectedSeason->base_score ||
                $selectedSeason->in_mode ||
                $selectedSeason->out_mode ||
                $selectedSeason->bull_mode ||
                $selectedSeason->max_rounds ||
                $selectedSeason->bull_off ||
                $selectedSeason->match_mode_type
            );
        @endphp
        
        @if ($selectedSeason && $hasGameSettings)
            <flux:modal wire:model.self="showConfirmModal" class="min-w-[32rem]">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Spiel starten') }}</flux:heading>
                        <flux:text class="mt-2">
                            {{ __('Bitte bestätige, dass du die folgenden Spieleinstellungen für diese Saison kennst:') }}
                        </flux:text>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:heading size="sm" class="mb-3">{{ __('Geforderte Spieleinstellungen') }}</flux:heading>
                        <dl class="space-y-2 text-sm">
                            @if ($selectedSeason->base_score)
                                <div class="flex justify-between">
                                    <dt class="font-medium text-zinc-600 dark:text-zinc-400">{{ __('Base Score') }}</dt>
                                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $selectedSeason->base_score }}</dd>
                                </div>
                            @endif

                            @if ($selectedSeason->in_mode)
                                <div class="flex justify-between">
                                    <dt class="font-medium text-zinc-600 dark:text-zinc-400">{{ __('In Mode') }}</dt>
                                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $selectedSeason->in_mode }}</dd>
                                </div>
                            @endif

                            @if ($selectedSeason->out_mode)
                                <div class="flex justify-between">
                                    <dt class="font-medium text-zinc-600 dark:text-zinc-400">{{ __('Out Mode') }}</dt>
                                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $selectedSeason->out_mode }}</dd>
                                </div>
                            @endif

                            @if ($selectedSeason->bull_mode)
                                <div class="flex justify-between">
                                    <dt class="font-medium text-zinc-600 dark:text-zinc-400">{{ __('Bull Mode') }}</dt>
                                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $selectedSeason->bull_mode }}</dd>
                                </div>
                            @endif

                            @if ($selectedSeason->max_rounds)
                                <div class="flex justify-between">
                                    <dt class="font-medium text-zinc-600 dark:text-zinc-400">{{ __('Max Rounds') }}</dt>
                                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $selectedSeason->max_rounds }}</dd>
                                </div>
                            @endif

                            @if ($selectedSeason->bull_off)
                                <div class="flex justify-between">
                                    <dt class="font-medium text-zinc-600 dark:text-zinc-400">{{ __('Bull-Off') }}</dt>
                                    <dd class="text-zinc-900 dark:text-zinc-100">{{ $selectedSeason->bull_off }}</dd>
                                </div>
                            @endif

                            @if ($selectedSeason->match_mode_type)
                                <div class="flex justify-between">
                                    <dt class="font-medium text-zinc-600 dark:text-zinc-400">{{ __('Match Mode') }}</dt>
                                    <dd class="text-zinc-900 dark:text-zinc-100">
                                        @if ($selectedSeason->match_mode_type === 'Legs' && $selectedSeason->match_mode_legs_count)
                                            {{ __('Legs - First to :count leg', ['count' => $selectedSeason->match_mode_legs_count]) }}
                                        @elseif ($selectedSeason->match_mode_type === 'Sets' && $selectedSeason->match_mode_sets_count)
                                            {{ __('Sets - First to :count sets', ['count' => $selectedSeason->match_mode_sets_count]) }}
                                            @if ($selectedSeason->match_mode_legs_count)
                                                · {{ __('First to :count leg', ['count' => $selectedSeason->match_mode_legs_count]) }}
                                            @endif
                                        @else
                                            {{ $selectedSeason->match_mode_type }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                        </flux:modal.close>
                        <flux:button
                            variant="primary"
                            color="green"
                            wire:click="startMatchday({{ $selectedMatchdayId }})"
                            wire:loading.attr="disabled"
                            icon="play"
                        >
                            {{ __('Spiel starten') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    @endif

    @if ($activeTab === 'news' && ($season->isAdmin(Auth::user()) || $season->league->isAdmin(Auth::user())))
        <livewire:seasons.news :season="$season" />
    @endif
</section>
