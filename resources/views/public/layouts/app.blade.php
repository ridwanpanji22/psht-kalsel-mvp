@php
    $publicNav = [
        ['label' => 'Struktur', 'route' => 'public.organization.index', 'pattern' => 'public.organization.*'],
        ['label' => 'Galeri', 'route' => 'public.galleries.index', 'pattern' => 'public.galleries.*'],
        ['label' => 'Kegiatan', 'route' => 'public.events.index', 'pattern' => 'public.events.*'],
        ['label' => 'Tempat latihan', 'route' => 'public.training-locations.index', 'pattern' => 'public.training-locations.*'],
    ];
    $isHomepage = View::hasSection('homepage');
    $currentRoute = request()->route()?->getName() ?? '';
@endphp
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
<body class="psht-shell min-h-screen bg-psht-bg text-stone-100 antialiased">
    @unless(View::hasSection('homepage-header'))
        <header class="border-b border-white/10 bg-psht-bg/95 backdrop-blur">
            <nav class="mx-auto flex min-h-20 max-w-6xl items-center justify-between gap-4 px-4" aria-label="Navigasi utama">
                <a href="{{ route('public.home') }}" class="font-semibold text-white">PSHT Kalimantan Selatan</a>

                <div class="hidden items-center gap-6 text-sm md:flex">
                    @foreach ($publicNav as $item)
                        @php $isCurrent = str($currentRoute)->is($item['pattern']); @endphp
                        <a href="{{ route($item['route']) }}"
                           @if ($isCurrent) aria-current="page" @endif
                           class="{{ $isCurrent ? 'font-semibold text-white' : 'text-stone-300 hover:text-white' }}">{{ $item['label'] }}</a>
                    @endforeach
                </div>

                <details class="relative md:hidden">
                    <summary class="cursor-pointer list-none rounded-lg border border-white/30 px-3 py-2 text-sm text-white" aria-label="Buka menu navigasi">Menu</summary>
                    <div class="absolute right-0 top-full z-20 mt-2 grid w-48 gap-1 rounded-2xl border border-white/10 bg-psht-surface p-2 shadow-xl">
                        @foreach ($publicNav as $item)
                            @php $isCurrent = str($currentRoute)->is($item['pattern']); @endphp
                            <a href="{{ route($item['route']) }}"
                               @if ($isCurrent) aria-current="page" @endif
                               class="rounded-lg px-3 py-2 text-sm {{ $isCurrent ? 'bg-psht-blue font-semibold text-white' : 'text-stone-200 hover:bg-white/10' }}">{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </details>
            </nav>
        </header>
    @endunless
    @yield('homepage-header')
    <main class="@if ($isHomepage) max-w-none p-0 @else mx-auto max-w-6xl px-4 py-10 @endif">
        @yield('content')
    </main>
    <footer class="psht-footer">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-stone-400 sm:flex-row sm:items-center sm:justify-between">
            <span class="font-semibold text-stone-200">PSHT Kalimantan Selatan</span>
            <span>Portal resmi organisasi PSHT Kalimantan Selatan.</span>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
