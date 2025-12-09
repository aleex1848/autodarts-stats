<?php

use App\Http\Controllers\Auth\DiscordController;
use App\Models\DartMatch;
use App\Models\Download;
use App\Models\League;
use App\Models\News;
use App\Models\Season;
use App\Models\User;
use App\Services\MatchExportService;
use App\Services\MatchImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('datenschutz', 'privacy-policy')->name('privacy-policy');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Discord OAuth routes
Route::get('/auth/discord/redirect', [DiscordController::class, 'redirect'])->name('discord.redirect');
Route::get('/auth/discord/callback', [DiscordController::class, 'callback'])->name('discord.callback');

Route::model('match', DartMatch::class);
Route::model('league', League::class);
Route::model('news', News::class);
Route::model('season', Season::class);
Route::model('download', Download::class);
Route::model('user', User::class);

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('settings/api-tokens', 'settings.api-tokens')->name('api-tokens.index');
    Volt::route('settings/identify', 'settings.identify')->name('identify.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Volt::route('matches', 'matches.index')->name('matches.index');
        Volt::route('matches/{match}', 'matches.show')
            ->middleware('can:view,match')
            ->name('matches.show');

        Volt::route('leagues', 'leagues.index')->name('leagues.index');
        Volt::route('leagues/create', 'leagues.create')
            ->middleware('can:create,App\Models\League')
            ->name('leagues.create');
        Volt::route('leagues/{league}', 'leagues.show')
            ->middleware('can:view,league')
            ->name('leagues.show');

        Volt::route('seasons/{season}', 'seasons.show')
            ->middleware('can:view,season')
            ->name('seasons.show');

        Volt::route('users/{user}', 'users.show')->name('users.show');

        // News routes
        Volt::route('news/{news}', 'news.show')
            ->middleware('can:view,news')
            ->name('news.show');
        Volt::route('news/platform', 'news.platform')->name('news.platform');
        Volt::route('news/leagues', 'news.leagues')->name('news.leagues');

        // Export route (only for admins)
        Route::get('matches/{match}/export', function (DartMatch $match, MatchExportService $exportService) {
            if (! auth()->user()->hasAnyRole(['Super-Admin', 'Admin'])) {
                abort(403);
            }

            $data = $exportService->exportMatch($match);
            $filename = 'match-'.$match->autodarts_match_id.'-'.now()->format('Y-m-d-His').'.json';

            return response()->json($data, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        })->middleware('can:view,match')->name('matches.export');

        // Import route (only for admins)
        Route::post('matches/import', function (Request $request, MatchImportService $importService) {
            if (! auth()->user()->hasAnyRole(['Super-Admin', 'Admin'])) {
                abort(403);
            }

            $request->validate([
                'file' => 'required|file|mimes:json|max:10240',
                'overwrite' => 'boolean',
            ]);

            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['file' => __('Ungültige JSON-Datei.')]);
            }

            try {
                $overwrite = $request->boolean('overwrite', false);
                $match = $importService->importMatch($data, $overwrite);

                return back()->with('success', __('Match wurde erfolgreich importiert.'));
            } catch (\Exception $e) {
                return back()->withErrors(['file' => $e->getMessage()]);
            }
        })->name('matches.import');
    });

// User Switch Stop route (must be outside admin middleware to work when switched to non-admin user)
Route::post('admin/user-switch-stop', [\App\Http\Controllers\Admin\UserSwitchController::class, 'stop'])
    ->middleware(['auth'])
    ->name('admin.user-switch.stop');

Route::middleware(['auth', 'verified', 'role:Super-Admin|Admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Volt::route('admin/users', 'admin.users.index')->name('users.index');
        Volt::route('admin/roles', 'admin.roles.index')->name('roles.index');

        // User Switch routes
        Volt::route('admin/user-switch', 'admin.user-switch.index')->name('user-switch.index');
        Route::post('admin/user-switch/{user}', [\App\Http\Controllers\Admin\UserSwitchController::class, 'switch'])->name('user-switch.switch');

        Volt::route('admin/matches', 'admin.matches.index')->name('matches.index');

        Volt::route('admin/leagues', 'admin.leagues.index')->name('leagues.index');
        Volt::route('admin/leagues/create', 'admin.leagues.create')->name('leagues.create');
        Volt::route('admin/leagues/{league}', 'admin.leagues.show')
            ->middleware('can:view,league')
            ->name('leagues.show');
        Volt::route('admin/leagues/{league}/edit', 'admin.leagues.edit')
            ->middleware('can:update,league')
            ->name('leagues.edit');

        // Season routes
        Volt::route('admin/seasons/create', 'admin.seasons.create')->name('seasons.create');
        Volt::route('admin/seasons/{season}', 'admin.seasons.show')
            ->middleware('can:view,season')
            ->name('seasons.show');
        Volt::route('admin/seasons/{season}/edit', 'admin.seasons.edit')
            ->middleware('can:update,season')
            ->name('seasons.edit');

        // Import route
        Route::post('admin/matches/import', function (Request $request, MatchImportService $importService) {
            $request->validate([
                'file' => 'required|file|mimes:json|max:10240',
                'overwrite' => 'boolean',
            ]);

            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['file' => __('Ungültige JSON-Datei.')]);
            }

            try {
                $overwrite = $request->boolean('overwrite', false);
                $match = $importService->importMatch($data, $overwrite);

                return back()->with('success', __('Match wurde erfolgreich importiert.'));
            } catch (\Exception $e) {
                return back()->withErrors(['file' => $e->getMessage()]);
            }
        })->name('matches.import');

        // Downloads routes
        Volt::route('admin/downloads', 'admin.downloads.index')->name('downloads.index');
        Volt::route('admin/downloads/create', 'admin.downloads.create')->name('downloads.create');
        Volt::route('admin/downloads/{download}', 'admin.downloads.show')->name('downloads.show');

        // Download categories routes
        Volt::route('admin/download-categories', 'admin.download-categories.index')->name('download-categories.index');
        
        // News routes
        Volt::route('admin/news/categories', 'admin.news.categories.index')->name('news.categories.index');
        Volt::route('admin/news/platform', 'admin.news.platform.index')->name('news.platform.index');
        Volt::route('admin/news/leagues', 'admin.news.leagues.index')->name('news.leagues.index');
        
        Volt::route('admin/page-settings', 'admin.page-settings.index')->name('page-settings.index');
        Volt::route('admin/page-settings/scheduler', 'admin.page-settings.scheduler')->name('page-settings.scheduler');
        Volt::route('admin/page-settings/openai', 'admin.page-settings.openai')->name('page-settings.openai');
    });

Route::middleware(['auth', 'verified'])
    ->group(function () {
        // Public download routes
        Volt::route('downloads/{download}', 'downloads.show')->name('downloads.show');

        // Download file route
        Route::get('downloads/{download}/file', function (Download $download) {
            $media = $download->getFirstMedia('files');

            if (! $media) {
                abort(404);
            }

            return $media;
        })->name('downloads.file');
    });
