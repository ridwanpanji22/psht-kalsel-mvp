@extends('public.layouts.app')

@section('title', 'Tempat Latihan - PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="locations-heading">
        <header class="psht-page-heading">
            <p class="psht-kicker">Lokasi terverifikasi</p>
            <h1 id="locations-heading" class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Tempat latihan</h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-stone-300">Pilih tempat latihan untuk membuka alamat lengkap, jadwal, dan peta lokasinya.</p>
        </header>

        <div class="pt-8">
            @if ($locations->isEmpty())
                <p class="psht-card border-dashed p-6 text-stone-400">Belum ada tempat latihan yang diterbitkan.</p>
            @else
                <ul class="grid gap-4 sm:grid-cols-2">
                    @foreach ($locations as $location)
                        <li>
                            <a href="{{ route('public.training-locations.show', $location->slug) }}" class="psht-card block h-full p-5 transition hover:border-psht-bright">
                                <h2 class="text-xl font-semibold text-white">{{ $location->name }}</h2>
                                <p class="mt-2 text-stone-400">{{ $location->address }}</p>
                                @if ($location->schedule)
                                    <p class="mt-3 text-sm text-stone-300"><strong class="text-stone-400">Jadwal:</strong> {{ $location->schedule }}</p>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div id="training-map" class="mt-8 overflow-hidden rounded-xl border border-white/10" role="region" aria-label="Peta tempat latihan"></div>
        <p class="mt-2 text-sm text-stone-400">Peta menampilkan titik lokasi latihan terverifikasi. Klik penanda untuk melihat nama dan alamatnya.</p>
            @endif
        </div>
    </section>
@endsection

@if ($locations->isNotEmpty())
    @php
        $locationMapData = $locations->map(fn ($location) => [
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ])->values();
    @endphp
    @push('scripts')
        <script type="application/json" id="training-location-data">@json($locationMapData)</script>
    @endpush
@endif
