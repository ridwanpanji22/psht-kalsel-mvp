@extends('public.layouts.app')

@section('title', $event->title.' - Kegiatan PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="event-heading" class="pb-16 pt-10">
        <nav aria-label="Breadcrumb" class="text-sm text-stone-400">
            <a href="{{ route('public.home') }}" class="hover:text-white">Beranda</a>
            <span aria-hidden="true"> / </span>
            <a href="{{ route('public.events.index') }}" class="hover:text-white">Kegiatan</a>
            <span aria-hidden="true"> / </span>
            <span aria-current="page" class="text-stone-200">{{ $event->title }}</span>
        </nav>

        <article class="mt-8 max-w-3xl">
            <p class="psht-kicker">Agenda organisasi</p>
            <h1 id="event-heading" class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $event->title }}</h1>

            <dl class="mt-6 space-y-4 border-t border-white/10 pt-6 text-stone-300">
                <div>
                    <dt class="text-sm text-stone-400">Waktu</dt>
                    <dd class="mt-1">
                        <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->locale('id')->translatedFormat('l, d F Y H:i') }}</time>
                    </dd>
                </div>
                @if ($event->venue)
                    <div>
                        <dt class="text-sm text-stone-400">Tempat</dt>
                        <dd class="mt-1">{{ $event->venue }}</dd>
                    </div>
                @endif
            </dl>

            @if ($event->cover_path)
                <img class="mt-8 aspect-video w-full rounded-xl object-cover" src="{{ route('public.events.cover', $event) }}" alt="{{ $event->cover_alt_text ?: $event->title }}">
            @endif

            @if ($event->description)
                <div class="psht-editorial mt-8 whitespace-pre-line border-t border-white/10 pt-6 leading-8 text-stone-300">{{ $event->description }}</div>
            @endif

            <a href="{{ route('public.events.index') }}" class="mt-8 inline-flex min-h-11 items-center rounded-lg bg-psht-blue px-4 py-3 font-semibold text-white transition hover:bg-psht-bright">Kembali ke semua kegiatan</a>
        </article>
    </section>
@endsection
