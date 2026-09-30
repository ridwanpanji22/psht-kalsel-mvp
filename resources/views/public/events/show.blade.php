@extends('public.layouts.app')

@section('title', $event->title.' — Kegiatan PSHT Kalimantan Selatan')

@section('content')
    <article class="mx-auto max-w-3xl rounded-xl border border-stone-200 bg-white p-6 sm:p-10">
        <a href="{{ route('public.events.index') }}" class="text-sm font-medium text-emerald-800 underline-offset-4 hover:underline">← Semua kegiatan</a>
        <time datetime="{{ $event->starts_at->toIso8601String() }}" class="mt-6 block text-sm font-medium text-emerald-800">{{ $event->starts_at->locale('id')->translatedFormat('l, d F Y H:i') }}</time>
        <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ $event->title }}</h1>
        @if ($event->venue)
            <p class="mt-4 text-stone-600">{{ $event->venue }}</p>
        @endif
        @if ($event->description)
            <div class="mt-6 whitespace-pre-line leading-8 text-stone-700">{{ $event->description }}</div>
        @endif
    </article>
@endsection
