@extends('public.layouts.app')

@section('title', $page->title.' - PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="page-heading" class="pb-16 pt-10">
        <nav aria-label="Breadcrumb" class="text-sm text-stone-400">
            <a href="{{ route('public.home') }}" class="hover:text-white">Beranda</a>
            <span aria-hidden="true"> / </span>
            <span aria-current="page" class="text-stone-200">{{ $page->title }}</span>
        </nav>

        <article class="mt-8 max-w-3xl">
            <p class="psht-kicker">Profil organisasi</p>
            <h1 id="page-heading" class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $page->title }}</h1>

            @if (filled($page->body))
                <div class="psht-editorial psht-editorial-body mt-8 whitespace-pre-line leading-8 text-stone-300">
                    <p class="editorial-drop-cap">{{ $page->body }}</p>
                </div>
            @endif
        </article>
    </section>
@endsection
