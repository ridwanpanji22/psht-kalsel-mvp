@extends('public.layouts.app')

@section('title', 'Kegiatan — PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="events-heading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">Agenda organisasi</p>
                <h1 id="events-heading" class="mt-2 text-3xl font-bold tracking-tight">Kegiatan</h1>
            </div>
            <p class="text-sm text-stone-600">Kegiatan yang telah diterbitkan.</p>
        </div>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($events as $event)
                <li>
                    <a href="{{ route('public.events.show', $event->slug) }}" class="block h-full rounded-xl border border-stone-200 bg-white p-5 hover:border-emerald-700 focus:outline-2 focus:outline-emerald-800">
                        <time datetime="{{ $event->starts_at->toIso8601String() }}" class="text-sm font-medium text-emerald-800">{{ $event->starts_at->locale('id')->translatedFormat('l, d F Y H:i') }}</time>
                        <h2 class="mt-2 text-xl font-semibold">{{ $event->title }}</h2>
                        @if ($event->venue)
                            <p class="mt-2 text-sm text-stone-600">{{ $event->venue }}</p>
                        @endif
                    </a>
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-stone-300 bg-white p-6 text-stone-600">Belum ada kegiatan yang diterbitkan.</li>
            @endforelse
        </ul>
        <div class="mt-8">{{ $events->links() }}</div>
    </section>
@endsection
