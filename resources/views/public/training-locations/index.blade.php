@extends('public.layouts.app')

@section('title', 'Tempat Latihan — PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="locations-heading">
        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">Lokasi terverifikasi</p>
        <h1 id="locations-heading" class="mt-2 text-3xl font-bold tracking-tight">Tempat latihan</h1>
        @if ($locations->isEmpty())
            <p class="mt-8 rounded-xl border border-dashed border-stone-300 bg-white p-6 text-stone-600">Belum ada tempat latihan yang diterbitkan.</p>
        @else
            <ul class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach ($locations as $location)
                    <li class="rounded-xl border border-stone-200 bg-white p-5">
                        <h2 class="text-xl font-semibold">{{ $location->name }}</h2>
                        <p class="mt-2 text-stone-600">{{ $location->address }}</p>
                        @if ($location->schedule)
                            <p class="mt-3 text-sm"><strong>Jadwal:</strong> {{ $location->schedule }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div id="training-map" class="mt-8 h-96 overflow-hidden rounded-xl border border-stone-200" aria-label="Peta tempat latihan"></div>
        @endif
    </section>
@endsection

@if ($locations->isNotEmpty())
    @push('scripts')
        <script type="application/json" id="training-location-data">@json($locations)</script>
    @endpush
@endif
