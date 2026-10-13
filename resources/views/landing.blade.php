@extends('layouts.app')

@section('title', ($salonSettings->salon_name ?? 'Company Name') . ' — Book online')
@section('description', $salonSettings->hero_sub ?? '')

@php
    $fmtTime = fn ($t) => \Carbon\Carbon::parse($t)->format('g:i A');
@endphp

@section('content')

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-rosesoft via-cream to-cream">
        <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-rose/20 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -left-32 bottom-0 h-80 w-80 rounded-full bg-wine/10 blur-3xl" aria-hidden="true"></div>

        <div class="container-page relative grid items-center gap-12 py-16 md:grid-cols-[1.15fr_1fr] md:py-28">
            <div>
                @if ($reviewCount > 0)
                    <div class="chip mb-6">
                        @include('partials.stars', ['rating' => $avgRating, 'size' => 'h-3.5 w-3.5'])
                        <span>{{ $avgRating }} from {{ $reviewCount }} {{ Str::plural('client', $reviewCount) }}</span>
                    </div>
                @endif
                <h1 class="mb-6 text-4xl font-medium leading-[1.08] md:text-6xl">{{ $salonSettings->hero_headline }}</h1>
                <p class="lead mb-9 max-w-lg">{{ $salonSettings->hero_sub }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ url('/app/register') }}" class="btn btn-primary btn-lg">Book your appointment</a>
                    <a href="#services" class="btn btn-secondary btn-lg">See services &amp; pricing</a>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-subtle">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 text-wine" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        Upfront pricing
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 text-wine" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        Book in under a minute
                    </span>
                </div>
            </div>

            <div class="card p-6 shadow-lift md:p-8">
                <div class="mb-1 text-xs font-semibold uppercase tracking-[0.18em] text-wine">Popular right now</div>
                <div class="mb-5 font-serif text-2xl">This week's favorite services</div>
                <ul class="divide-y divide-line">
                    @foreach ($featuredServices->take(4) as $service)
                        <li class="flex items-center justify-between gap-4 py-3.5 text-sm">
                            <span class="font-medium">{{ $service->name }}</span>
                            <span class="font-serif text-lg text-wine">₱{{ number_format($service->price, 0) }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ url('/app/register') }}" class="btn btn-primary mt-6 w-full">Book one of these</a>
            </div>
        </div>
    </section>

    {{-- Benefits --}}
    <section class="border-y border-line bg-white">
        <div class="container-page grid gap-10 py-14 sm:grid-cols-3 md:py-16">
            @php
                $benefits = [
                    ['Upfront pricing, always', 'Every price is listed before you book. What you see is what you pay in the chair - no surprises at checkout.', 'M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15M9 8.25V6a3 3 0 013-3v0a3 3 0 013 3v2.25M9 8.25h6'],
                    ['Pick your stylist', 'Browse the team and their specialties, then book directly with the person you trust - or let us match you.', 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
                    ['Reviews from real visits', 'Every review here is tied to a completed appointment - no fake five-star padding, just honest feedback.', 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 21.539a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z'],
                ];
            @endphp
            @foreach ($benefits as [$title, $text, $icon])
                <div class="flex gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-winesoft text-wine">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <div>
                        <div class="mb-1 font-semibold">{{ $title }}</div>
                        <p class="text-sm leading-relaxed text-subtle">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Services --}}
    <section id="services" class="section scroll-mt-16">
        <div class="container-page">
            <div class="section-head">
                <div class="eyebrow">Services</div>
                <h2 class="h2 mb-3">What we offer</h2>
                <p class="lead">Every price is exactly what you'll pay in the chair, no surprises at checkout.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredServices as $service)
                    <article class="card card-hover flex flex-col overflow-hidden">
                        @if ($service->photo_url)
                            <img src="{{ $service->photo_url }}" alt="{{ $service->name }}" loading="lazy" class="h-44 w-full object-cover">
                        @endif
                        <div class="flex flex-1 flex-col gap-3 p-6">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="text-xl font-medium leading-snug">{{ $service->name }}</h3>
                                <div class="font-serif text-2xl text-wine">₱{{ number_format($service->price, 0) }}</div>
                            </div>
                            <div class="mt-auto inline-flex items-center gap-1.5 text-sm text-subtle">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                                About {{ $service->duration_minutes }} min
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Team --}}
    <section id="team" class="section scroll-mt-16 bg-rosesoft">
        <div class="container-page">
            <div class="section-head">
                <div class="eyebrow">Our team</div>
                <h2 class="h2">Meet the team</h2>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($stylists as $stylist)
                    <a href="{{ url('/stylists/' . $stylist->id) }}" class="card card-hover group block p-6 text-center">
                        @if ($stylist->photo_url)
                            <img src="{{ $stylist->photo_url }}" alt="{{ $stylist->name }}" loading="lazy" class="mx-auto mb-4 h-20 w-20 rounded-full object-cover ring-4 ring-winesoft">
                        @else
                            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-winesoft font-serif text-2xl text-wine ring-4 ring-rosesoft">
                                {{ collect(explode(' ', $stylist->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                            </div>
                        @endif
                        <div class="font-semibold">{{ $stylist->name }}</div>
                        <div class="mb-3 text-sm text-subtle">{{ $stylist->role }}</div>
                        <span class="inline-flex items-center gap-1 text-sm font-medium text-wine">
                            View profile
                            <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Reviews --}}
    @if ($reviews->count())
        <section id="reviews" class="section scroll-mt-16">
            <div class="container-page">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4 md:mb-14">
                    <div>
                        <div class="eyebrow">Reviews</div>
                        <h2 class="h2">What clients say</h2>
                    </div>
                    @if ($reviewCount > 0)
                        <div class="flex items-center gap-2 text-sm text-subtle">
                            @include('partials.stars', ['rating' => $avgRating])
                            <span>{{ $avgRating }} average · {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</span>
                        </div>
                    @endif
                </div>
                <div class="grid gap-5 md:grid-cols-3">
                    @foreach ($reviews as $review)
                        <figure class="card flex flex-col p-6">
                            @include('partials.stars', ['rating' => $review->rating])
                            <blockquote class="my-4 flex-1 leading-relaxed">“{{ $review->comment }}”</blockquote>
                            <figcaption class="flex items-center gap-3 border-t border-line pt-4">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-winesoft text-xs font-semibold text-wine">{{ mb_substr($review->client->public_name, 0, 1) }}</span>
                                <span class="text-sm">
                                    <span class="block font-medium">{{ $review->client->public_name }}</span>
                                    <span class="text-subtle">with {{ $review->stylist->name }}</span>
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    @if (!empty($salonSettings->faqs))
        <section class="section bg-white border-y border-line">
            <div class="container-page max-w-3xl">
                <div class="section-head center">
                    <div class="eyebrow">FAQ</div>
                    <h2 class="h2">Frequently asked</h2>
                </div>
                <div class="flex flex-col gap-3">
                    @foreach ($salonSettings->faqs as $faq)
                        <details class="group card px-5 py-1 open:shadow-lift">
                            <summary class="flex cursor-pointer items-center justify-between gap-4 py-4 font-medium">
                                {{ $faq['question'] }}
                                <svg class="h-5 w-5 shrink-0 text-wine transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <p class="pb-4 leading-relaxed text-subtle">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Final call to action --}}
    <section class="relative overflow-hidden bg-wine text-white">
        <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-rose/30 blur-3xl" aria-hidden="true"></div>
        <div class="container-page relative py-16 text-center md:py-20">
            <h2 class="mb-3 font-serif text-3xl md:text-4xl">Ready to book?</h2>
            <p class="mx-auto mb-8 max-w-md text-white/80">Takes less than a minute, and you'll know exactly what you're paying before you sit down.</p>
            <a href="{{ url('/app/register') }}" class="btn btn-light btn-lg">Book your appointment</a>
        </div>
    </section>

    {{-- Contact --}}
    <section id="contact" class="section scroll-mt-16">
        <div class="container-page grid gap-8 md:grid-cols-2">
            <div>
                <div class="eyebrow">Contact</div>
                <h2 class="h2 mb-8">Visit us</h2>
                <ul class="space-y-5">
                    <li class="flex gap-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-winesoft text-wine"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                        <div><div class="text-xs font-semibold uppercase tracking-[0.14em] text-subtle">Address</div>{{ $salonSettings->contact_address }}</div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-winesoft text-wine"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2.3a1 1 0 011 .8l.7 3.3a1 1 0 01-.5 1.1l-1.6 1a11 11 0 005 5l1-1.6a1 1 0 011.1-.5l3.3.7a1 1 0 01.8 1V19a2 2 0 01-2 2A16 16 0 013 5z"/></svg></span>
                        <div><div class="text-xs font-semibold uppercase tracking-[0.14em] text-subtle">Phone</div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $salonSettings->contact_phone) }}" class="hover:text-wine">{{ $salonSettings->contact_phone }}</a></div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-winesoft text-wine"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg></span>
                        <div><div class="text-xs font-semibold uppercase tracking-[0.14em] text-subtle">Hours</div>Daily, {{ $fmtTime($salonSettings->open_time) }} – {{ $fmtTime($salonSettings->close_time) }}</div>
                    </li>
                </ul>
                @if ($salonSettings->cancellation_policy)
                    <p class="mt-8 rounded-xl bg-rosesoft px-4 py-3 text-sm leading-relaxed text-subtle">{{ $salonSettings->cancellation_policy }}</p>
                @endif
            </div>
            <div class="card min-h-[280px] overflow-hidden md:min-h-[360px]">
                <iframe title="Salon location map" width="100%" height="100%" class="h-full min-h-[280px] w-full border-0 md:min-h-[360px]" loading="lazy"
                    src="https://maps.google.com/maps?q={{ urlencode($salonSettings->map_query ?? $salonSettings->contact_address) }}&output=embed"></iframe>
            </div>
        </div>
    </section>
@endsection
