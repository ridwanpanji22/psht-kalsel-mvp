@extends('public.layouts.app')

@section('title', 'PSHT Kalimantan Selatan')

@section('content')
    <section class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">PSHT Kalimantan Selatan</p>
        <h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">Persaudaraan, pembinaan, dan organisasi.</h1>
        <p class="mt-5 text-lg leading-8 text-stone-600">Portal informasi resmi kegiatan, sejarah organisasi, galeri, dan lokasi latihan.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('public.events.index') }}" class="rounded-md bg-emerald-800 px-5 py-3 font-medium text-white hover:bg-emerald-900 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-800">Lihat kegiatan</a>
            <a href="{{ route('public.organization.index') }}" class="rounded-md border border-stone-300 bg-white px-5 py-3 font-medium hover:bg-stone-100 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-800">Struktur organisasi</a>
        </div>
    </section>

    <section class="mt-14" aria-labelledby="history-heading">
        <h2 id="history-heading" class="text-2xl font-bold">Sejarah organisasi</h2>
        <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($pages as $page)
                <li><a href="{{ route('public.pages.show', $page->slug) }}" class="block rounded-xl border border-stone-200 bg-white p-5 font-semibold hover:border-emerald-700 focus:outline-2 focus:outline-emerald-800">{{ $page->title }}</a></li>
            @empty
                <li class="text-stone-600">Belum ada sejarah yang diterbitkan.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-14" aria-labelledby="events-heading">
        <div class="flex items-end justify-between gap-4">
            <h2 id="events-heading" class="text-2xl font-bold">Kegiatan mendatang</h2>
            <a href="{{ route('public.events.index') }}" class="font-medium text-emerald-800 underline-offset-4 hover:underline">Semua kegiatan</a>
        </div>
        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
            @forelse ($events as $event)
                <li><a href="{{ route('public.events.show', $event->slug) }}" class="block rounded-xl border border-stone-200 bg-white p-5 hover:border-emerald-700 focus:outline-2 focus:outline-emerald-800"><time datetime="{{ $event->starts_at->toIso8601String() }}" class="text-sm text-emerald-800">{{ $event->starts_at->locale('id')->translatedFormat('d F Y H:i') }}</time><span class="mt-2 block font-semibold">{{ $event->title }}</span></a></li>
            @empty
                <li class="text-stone-600">Belum ada kegiatan yang diterbitkan.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-14" aria-labelledby="galleries-heading">
        <div class="flex items-end justify-between gap-4">
            <h2 id="galleries-heading" class="text-2xl font-bold">Galeri</h2>
            <a href="{{ route('public.galleries.index') }}" class="font-medium text-emerald-800 underline-offset-4 hover:underline">Semua album</a>
        </div>
        <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($galleries as $gallery)
                <li><a href="{{ route('public.galleries.index') }}" class="block rounded-xl border border-stone-200 bg-white p-5 font-semibold hover:border-emerald-700 focus:outline-2 focus:outline-emerald-800">{{ $gallery->title }}</a></li>
            @empty
                <li class="text-stone-600">Belum ada album yang diterbitkan.</li>
            @endforelse
        </ul>
    </section>
@endsection
