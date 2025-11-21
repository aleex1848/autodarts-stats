<?php

use App\Models\DartMatch;
use App\Models\League;
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

        Volt::route('admin/leagues', 'admin.leagues.index')->name('leagues.index');
        Volt::route('admin/leagues/create', 'admin.leagues.create')->name('leagues.create');
        Volt::route('admin/leagues/{league}', 'admin.leagues.show')
            ->middleware('can:view,league')
            ->name('leagues.show');
        Volt::route('admin/leagues/{league}/edit', 'admin.leagues.edit')
            ->middleware('can:update,league')
            ->name('leagues.edit');
    });
