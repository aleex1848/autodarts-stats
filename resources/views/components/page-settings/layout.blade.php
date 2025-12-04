<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            <flux:navlist.item href="{{ route('admin.page-settings.index') }}" :current="request()->routeIs('admin.page-settings.index')">{{ __('Dashboard') }}</flux:navlist.item>
            <flux:navlist.item href="{{ route('admin.page-settings.scheduler') }}" :current="request()->routeIs('admin.page-settings.scheduler')">{{ __('Scheduler') }}</flux:navlist.item>
            <flux:navlist.item href="{{ route('admin.page-settings.openai') }}" :current="request()->routeIs('admin.page-settings.openai')">{{ __('OpenAI') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-3xl">
            {{ $slot }}
        </div>
    </div>
</div>
