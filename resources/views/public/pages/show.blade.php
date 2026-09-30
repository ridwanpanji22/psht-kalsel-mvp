@extends('public.layouts.app')

@section('title', $page->title.' — PSHT Kalimantan Selatan')

@section('content')
    <article class="mx-auto max-w-3xl rounded-xl border border-stone-200 bg-white p-6 sm:p-10">
        <h1 class="text-3xl font-bold tracking-tight">{{ $page->title }}</h1>
        <div class="mt-6 whitespace-pre-line leading-8 text-stone-700">{{ $page->body }}</div>
    </article>
@endsection
