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
                <div class="organization-toolbar mb-6" role="search">
                    <label for="organization-search" class="sr-only">Cari unit organisasi</label>
                    <input
                        id="organization-search"
                        type="search"
                        class="organization-search w-full max-w-sm px-3 py-2.5"
                        placeholder="Cari unit organisasi…"
                        autocomplete="off"
                    >
                    <div class="organization-filters" role="group" aria-label="Filter jenis unit">
                        <button type="button" class="organization-filter" data-tree-filter="all" aria-pressed="true">Semua</button>
                        @foreach (\App\OrganizationUnitType::cases() as $type)
                            <button type="button" class="organization-filter" data-tree-filter="{{ $type->value }}" aria-pressed="false">{{ ucfirst($type->value) }}</button>
                        @endforeach
                    </div>
                    <button type="button" class="organization-filter" data-tree-expand-all>Buka semua</button>
                    <button type="button" class="organization-filter" data-tree-collapse-all>Tutup semua</button>
                    <p class="organization-result mt-2 text-sm text-stone-400" role="status" aria-live="polite"></p>
                </div>
                <ol id="organization-tree" class="organization-tree space-y-4" aria-label="Struktur organisasi">
                    @foreach ($units as $unit)
                        @include('public.organization.unit', ['unit' => $unit, 'depth' => 0])
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
@endsection
