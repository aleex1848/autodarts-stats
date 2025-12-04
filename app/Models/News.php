<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class News extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'title',
        'slug',
        'content',
        'excerpt',
        'category_id',
        'league_id',
        'season_id',
        'matchday_id',
        'matchday_fixture_id',
        'created_by_user_id',
        'published_at',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(MatchdayFixture::class, 'matchday_fixture_id');
    }

    /**
     * Get the match through the fixture relationship.
     */
    public function match(): ?DartMatch
    {
        return $this->fixture?->dartMatch;
    }

    /**
     * Check if this is platform news.
     */
    public function isPlatformNews(): bool
    {
        return $this->type === 'platform';
    }

    /**
     * Check if this is league news.
     */
    public function isLeagueNews(): bool
    {
        return $this->type === 'league';
    }

    /**
     * Check if the user can view this news.
     */
    public function canBeViewedBy(User $user): bool
    {
        // Platform news can be viewed by everyone
        if ($this->isPlatformNews()) {
            return true;
        }

        // League news: user must be participant of the league/season
        if ($this->isLeagueNews()) {
            // If season-specific, check if user is participant of that season
            if ($this->season_id) {
                if (! $user->player) {
                    return false;
                }

                return $this->season->participants()
                    ->where('player_id', $user->player->id)
                    ->exists();
            }

            // If general league news (no season), check if user is participant of any season of that league
            if ($this->league_id) {
                if (! $user->player) {
                    return false;
                }

                return $this->league->seasons()
                    ->whereHas('participants', function ($query) use ($user) {
                        $query->where('player_id', $user->player->id);
                    })
                    ->exists();
            }
        }

        return false;
    }

    /**
     * Scope a query to only include published news.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope a query to only include platform news.
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->where('type', 'platform');
    }

    /**
     * Scope a query to only include league news.
     */
    public function scopeLeague(Builder $query): Builder
    {
        return $query->where('type', 'league');
    }

    /**
     * Scope a query to only include news for a specific league.
     */
    public function scopeForLeague(Builder $query, League $league): Builder
    {
        return $query->where('league_id', $league->id);
    }

    /**
     * Scope a query to only include news for a specific season.
     */
    public function scopeForSeason(Builder $query, Season $season): Builder
    {
        return $query->where('season_id', $season->id);
    }

    /**
     * Scope a query to only include news for a specific category.
     */
    public function scopeByCategory(Builder $query, NewsCategory $category): Builder
    {
        return $query->where('category_id', $category->id);
    }

    /**
     * Scope a query to only include news for a specific matchday.
     */
    public function scopeForMatchday(Builder $query, Matchday $matchday): Builder
    {
        return $query->where('matchday_id', $matchday->id);
    }

    /**
     * Scope a query to only include news for a specific fixture.
     */
    public function scopeForFixture(Builder $query, MatchdayFixture $fixture): Builder
    {
        return $query->where('matchday_fixture_id', $fixture->id);
    }

    /**
     * Get the content as rendered HTML from Markdown.
     */
    public function getRenderedContentAttribute(): string
    {
        if (empty($this->content)) {
            return '';
        }

        // Check if spatie/laravel-markdown is available
        if (class_exists(\Spatie\LaravelMarkdown\MarkdownRenderer::class)) {
            try {
                $html = app(\Spatie\LaravelMarkdown\MarkdownRenderer::class)
                    ->toHtml($this->content);
                
                // Add CSS classes to headings for better styling
                $html = $this->addHeadingClasses($html);
                
                return $html;
            } catch (\Exception $e) {
                // Fallback if markdown rendering fails
                \Illuminate\Support\Facades\Log::warning('Markdown rendering failed', [
                    'error' => $e->getMessage(),
                ]);
                return $this->parseMarkdown($this->content);
            }
        }

        // Fallback: Simple Markdown parsing if package not available
        return $this->parseMarkdown($this->content);
    }

    /**
     * Simple Markdown parser as fallback.
     * This is a basic parser that handles common Markdown syntax.
     */
    protected function parseMarkdown(string $markdown): string
    {
        $html = $markdown;

        // Code blocks first (to avoid parsing content inside code blocks)
        $codeBlocks = [];
        $html = preg_replace_callback('/```(\w+)?\n(.*?)```/s', function ($matches) use (&$codeBlocks) {
            $id = 'CODE_BLOCK_' . count($codeBlocks);
            $codeBlocks[$id] = '<pre class="bg-zinc-100 dark:bg-zinc-800 p-4 rounded-lg overflow-x-auto my-6"><code class="language-' . ($matches[1] ?? '') . '">' . htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8') . '</code></pre>';
            return $id;
        }, $html);

        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code class="bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-sm font-mono">$1</code>', $html);

        // Headers (must be at start of line) - improved spacing
        $html = preg_replace('/^#### (.*?)$/m', '<h4 class="text-xl font-bold mt-8 mb-4 text-neutral-900 dark:text-neutral-100">$1</h4>', $html);
        $html = preg_replace('/^### (.*?)$/m', '<h3 class="text-2xl font-bold mt-8 mb-4 text-neutral-900 dark:text-neutral-100">$1</h3>', $html);
        $html = preg_replace('/^## (.*?)$/m', '<h2 class="text-3xl font-bold mt-10 mb-5 text-neutral-900 dark:text-neutral-100 leading-tight">$1</h2>', $html);
        $html = preg_replace('/^# (.*?)$/m', '<h1 class="text-4xl font-bold mt-10 mb-6 text-neutral-900 dark:text-neutral-100 leading-tight">$1</h1>', $html);

        // Bold (must come before italic)
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-semibold text-neutral-900 dark:text-neutral-100">$1</strong>', $html);
        $html = preg_replace('/__(.*?)__/', '<strong class="font-semibold text-neutral-900 dark:text-neutral-100">$1</strong>', $html);

        // Italic (but not if already bold)
        $html = preg_replace('/(?<!\*)\*(?!\*)([^*\n]+?)(?<!\*)\*(?!\*)/', '<em class="italic">$1</em>', $html);
        $html = preg_replace('/(?<!_)_(?!_)([^_\n]+?)(?<!_)_(?!_)/', '<em class="italic">$1</em>', $html);

        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline decoration-2 underline-offset-2 transition-colors">$1</a>', $html);

        // Lists (unordered) - process line by line with better spacing
        $lines = explode("\n", $html);
        $inList = false;
        $result = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^[\*\-\+] (.+)$/', $line, $matches)) {
                if (!$inList) {
                    $result[] = '<ul class="list-disc list-outside space-y-2 my-6 ml-6 text-neutral-700 dark:text-neutral-300">';
                    $inList = true;
                }
                $result[] = '<li class="leading-relaxed">' . trim($matches[1]) . '</li>';
            } else {
                if ($inList) {
                    $result[] = '</ul>';
                    $inList = false;
                }
                $result[] = $line;
            }
        }
        
        if ($inList) {
            $result[] = '</ul>';
        }
        
        $html = implode("\n", $result);

        // Restore code blocks
        foreach ($codeBlocks as $id => $code) {
            $html = str_replace($id, $code, $html);
        }

        // Paragraphs (double newlines become paragraph breaks)
        // Split by double newlines, but preserve single newlines within paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $html);
        $wrappedParagraphs = [];
        
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (!empty($para) && !preg_match('/^<(h[1-6]|ul|pre|code)/', $para)) {
                // Convert single newlines to <br> within paragraphs
                $para = preg_replace('/\n/', '<br>', $para);
                $wrappedParagraphs[] = '<p class="mb-6 text-neutral-700 dark:text-neutral-300 leading-relaxed text-base">' . $para . '</p>';
            } else {
                $wrappedParagraphs[] = $para;
            }
        }
        
        $html = implode("\n", $wrappedParagraphs);

        return $html;
    }

    /**
     * Add CSS classes to headings in HTML rendered by Spatie Markdown.
     */
    protected function addHeadingClasses(string $html): string
    {
        // Add classes to h1-h6 headings (only if they don't already have a class attribute)
        $html = preg_replace_callback(
            '/<h1([^>]*)>/',
            function ($matches) {
                $attrs = $matches[1];
                if (strpos($attrs, 'class=') !== false) {
                    return $matches[0]; // Already has class, don't modify
                }
                return '<h1' . $attrs . ' class="text-4xl font-bold mt-10 mb-6 text-neutral-900 dark:text-neutral-100 leading-tight">';
            },
            $html
        );
        
        $html = preg_replace_callback(
            '/<h2([^>]*)>/',
            function ($matches) {
                $attrs = $matches[1];
                if (strpos($attrs, 'class=') !== false) {
                    return $matches[0];
                }
                return '<h2' . $attrs . ' class="text-3xl font-bold mt-10 mb-5 text-neutral-900 dark:text-neutral-100 leading-tight">';
            },
            $html
        );
        
        $html = preg_replace_callback(
            '/<h3([^>]*)>/',
            function ($matches) {
                $attrs = $matches[1];
                if (strpos($attrs, 'class=') !== false) {
                    return $matches[0];
                }
                return '<h3' . $attrs . ' class="text-2xl font-bold mt-8 mb-4 text-neutral-900 dark:text-neutral-100">';
            },
            $html
        );
        
        $html = preg_replace_callback(
            '/<h4([^>]*)>/',
            function ($matches) {
                $attrs = $matches[1];
                if (strpos($attrs, 'class=') !== false) {
                    return $matches[0];
                }
                return '<h4' . $attrs . ' class="text-xl font-bold mt-8 mb-4 text-neutral-900 dark:text-neutral-100">';
            },
            $html
        );
        
        $html = preg_replace_callback(
            '/<h5([^>]*)>/',
            function ($matches) {
                $attrs = $matches[1];
                if (strpos($attrs, 'class=') !== false) {
                    return $matches[0];
                }
                return '<h5' . $attrs . ' class="text-lg font-bold mt-6 mb-3 text-neutral-900 dark:text-neutral-100">';
            },
            $html
        );
        
        $html = preg_replace_callback(
            '/<h6([^>]*)>/',
            function ($matches) {
                $attrs = $matches[1];
                if (strpos($attrs, 'class=') !== false) {
                    return $matches[0];
                }
                return '<h6' . $attrs . ' class="text-base font-bold mt-6 mb-3 text-neutral-900 dark:text-neutral-100">';
            },
            $html
        );

        // Also ensure paragraphs have proper spacing (only if they don't already have a class)
        $html = preg_replace_callback(
            '/<p([^>]*)>/',
            function ($matches) {
                $attrs = $matches[1];
                if (strpos($attrs, 'class=') !== false) {
                    return $matches[0];
                }
                return '<p' . $attrs . ' class="mb-6 text-neutral-700 dark:text-neutral-300 leading-relaxed text-base">';
            },
            $html
        );

        return $html;
    }
}

