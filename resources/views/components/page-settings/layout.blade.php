<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            <flux:navlist.item href="#header">{{ __('Header') }}</flux:navlist.item>
            <flux:navlist.item href="#branding">{{ __('Branding') }}</flux:navlist.item>
            <flux:navlist.item href="#advanced">{{ __('Advanced') }}</flux:navlist.item>
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
