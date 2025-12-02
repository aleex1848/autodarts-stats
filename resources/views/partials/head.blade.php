<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

{{-- Favicon --}}
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Open Graph Meta Tags für WhatsApp und andere Social Media --}}
<meta property="og:type" content="website" />
<meta property="og:title" content="{{ $title ?? 'Autodarts-Liga.de' }}" />
<meta property="og:description" content="{{ $description ?? 'Autodarts-Liga.de - Die Liga für Autodarts' }}" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:image" content="{{ url('/favicon.svg') }}" />
<meta property="og:site_name" content="Autodarts-Liga.de" />

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="{{ $title ?? 'Autodarts-Liga.de' }}" />
<meta name="twitter:description" content="{{ $description ?? 'Autodarts-Liga.de - Die Liga für Autodarts' }}" />
<meta name="twitter:image" content="{{ url('/favicon.svg') }}" />

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
