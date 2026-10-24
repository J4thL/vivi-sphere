@extends('layouts.app')

@section('title', $stylist->name . ' — ' . $salonSettings->salon_name)
@section('description', $stylist->bio ?? ($stylist->name . ' at ' . $salonSettings->salon_name))

@section('content')
    <div class="container-page max-w-3xl py-10 md:py-16">
        <a href="{{ url('/') }}#team" class="mb-8 inline-flex items-center gap-1.5 text-sm font-medium text-subtle transition hover:text-wine">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to the team
        </a>

        <section class="card mb-10 flex flex-col items-center gap-6 p-8 text-center sm:flex-row sm:items-center sm:text-left">
            @if ($stylist->photo_url)
                <img src="{{ $stylist->photo_url }}" alt="{{ $stylist->name }}" class="h-28 w-28 shrink-0 rounded-full object-cover ring-4 ring-winesoft">
            @else
                <div class="flex h-28 w-28 shrink-0 items-center justify-center rounded-full bg-winesoft font-serif text-4xl text-wine ring-4 ring-rosesoft">
                    {{ collect(explode(' ', $stylist->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                </div>
            @endif
            <div class="flex-1">
                <h1 class="font-serif text-3xl md:text-4xl">{{ $stylist->name }}</h1>
                <div class="mb-3 text-subtle">{{ $stylist->role }}</div>
                @if ($stylist->bio)
                    <p class="leading-relaxed text-subtle">{{ $stylist->bio }}</p>
                @endif
            </div>
            <a href="{{ url('/app/register') }}" class="btn btn-primary w-full shrink-0 sm:w-auto">Book with {{ \Illuminate\Support\Str::before($stylist->name, ' ') }}</a>
        </section>

        <section>
            <h2 class="mb-6 font-serif text-2xl">Client reviews</h2>
            @if ($reviews->count())
                <div class="flex flex-col gap-4">
                    @foreach ($reviews as $review)
                        <figure class="card p-6">
                            @include('partials.stars', ['rating' => $review->rating])
                            <blockquote class="mt-3 leading-relaxed">“{{ $review->comment }}”</blockquote>
                        </figure>
                    @endforeach
                </div>
            @else
                <p class="card p-6 text-sm text-subtle">No reviews yet. Be the first to book and share your experience.</p>
            @endif
        </section>
    </div>
@endsection
