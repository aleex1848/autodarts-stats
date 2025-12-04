<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    <flux:navlist.item
                        icon="trophy"
                        :href="route('matches.index')"
                        :current="request()->routeIs('matches.*') && !request()->routeIs('admin.*')"
                        wire:navigate
                    >
                        {{ __('Matches') }}
                    </flux:navlist.item>
                    <flux:navlist.item
                        icon="flag"
                        :href="route('leagues.index')"
                        :current="request()->routeIs('leagues.*') && !request()->routeIs('admin.*')"
                        wire:navigate
                    >
                        {{ __('Ligen') }}
                    </flux:navlist.item>
                </flux:navlist.group>

                @hasanyrole('Super-Admin|Admin')
                    <flux:navlist.group :heading="__('Admin')" class="grid">
                        <flux:navlist.item
                            icon="trophy"
                            :href="route('admin.matches.index')"
                            :current="request()->routeIs('admin.matches.*')"
                            wire:navigate
                        >
                            {{ __('Matches') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="flag"
                            :href="route('admin.leagues.index')"
                            :current="request()->routeIs('admin.leagues.*')"
                            wire:navigate
                        >
                            {{ __('Ligen') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="users"
                            :href="route('admin.users.index')"
                            :current="request()->routeIs('admin.users.*')"
                            wire:navigate
                        >
                            {{ __('Benutzer') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="key"
                            :href="route('admin.roles.index')"
                            :current="request()->routeIs('admin.roles.*')"
                            wire:navigate
                        >
                            {{ __('Rollen') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="arrows-right-left"
                            :href="route('admin.user-switch.index')"
                            :current="request()->routeIs('admin.user-switch.*')"
                            wire:navigate
                        >
                            {{ __('User-Switch') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="arrow-down-tray"
                            :href="route('admin.downloads.index')"
                            :current="request()->routeIs('admin.downloads.*') || request()->routeIs('admin.download-categories.*')"
                            wire:navigate
                        >
                            {{ __('Downloads') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="newspaper"
                            :href="route('admin.news.platform.index')"
                            :current="request()->routeIs('admin.news.platform.*')"
                            wire:navigate
                        >
                            {{ __('Platform News') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="flag"
                            :href="route('admin.news.leagues.index')"
                            :current="request()->routeIs('admin.news.leagues.*')"
                            wire:navigate
                        >
                            {{ __('Liga News') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            icon="tag"
                            :href="route('admin.news.categories.index')"
                            :current="request()->routeIs('admin.news.categories.*')"
                            wire:navigate
                        >
                            {{ __('News Kategorien') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endhasanyrole
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                {{ __('Repository') }}
                </flux:navlist.item>

                <flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                {{ __('Documentation') }}
                </flux:navlist.item>
            </flux:navlist>

            <!-- User Switch Mode Warning -->
            @if(session()->has('original_user_id'))
                <div class="mx-2 mb-3 rounded-lg border border-amber-500 bg-amber-100 p-3 dark:border-amber-600 dark:bg-amber-900/50">
                    <div class="mb-2 flex items-center gap-2">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span class="text-sm font-semibold text-amber-800 dark:text-amber-200">{{ __('Switch-Modus aktiv') }}</span>
                    </div>
                    <p class="mb-2 text-xs text-amber-700 dark:text-amber-300">
                        {{ __('Eingeloggt als:') }} <strong>{{ auth()->user()->name }}</strong>
                    </p>
                    <form method="POST" action="{{ route('admin.user-switch.stop') }}">
                        @csrf
                        <flux:button type="submit" size="xs" variant="danger" class="w-full">
                            {{ __('Switch beenden') }}
                        </flux:button>
                    </form>
                </div>
            @endif

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                    data-test="sidebar-menu-button"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <!-- User Switch Mode Warning (Mobile) -->
            @if(session()->has('original_user_id'))
                <div class="flex items-center gap-2 rounded-lg bg-amber-100 px-2 py-1 dark:bg-amber-900/50">
                    <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="text-xs font-semibold text-amber-800 dark:text-amber-200">{{ __('Switch-Modus') }}</span>
                </div>
            @endif

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <x-notifications />

        @fluxScripts
    </body>
</html>
