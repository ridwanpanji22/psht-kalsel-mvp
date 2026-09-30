<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Informasi resmi organisasi PSHT Kalimantan Selatan.">
    <title>{{ config('app.name', 'PSHT Kalimantan Selatan') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4" aria-label="Navigasi utama">
            <a href="{{ route('public.home') }}" class="font-semibold text-emerald-900">PSHT Kalimantan Selatan</a>
            <div class="flex flex-wrap gap-4 text-sm">
                <a href="{{ route('public.events.index') }}" class="underline-offset-4 hover:underline">Kegiatan</a>
                <a href="{{ route('public.training-locations.index') }}" class="underline-offset-4 hover:underline">Tempat latihan</a>
            </div>
        </nav>
    </header>
    <main class="mx-auto max-w-6xl px-4 py-12">
        <section class="max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">PSHT Kalimantan Selatan</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">Persaudaraan, pembinaan, dan organisasi.</h1>
            <p class="mt-5 text-lg leading-8 text-stone-600">Portal informasi resmi kegiatan, sejarah organisasi, dan lokasi latihan.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('public.events.index') }}" class="rounded-md bg-emerald-800 px-5 py-3 font-medium text-white hover:bg-emerald-900 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-800">Lihat kegiatan</a>
                <a href="{{ route('public.training-locations.index') }}" class="rounded-md border border-stone-300 bg-white px-5 py-3 font-medium hover:bg-stone-100 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-800">Temukan tempat latihan</a>
            </div>
        </section>
        <section class="mt-14 grid gap-5 sm:grid-cols-2" aria-label="Informasi terbaru">
            <a href="{{ route('public.events.index') }}" class="rounded-xl border border-stone-200 bg-white p-6 hover:border-emerald-700 focus:outline-2 focus:outline-emerald-800">
                <h2 class="text-xl font-semibold">Kalender kegiatan</h2>
                <p class="mt-2 text-stone-600">Agenda yang telah disetujui untuk dipublikasikan.</p>
            </a>
            <a href="{{ route('public.training-locations.index') }}" class="rounded-xl border border-stone-200 bg-white p-6 hover:border-emerald-700 focus:outline-2 focus:outline-emerald-800">
                <h2 class="text-xl font-semibold">Tempat latihan</h2>
                <p class="mt-2 text-stone-600">Lokasi dan jadwal latihan yang telah diverifikasi.</p>
            </a>
        </section>
    </main>
    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-stone-600">PSHT Kalimantan Selatan</div>
    </footer>
</body>
</html>
