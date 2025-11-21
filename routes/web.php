<?php

use App\Models\DartMatch;
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

        // Export route (only for admins)
        Route::get('matches/{match}/export', function (DartMatch $match, MatchExportService $exportService) {
            if (! auth()->user()->hasAnyRole(['Super-Admin', 'Admin'])) {
                abort(403);
            }

            $data = $exportService->exportMatch($match);
            $filename = 'match-' . $match->autodarts_match_id . '-' . now()->format('Y-m-d-His') . '.json';

            return response()->json($data, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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

Route::middleware(['auth', 'verified', 'role:Super-Admin|Admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Volt::route('admin/users', 'admin.users.index')->name('users.index');
        Volt::route('admin/roles', 'admin.roles.index')->name('roles.index');

        Volt::route('admin/matches', 'admin.matches.index')->name('matches.index');
        Volt::route('admin/matches/{match}', 'admin.matches.show')
            ->middleware('can:view,match')
            ->name('matches.show');

        // Export route
        Route::get('admin/matches/{match}/export', function (DartMatch $match, MatchExportService $exportService) {
            $data = $exportService->exportMatch($match);
            $filename = 'match-' . $match->autodarts_match_id . '-' . now()->format('Y-m-d-His') . '.json';

            return response()->json($data, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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
    });
