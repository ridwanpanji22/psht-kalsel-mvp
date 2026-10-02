@extends('public.layouts.app')

@section('title', $location->name.' — Tempat Latihan PSHT Kalimantan Selatan')

@section('content')
    <section aria-labelledby="location-heading">
        <a href="{{ route('public.training-locations.index') }}" class="text-sm font-medium text-emerald-800 underline-offset-4 hover:underline">← Semua tempat latihan</a>
        <h1 id="location-heading" class="mt-4 text-3xl font-bold tracking-tight">{{ $location->name }}</h1>
        @if ($location->organizationUnit?->name)
            <p class="mt-2 text-sm font-medium text-emerald-800">{{ $location->organizationUnit->name }}</p>
        @endif
        @if ($location->address)
            <p class="mt-4 text-stone-600">{{ $location->address }}</p>
        @endif
        @if ($location->schedule)
            <p class="mt-3 text-sm"><strong>Jadwal:</strong> {{ $location->schedule }}</p>
        @endif
        @if ($location->contact)
            <p class="mt-3 text-sm"><strong>Kontak:</strong> {{ $location->contact }}</p>
        @endif
    </section>
@endsection
