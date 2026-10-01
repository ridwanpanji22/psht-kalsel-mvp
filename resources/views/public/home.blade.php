@extends('public.layouts.app')

@section('title', 'PSHT Kalimantan Selatan')

@section('homepage')
@endsection

@section('homepage-header')
    <header class="absolute inset-x-0 top-0 z-20">
        <nav class="mx-auto flex h-20 max-w-6xl items-center justify-between gap-4 px-4" aria-label="Navigasi utama beranda">
            <a href="{{ route('public.home') }}" class="font-semibold text-white">PSHT Kalimantan Selatan</a>

            <div class="hidden items-center gap-6 text-sm text-stone-200 md:flex">
                <a href="{{ route('public.organization.index') }}" class="underline-offset-4 hover:text-white hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">Struktur</a>
                <a href="{{ route('public.galleries.index') }}" class="underline-offset-4 hover:text-white hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">Galeri</a>
                <a href="{{ route('public.events.index') }}" class="underline-offset-4 hover:text-white hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">Kegiatan</a>
                <a href="{{ route('public.training-locations.index') }}" class="underline-offset-4 hover:text-white hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">Tempat latihan</a>
            </div>

            <details class="relative md:hidden">
                <summary class="cursor-pointer list-none rounded-md border border-white/30 px-3 py-2 text-sm font-medium text-white focus:outline-2 focus:outline-offset-2 focus:outline-amber-300" aria-label="Buka menu navigasi">Menu</summary>
                <div class="absolute right-0 top-full z-20 mt-2 w-48 rounded-xl border border-white/10 bg-[#0d1117] p-2 shadow-xl">
                    <a href="{{ route('public.organization.index') }}" class="block rounded-md px-3 py-2 text-sm text-stone-200 hover:bg-white/10 focus:outline-2 focus:outline-amber-300">Struktur</a>
                    <a href="{{ route('public.galleries.index') }}" class="block rounded-md px-3 py-2 text-sm text-stone-200 hover:bg-white/10 focus:outline-2 focus:outline-amber-300">Galeri</a>
                    <a href="{{ route('public.events.index') }}" class="block rounded-md px-3 py-2 text-sm text-stone-200 hover:bg-white/10 focus:outline-2 focus:outline-amber-300">Kegiatan</a>
                    <a href="{{ route('public.training-locations.index') }}" class="block rounded-md px-3 py-2 text-sm text-stone-200 hover:bg-white/10 focus:outline-2 focus:outline-amber-300">Tempat latihan</a>
                </div>
            </details>
        </nav>
    </header>
@endsection

@section('content')
    <section class="relative isolate flex min-h-[85vh] items-center overflow-hidden bg-[#0d1117]" aria-labelledby="hero-heading">
        <video class="absolute inset-0 -z-20 h-full w-full object-cover" data-hero-video aria-hidden="true" muted loop playsinline preload="metadata" poster="{{ asset('images/hero-martial-arts-poster.webp') }}">
            <source src="{{ asset('videos/hero-martial-arts.mp4') }}" type="video/mp4">
        </video>
        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-[#04070d]/95 via-[#0d1117]/80 to-[#0d1117]/40" aria-hidden="true"></div>

        <div class="mx-auto w-full max-w-6xl px-4 pt-28 pb-16 sm:pt-32">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">PSHT Kalimantan Selatan</p>
            <h1 id="hero-heading" class="mt-4 max-w-4xl text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-[3.25rem]">Persaudaraan dan pembinaan di Kalimantan Selatan.</h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-stone-200">Portal resmi PSHT Kalimantan Selatan untuk mengenal organisasi, kegiatan, galeri, dan tempat latihan.</p>
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ route('public.organization.index') }}" class="rounded-md bg-[#0720b0] px-5 py-3 font-semibold text-white hover:bg-[#2162ed] focus:bg-[#2162ed] focus:outline-2 focus:outline-offset-2 focus:outline-[#2162ed]">Jelajahi struktur</a>
                <a href="{{ route('public.training-locations.index') }}" class="rounded-md border border-white/40 px-5 py-3 font-semibold text-white hover:bg-white/10 focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">Temukan tempat latihan</a>
            </div>
        </div>
    </section>

    <section class="border-y border-white/10 bg-[#0b0f16]" aria-labelledby="values-heading">
        <div class="mx-auto max-w-6xl px-4 py-12">
            <h2 id="values-heading" class="sr-only">Nilai dasar</h2>
            <ul class="flex snap-x gap-4 overflow-x-auto pb-2 text-sm font-medium text-stone-200 sm:grid sm:grid-cols-3 sm:overflow-visible lg:grid-cols-6">
                @foreach (['Persaudaraan', 'Pembinaan', 'Olahraga', 'Bela diri', 'Kesenian', 'Kerohanian'] as $value)
                    <li class="snap-start rounded-md border border-white/10 bg-white/5 px-4 py-3 text-center whitespace-nowrap">{{ $value }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="bg-[#0d1117]" aria-labelledby="history-heading">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Sejarah</p>
                <h2 id="history-heading" class="mt-3 text-3xl font-bold tracking-tight text-white">Jejak organisasi</h2>
            </div>
            <div class="mt-10 grid gap-6 lg:grid-cols-3">
                @forelse ($pages as $index => $page)
                    <article class="rounded-2xl border border-white/10 bg-[#111722] p-6 {{ $index === 0 ? 'lg:col-span-2 lg:row-span-2' : '' }}">
                        <h3 class="text-xl font-semibold text-white">
                            <a href="{{ route('public.pages.show', $page->slug) }}" class="underline-offset-4 hover:text-amber-200 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">{{ $page->title }}</a>
                        </h3>
                        <p class="mt-3 text-sm text-stone-400">Baca selengkapnya</p>
                    </article>
                @empty
                    <p class="rounded-2xl border border-dashed border-white/20 bg-[#111722] p-6 text-stone-400">Informasi sejarah belum diterbitkan.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="border-t border-white/10 bg-[#0b0f16]" aria-labelledby="events-heading">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Agenda</p>
                    <h2 id="events-heading" class="mt-3 text-3xl font-bold tracking-tight text-white">Kegiatan mendatang</h2>
                </div>
                <a href="{{ route('public.events.index') }}" class="font-medium text-amber-300 underline-offset-4 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">Semua kegiatan</a>
            </div>
            <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($events as $event)
                    <li>
                        <a href="{{ route('public.events.show', $event->slug) }}" class="block h-full rounded-2xl border border-white/10 bg-[#111722] p-5 hover:border-amber-300/60 focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">
                            <time datetime="{{ $event->starts_at->toIso8601String() }}" class="text-sm text-amber-300">{{ $event->starts_at->locale('id')->translatedFormat('d F Y H:i') }}</time>
                            <span class="mt-3 block font-semibold text-white">{{ $event->title }}</span>
                            @if ($event->venue)
                                <span class="mt-2 block text-sm text-stone-400">{{ $event->venue }}</span>
                            @endif
                        </a>
                    </li>
                @empty
                    <li class="rounded-2xl border border-dashed border-white/20 bg-[#111722] p-6 text-stone-400">Belum ada kegiatan mendatang.</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="border-t border-white/10 bg-[#0d1117]" aria-labelledby="galleries-heading">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Dokumentasi</p>
                    <h2 id="galleries-heading" class="mt-3 text-3xl font-bold tracking-tight text-white">Galeri</h2>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('public.galleries.index') }}" class="font-medium text-amber-300 underline-offset-4 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">Semua album</a>
                    @php $publishedGalleries = $galleries->filter(fn ($gallery) => $gallery->media->isNotEmpty())->values(); @endphp
                    @if ($publishedGalleries->isNotEmpty())
                        <div class="hidden items-center gap-2 md:flex" aria-label="Kontrol carousel galeri">
                            <button type="button" data-carousel-prev class="flex h-9 w-9 items-center justify-center rounded-full border border-white/20 bg-white/5 text-white transition hover:bg-[#0720b0] hover:border-[#2162ed] focus:outline-2 focus:outline-offset-2 focus:outline-[#2162ed]" aria-label="Album sebelumnya">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                            </button>
                            <button type="button" data-carousel-next class="flex h-9 w-9 items-center justify-center rounded-full border border-white/20 bg-white/5 text-white transition hover:bg-[#0720b0] hover:border-[#2162ed] focus:outline-2 focus:outline-offset-2 focus:outline-[#2162ed]" aria-label="Album berikutnya">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            @if ($publishedGalleries->isEmpty())
                <p class="mt-10 rounded-2xl border border-dashed border-white/20 bg-[#111722] p-6 text-stone-400">Belum ada dokumentasi yang diterbitkan.</p>
            @else
                <div data-gallery-carousel role="region" aria-roledescription="carousel" aria-label="Galeri dokumentasi" class="relative mt-8">
                    <div data-carousel-scroller tabindex="0" class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4 scroll-smooth focus:outline-none [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($publishedGalleries as $index => $gallery)
                            <article data-carousel-item data-index="{{ $index }}" class="w-full shrink-0 snap-start overflow-hidden rounded-2xl border border-white/10 bg-[#111722] md:w-[calc((100%-2rem)/3)]">
                                <a href="{{ route('public.galleries.index') }}" class="block focus:outline-2 focus:outline-offset-2 focus:outline-[#2162ed]">
                                    <img class="aspect-video w-full object-cover" src="{{ route('public.media.show', $gallery->media->first()) }}" alt="{{ $gallery->media->first()->alt_text }}" loading="lazy">
                                    <h3 class="px-5 py-4 font-semibold text-white truncate">{{ $gallery->title }}</h3>
                                </a>
                            </article>
                        @endforeach
                    </div>

                    {{-- Plain group of buttons, not an ARIA tabs widget: there are no tabpanels and
                         no roving tabindex, so `role="tablist"`/`role="tab"` would be a lie. --}}
                    <div data-carousel-dots class="mt-4 flex items-center justify-center" aria-label="Pilih album galeri">
                        @foreach ($publishedGalleries as $index => $gallery)
                            <button type="button" data-carousel-dot="{{ $index }}" aria-label="Lihat album {{ $index + 1 }}: {{ $gallery->title }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}" class="flex min-h-[32px] min-w-[32px] items-center justify-center focus:outline-2 focus:outline-offset-2 focus:outline-[#2162ed]">
                                <span class="block h-2 rounded-full transition-all duration-300 {{ $index === 0 ? 'w-6 bg-[#2162ed]' : 'w-2 bg-white/30 hover:bg-white/60' }}" aria-hidden="true"></span>
                            </button>
                        @endforeach
                    </div>
                    <div data-carousel-status aria-live="polite" class="sr-only"></div>
                </div>
            @endif
        </div>
    </section>

    <section class="border-t border-white/10 bg-[#0b0f16]" aria-labelledby="locations-heading">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-300">Lokasi terverifikasi</p>
                <h2 id="locations-heading" class="mt-3 text-3xl font-bold tracking-tight text-white">Tempat latihan</h2>
            </div>
            @if ($trainingLocations->isEmpty())
                <p class="mt-10 rounded-2xl border border-dashed border-white/20 bg-[#111722] p-6 text-stone-400">Informasi tempat latihan belum tersedia.</p>
            @else
                <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($trainingLocations as $location)
                        <li class="rounded-2xl border border-white/10 bg-[#111722] p-5">
                            <h3 class="text-lg font-semibold text-white">{{ $location->name }}</h3>
                            <p class="mt-2 text-sm text-stone-400">{{ $location->address }}</p>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-8">
                    <a href="{{ route('public.training-locations.index') }}" class="font-medium text-amber-300 underline-offset-4 hover:underline focus:outline-2 focus:outline-offset-2 focus:outline-amber-300">Lihat semua tempat latihan</a>
                </div>
            @endif
        </div>
    </section>
@endsection