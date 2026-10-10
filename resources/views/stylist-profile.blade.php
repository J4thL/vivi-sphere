@extends('layouts.app')

@section('title', $stylist->name . ' — Gilt & Grace Salon')
@section('description', $stylist->bio ?? ($stylist->name . ' at Gilt & Grace Salon'))

@section('content')
    <div class="max-w-2xl mx-auto px-6 py-12">
        <a href="{{ url('/') }}" class="text-subtle text-xs mb-6 inline-block">← Back to site</a>
        <div class="flex items-center gap-4 mb-6">
            @if ($stylist->photo_url)
                <img src="{{ $stylist->photo_url }}" alt="{{ $stylist->name }}" class="w-20 h-20 rounded-full object-cover">
            @else
                <div class="w-20 h-20 rounded-full bg-winesoft text-wine flex items-center justify-center text-2xl font-medium">
                    {{ collect(explode(' ', $stylist->name))->map(fn($p) => $p[0])->take(2)->implode('') }}
                </div>
            @endif
            <div>
                <div class="font-serif text-2xl">{{ $stylist->name }}</div>
                <div class="text-subtle text-sm">{{ $stylist->role }}</div>
            </div>
        </div>

        @if ($stylist->bio)
            <p class="text-subtle text-sm mb-8">{{ $stylist->bio }}</p>
        @endif

        @if ($reviews->count())
            <div class="font-serif text-lg mb-4">Client reviews</div>
            <div class="flex flex-col gap-3">
                @foreach ($reviews as $review)
                    <div class="bg-white border border-line rounded-lg p-4">
                        <div class="text-wine text-xs mb-1">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                        <p class="text-sm">"{{ $review->comment }}"</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
