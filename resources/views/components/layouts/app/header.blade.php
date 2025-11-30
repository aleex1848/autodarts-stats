<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="ms-2 me-5 flex items-center space-x-2 rtl:space-x-reverse lg:ms-0" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>

                <flux:navbar.item
                    icon="trophy"
                    :href="route('matches.index')"
                    :current="request()->routeIs('matches.*') && !request()->routeIs('admin.*')"
                    wire:navigate
                >
                    {{ __('Matches') }}
                </flux:navbar.item>

                <flux:dropdown>
                    <flux:navbar.item
                        icon="flag"
                        icon:trailing="chevron-down"
                        :current="request()->routeIs('leagues.*') && !request()->routeIs('admin.*')"
                    >
                        {{ __('Liga') }}
                    </flux:navbar.item>

                    <flux:navmenu>
                        <flux:navmenu.item
                            icon="list-bullet"
                            :href="route('leagues.index')"
                            :current="request()->routeIs('leagues.index')"
                            wire:navigate
                        >
                            {{ __('Übersicht') }}
                        </flux:navmenu.item>

                        <flux:menu.separator />

                        @php
                            $user = auth()->user();
                            if ($user) {
                                if (!$user->relationLoaded('player')) {
                                    $user->load('player');
                                }
                                $userLeagues = $user->leagues();
                            } else {
                                $userLeagues = collect();
                            }
                        @endphp
                        
                        @if($userLeagues->isNotEmpty())
                            <flux:menu.group heading="Meine Ligen">
                                @foreach($userLeagues as $league)
                                    <flux:navmenu.item
                                        :href="route('leagues.show', $league)"
                                        :current="request()->routeIs('leagues.show', $league)"
                                        wire:navigate
                                    >
                                        {{ $league->name }}
                                    </flux:navmenu.item>
                                @endforeach
                            </flux:menu.group>
                        @endif
                    </flux:navmenu>
                </flux:dropdown>

                @hasanyrole('Super-Admin|Admin')
                    <flux:dropdown>
                        <flux:navbar.item icon="key" icon:trailing="chevron-down">
                            {{ __('Admin') }}
                        </flux:navbar.item>

                        <flux:navmenu>
                            <flux:navmenu.item
                                icon="trophy"
                                :href="route('admin.matches.index')"
                                :current="request()->routeIs('admin.matches.*')"
                                wire:navigate
                            >
                                {{ __('Matches') }}
                            </flux:navmenu.item>

                            <flux:navmenu.item
                                icon="flag"
                                :href="route('admin.leagues.index')"
                                :current="request()->routeIs('admin.leagues.*')"
                                wire:navigate
                            >
                                {{ __('Ligen') }}
                            </flux:navmenu.item>

                            <flux:navmenu.item
                                icon="users"
                                :href="route('admin.users.index')"
                                :current="request()->routeIs('admin.users.*')"
                                wire:navigate
                            >
                                {{ __('Benutzer') }}
                            </flux:navmenu.item>

                            <flux:navmenu.item
                                icon="key"
                                :href="route('admin.roles.index')"
                                :current="request()->routeIs('admin.roles.*')"
                                wire:navigate
                            >
                                {{ __('Rollen') }}
                            </flux:navmenu.item>

                            <flux:navmenu.item
                                icon="arrows-right-left"
                                :href="route('admin.user-switch.index')"
                                :current="request()->routeIs('admin.user-switch.*')"
                                wire:navigate
                            >
                                {{ __('User-Switch') }}
                            </flux:navmenu.item>

                            <flux:navmenu.item
                                icon="arrow-down-tray"
                                :href="route('admin.downloads.index')"
                                :current="request()->routeIs('admin.downloads.*') || request()->routeIs('admin.download-categories.*')"
                                wire:navigate
                            >
                                {{ __('Downloads') }}
                            </flux:navmenu.item>

                            <flux:navmenu.item
                                icon="adjustments-vertical"
                                :href="route('admin.page-settings.index')"
                                :current="request()->routeIs('admin.page-settings.*')"
                                wire:navigate
                            >
                                {{ __('Page Settings') }}
                            </flux:navmenu.item>
                        </flux:navmenu>
                    </flux:dropdown>
                @endhasanyrole
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
                <flux:tooltip :content="__('Repository')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="folder-git-2"
                        href="https://github.com/laravel/livewire-starter-kit"
                        target="_blank"
                        :label="__('Repository')"
                    />
                </flux:tooltip>
                <flux:tooltip :content="__('Documentation')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="book-open-text"
                        href="https://laravel.com/docs/starter-kits#livewire"
                        target="_blank"
                        label="Documentation"
                    />
                </flux:tooltip>
            </flux:navbar>

            <!-- Desktop User Menu -->
            <flux:dropdown position="top" align="end">
                <flux:profile
                    class="cursor-pointer"
                    :initials="auth()->user()->initials()"
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

        <!-- Mobile Menu -->
        <flux:sidebar stashable sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="ms-1 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:navlist.item>
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
                        :current="request()->routeIs('leagues.index')"
                        wire:navigate
                    >
                        {{ __('Liga') }}
                    </flux:navlist.item>
                </flux:navlist.group>

                @if(auth()->user()->leagues()->isNotEmpty())
                    <flux:navlist.group :heading="__('Meine Ligen')" expandable expanded class="grid">
                        @foreach(auth()->user()->leagues() as $league)
                            <flux:navlist.item
                                :href="route('leagues.show', $league)"
                                :current="request()->routeIs('leagues.show', $league)"
                                wire:navigate
                            >
                                {{ $league->name }}
                            </flux:navlist.item>
                        @endforeach
                    </flux:navlist.group>
                @endif

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
                            icon="adjustments-vertical"
                            :href="route('admin.page-settings.index')"
                            :current="request()->routeIs('admin.page-settings.*')"
                            wire:navigate
                        >
                            {{ __('Page Settings') }}
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
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
