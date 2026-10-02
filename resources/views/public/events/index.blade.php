@extends('public.layouts.app')

@section('title', 'Kegiatan - PSHT Kalimantan Selatan')

@php
    $periods = [
        'upcoming' => 'Mendatang',
        'past' => 'Selesai',
        'all' => 'Semua',
    ];
@endphp

@section('content')
    <section aria-labelledby="events-heading">
        <header class="psht-page-heading">
            <p class="psht-kicker">Agenda organisasi</p>
            <h1 id="events-heading" class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Kegiatan</h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-stone-300">Jadwal kegiatan yang telah diterbitkan, lengkap dengan waktu dan lokasi.</p>
        </header>

        <div class="pt-8">
            <div class="flex flex-wrap gap-2" role="group" aria-label="Filter waktu kegiatan">
                @foreach ($periods as $value => $label)
                    <a href="{{ route('public.events.index', ['period' => $value]) }}"
                       class="psht-control"
                       data-event-filter="{{ $value }}"
                       @if ($period === $value) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>

            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($events as $event)
                    <li>
                        <a href="{{ route('public.events.show', $event->slug) }}" class="psht-card block h-full overflow-hidden transition hover:border-psht-bright">
                            @if ($event->cover_path)
                                <img class="aspect-video w-full object-cover" src="{{ route('public.events.cover', $event) }}" alt="{{ $event->cover_alt_text ?: $event->title }}" loading="lazy">
                            @endif
                            <div class="p-5">
                                <time datetime="{{ $event->starts_at->toIso8601String() }}" class="block text-sm font-medium text-amber-300">{{ $event->starts_at->locale('id')->translatedFormat('l, d F Y H:i') }}</time>
                                <h2 class="mt-2 text-xl font-semibold text-white">{{ $event->title }}</h2>
                                @if ($event->venue)
                                    <p class="mt-2 text-sm text-stone-400">{{ $event->venue }}</p>
                                @endif
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="psht-card border-dashed p-6 text-stone-400 sm:col-span-2 lg:col-span-3">
                        <p>Belum ada kegiatan pada rentang ini.</p>
                        <a href="{{ route('public.events.index', ['period' => 'all']) }}" class="mt-3 inline-block font-medium text-amber-300 underline-offset-4 hover:underline">Lihat semua kegiatan</a>
                    </li>
                @endforelse
            </ul>

            <div class="mt-8">{{ $events->links() }}</div>
        </div>
    </section>
@endsection
