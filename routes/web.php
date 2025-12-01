<?php

use App\Models\DartMatch;
use App\Models\Download;
use App\Models\League;
use App\Services\MatchExportService;
use App\Services\MatchImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::model('match', DartMatch::class);
Route::model('league', League::class);
Route::model('download', Download::class);

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('settings/api-tokens', 'settings.api-tokens')->name('api-tokens.index');

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
        Volt::route('leagues/{league}', 'leagues.show')
            ->middleware('can:view,league')
            ->name('leagues.show');
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
        Volt::route('admin/matches/{match}', 'admin.matches.show')
            ->middleware('can:view,match')
            ->name('matches.show');

        Volt::route('admin/leagues', 'admin.leagues.index')->name('leagues.index');
        Volt::route('admin/leagues/create', 'admin.leagues.create')->name('leagues.create');
        Volt::route('admin/leagues/{league}', 'admin.leagues.show')
            ->middleware('can:view,league')
            ->name('leagues.show');
        Volt::route('admin/leagues/{league}/edit', 'admin.leagues.edit')
            ->middleware('can:update,league')
            ->name('leagues.edit');
        // Export route
        Route::get('admin/matches/{match}/export', function (DartMatch $match, MatchExportService $exportService) {
            $data = $exportService->exportMatch($match);
            $filename = 'match-'.$match->autodarts_match_id.'-'.now()->format('Y-m-d-His').'.json';

            return response()->json($data, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        })->middleware('can:view,match')->name('matches.export');

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
        Volt::route('admin/page-settings', 'admin.page-settings.index')->name('page-settings.index');
        Volt::route('admin/page-settings/scheduler', 'admin.page-settings.scheduler')->name('page-settings.scheduler');
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
