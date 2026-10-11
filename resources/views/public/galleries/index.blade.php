@extends('public.layouts.app')

@section('title', 'Galeri - PSHT Kalimantan Selatan')

@php
    $categories = [
        'all' => 'Semua',
        'latihan' => 'Latihan',
        'kegiatan' => 'Kegiatan',
        'pembinaan' => 'Pembinaan',
    ];
@endphp

@section('content')
    <section aria-labelledby="gallery-heading">
        <header class="psht-page-heading">
            <p class="psht-kicker">Dokumentasi organisasi</p>
            <h1 id="gallery-heading" class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Galeri dokumentasi</h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-stone-300">Album publik dari kegiatan dan pembinaan PSHT Kalimantan Selatan.</p>
        </header>

        <div class="pt-8">
            <div class="flex flex-wrap gap-2" role="group" aria-label="Filter kategori album">
                @foreach ($categories as $value => $label)
                    <a href="{{ route('public.galleries.index', ['category' => $value]) }}"
                       class="psht-control"
                       data-gallery-filter="{{ $value }}"
                       @if ($category === $value) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>

            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($galleries as $gallery)
                    @php $cover = $gallery->media->first(); @endphp

                    <li>
                        <article class="psht-card flex h-full flex-col overflow-hidden transition hover:border-psht-bright focus-within:border-psht-bright">
                            @if ($cover)
                                <button type="button"
                                        class="group block w-full cursor-pointer"
                                        data-gallery-open
                                        data-media-label="{{ $cover->alt_text ?: $gallery->title }}">
                                    <img class="aspect-video w-full object-cover transition group-hover:opacity-90" src="{{ route('public.media.show', $cover) }}" alt="{{ $cover->alt_text ?: 'Media album '.$gallery->title }}" loading="lazy">
                                </button>
                            @else
                                <div class="flex aspect-video w-full items-center justify-center border-b border-dashed border-white/15 p-4 text-center text-sm text-stone-400">
                                    Belum ada media publik pada album ini.
                                </div>
                            @endif

                            <div class="flex flex-1 flex-col p-5">
                                @if ($gallery->category)
                                    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-300">{{ $gallery->category->label() }}</span>
                                @endif
                                <h2 class="mt-2 text-xl font-semibold text-white">{{ $gallery->title }}</h2>
                                @if ($gallery->description)
                                    <p class="mt-2 text-sm text-stone-400">{{ $gallery->description }}</p>
                                @endif
                            </div>
                        </article>
                    </li>
                @empty
                    <li class="psht-card border-dashed p-6 text-stone-400 sm:col-span-2 lg:col-span-3">
                        <p>Belum ada album yang diterbitkan pada kategori ini.</p>
                        <a href="{{ route('public.galleries.index', ['category' => 'all']) }}" class="mt-3 inline-block font-medium text-amber-300 underline-offset-4 hover:underline">Lihat semua album</a>
                    </li>
                @endforelse
            </ul>

            <div class="mt-8">{{ $galleries->links() }}</div>
        </div>

        <dialog id="gallery-dialog" class="gallery-dialog" aria-label="Tampilan foto galeri">
            <form method="dialog" class="gallery-dialog__frame">
                {{-- Header row and navigation row are separate stacked siblings so
                     the close button and the prev/next controls never share one
                     absolutely positioned top row (which collided at 320px). --}}
                <div class="gallery-dialog__header">
                    <button type="submit" class="gallery-dialog__close" data-lightbox-close aria-label="Tutup foto">Tutup</button>
                </div>
                <div class="gallery-dialog__controls">
                    <button type="button" class="psht-control" data-lightbox-prev aria-label="Foto sebelumnya">Sebelumnya</button>
                    <button type="button" class="psht-control" data-lightbox-next aria-label="Foto berikutnya">Berikutnya</button>
                </div>
                <img data-gallery-dialog-image alt="">
                <p data-gallery-dialog-caption></p>
                <noscript><p>Tampilan foto memerlukan JavaScript. Semua foto tetap tersedia pada daftar album di atas.</p></noscript>
                <p data-gallery-dialog-status class="sr-only" aria-live="polite"></p>
            </form>
        </dialog>
    </section>
@endsection
