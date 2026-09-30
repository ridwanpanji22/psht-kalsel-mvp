<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Informasi resmi organisasi PSHT Kalimantan Selatan.">
    <title>@yield('title', 'PSHT Kalimantan Selatan')</title>
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4" aria-label="Navigasi utama">
            <a href="{{ route('public.home') }}" class="font-semibold text-emerald-900">PSHT Kalimantan Selatan</a>
            <div class="flex flex-wrap gap-4 text-sm">
                <a href="{{ route('public.organization.index') }}" class="underline-offset-4 hover:underline">Struktur</a>
                <a href="{{ route('public.galleries.index') }}" class="underline-offset-4 hover:underline">Galeri</a>
                <a href="{{ route('public.events.index') }}" class="underline-offset-4 hover:underline">Kegiatan</a>
                <a href="{{ route('public.training-locations.index') }}" class="underline-offset-4 hover:underline">Tempat latihan</a>
            </div>
        </nav>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-10">
        @yield('content')
    </main>
    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-stone-600">PSHT Kalimantan Selatan</div>
    </footer>
    @stack('scripts')
</body>
</html>
