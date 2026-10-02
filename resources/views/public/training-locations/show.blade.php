@extends('public.layouts.app')

@section('title', $location->name.' - Tempat Latihan PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="location-heading" class="pb-16 pt-10">
        <nav aria-label="Breadcrumb" class="text-sm text-stone-400">
            <a href="{{ route('public.home') }}" class="hover:text-white">Beranda</a>
            <span aria-hidden="true"> / </span>
            <a href="{{ route('public.training-locations.index') }}" class="hover:text-white">Tempat latihan</a>
            <span aria-hidden="true"> / </span>
            <span aria-current="page" class="text-stone-200">{{ $location->name }}</span>
        </nav>

        <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <article class="max-w-3xl">
                <p class="psht-kicker">Lokasi latihan</p>
                <h1 id="location-heading" class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $location->name }}</h1>

                <dl class="mt-8 space-y-5 border-t border-white/10 pt-6 text-stone-300">
                    @if ($location->organizationUnit?->name)
                        <div>
                            <dt class="text-sm text-stone-400">Unit pengelola</dt>
                            <dd class="mt-1">{{ $location->organizationUnit->name }}</dd>
                        </div>
                    @endif
                    @if ($location->address)
                        <div>
                            <dt class="text-sm text-stone-400">Alamat</dt>
                            <dd class="mt-1">{{ $location->address }}</dd>
                        </div>
                    @endif
                    @if ($location->schedule)
                        <div>
                            <dt class="text-sm text-stone-400">Jadwal</dt>
                            <dd class="mt-1">{{ $location->schedule }}</dd>
                        </div>
                    @endif
                    @if ($location->contact)
                        <div>
                            <dt class="text-sm text-stone-400">Kontak</dt>
                            <dd class="mt-1">{{ $location->contact }}</dd>
                        </div>
                    @endif
                </dl>
            </article>

            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <section class="psht-card overflow-hidden" aria-labelledby="location-map-heading">
                    <div class="p-5">
                        <p class="psht-kicker">Lokasi</p>
                        <h2 id="location-map-heading" class="mt-2 text-lg font-bold text-white">Peta lokasi</h2>
                    </div>
                    <div id="training-map-detail" class="border-t border-white/10" role="region" aria-label="Peta tempat latihan"></div>
                </section>
                <p class="text-xs text-stone-400">© OpenStreetMap contributors.</p>
                <a href="{{ route('public.training-locations.index') }}" class="inline-flex min-h-11 items-center rounded-lg bg-psht-blue px-4 py-3 font-semibold text-white transition hover:bg-psht-bright">Kembali ke semua tempat latihan</a>
            </aside>
        </div>
    </section>
@endsection

@php
    $locationDetailData = [
        'name' => $location->name,
        'address' => $location->address,
        'latitude' => $location->latitude,
        'longitude' => $location->longitude,
    ];
@endphp
@push('scripts')
    <script type="application/json" id="training-location-detail-data">@json($locationDetailData)</script>
@endpush
