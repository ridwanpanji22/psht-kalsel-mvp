@extends('public.layouts.app')

@section('title', 'Galeri — PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="gallery-heading">
        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">Dokumentasi organisasi</p>
        <h1 id="gallery-heading" class="mt-2 text-3xl font-bold tracking-tight">Galeri</h1>
        <div class="mt-8 space-y-8">
            @forelse ($galleries as $gallery)
                <article class="rounded-xl border border-stone-200 bg-white p-5">
                    <h2 class="text-xl font-semibold">{{ $gallery->title }}</h2>
                    @if ($gallery->description)
                        <p class="mt-2 text-stone-600">{{ $gallery->description }}</p>
                    @endif
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="Media {{ $gallery->title }}">
                        @foreach ($gallery->media as $media)
                            <li>
                                <img class="aspect-video w-full rounded-lg object-cover" src="{{ route('public.media.show', $media) }}" alt="{{ $media->alt_text }}" loading="lazy">
                            </li>
                        @endforeach
                    </ul>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-stone-300 bg-white p-6 text-stone-600">Belum ada album yang diterbitkan.</p>
            @endforelse
        </div>
        <div class="mt-8">{{ $galleries->links() }}</div>
    </section>
@endsection
