@extends('public.layouts.app')

@section('title', 'Struktur Organisasi - PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="structure-heading">
        <header class="psht-page-heading">
            <p class="psht-kicker">Hierarki formal</p>
            <h1 id="structure-heading" class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Struktur organisasi</h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-stone-300">Telusuri hubungan formal antarunit dan cabang pembina di Kalimantan Selatan.</p>
            <p class="mt-3 max-w-3xl text-sm text-stone-400">Cabang pembina ditampilkan sebagai relasi pembinaan, bukan induk formal.</p>
        </header>

        <div class="pt-10">
            @if ($units->isEmpty())
                <p class="psht-card border-dashed p-6 text-stone-400">Struktur belum diterbitkan.</p>
            @else
                <ol class="organization-tree space-y-4">
                    @foreach ($units as $unit)
                        <li>@include('public.organization.unit', ['unit' => $unit, 'depth' => 0])</li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
@endsection
