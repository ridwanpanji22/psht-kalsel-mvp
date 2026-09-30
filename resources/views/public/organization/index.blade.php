@extends('public.layouts.app')

@section('title', 'Struktur Organisasi — PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="structure-heading">
        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">Hierarki formal</p>
        <h1 id="structure-heading" class="mt-2 text-3xl font-bold tracking-tight">Struktur organisasi</h1>
        <p class="mt-3 max-w-3xl text-stone-600">Cabang pembina ditampilkan sebagai relasi pembinaan, bukan induk formal.</p>
        <div class="mt-8 space-y-4">
            @forelse ($units as $unit)
                @include('public.organization.unit', ['unit' => $unit, 'depth' => 0])
            @empty
                <p class="rounded-xl border border-dashed border-stone-300 bg-white p-6 text-stone-600">Struktur belum diterbitkan.</p>
            @endforelse
        </div>
    </section>
@endsection
