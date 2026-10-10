@extends('layouts.app')

@section('title', ($salonSettings->salon_name ?? 'Company Name') . ' — Book online')
@section('description', $salonSettings->hero_sub ?? '')

@section('content')
    <div class="pb-20 md:pb-0">

    {{-- Sticky header keeps the CTA reachable the whole time someone is scrolling --}}
    <header class="sticky top-0 z-40 flex items-center justify-between px-6 md:px-16 py-4 border-b border-line bg-white/95 backdrop-blur">
        <div class="font-serif text-wine text-xl md:text-2xl">{{ $salonSettings->salon_name }}</div>
        <nav class="hidden md:flex items-center gap-6 text-sm text-subtle">
            <a href="#services" class="hover:text-ink">Services</a>
            <a href="#team" class="hover:text-ink">Team</a>
            @if ($reviews->count())<a href="#reviews" class="hover:text-ink">Reviews</a>@endif
            <a href="#contact" class="hover:text-ink">Contact</a>
        </nav>
        <div class="flex items-center gap-3">
            <a href="{{ url('/app/login') }}" class="hidden sm:block text-wine text-sm font-medium px-3 py-2">Log in</a>
            <a href="{{ url('/app/register') }}" class="bg-wine text-white text-sm font-medium px-4 py-2 rounded-md">Book now</a>
        </div>
    </header>

    {{-- Hero: benefit-driven headline, trust signal, dual CTA --}}
    <section class="px-6 md:px-16 py-14 md:py-24 grid md:grid-cols-2 gap-10 items-center bg-rosesoft">
        <div>
            @if ($reviewCount > 0)
                <div class="flex items-center gap-2 mb-4 text-sm">
                    <span class="text-wine tracking-tight">{{ str_repeat('★', round($avgRating)) }}{{ str_repeat('☆', 5 - round($avgRating)) }}</span>
                    <span class="text-subtle">{{ $avgRating }} from {{ $reviewCount }} {{ Str::plural('client', $reviewCount) }}</span>
                </div>
            @endif
            <h1 class="font-serif text-3xl md:text-5xl leading-tight mb-5">{{ $salonSettings->hero_headline }}</h1>
            <p class="text-subtle text-base md:text-lg mb-8 max-w-md">{{ $salonSettings->hero_sub }}</p>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ url('/app/register') }}" class="bg-wine text-white px-6 py-3 rounded-md text-sm font-medium">Book your appointment</a>
                <a href="#services" class="border border-wine text-wine px-6 py-3 rounded-md text-sm font-medium">See services & pricing</a>
            </div>
        </div>
        <div class="bg-white border border-line rounded-lg p-6 shadow-sm">
            <div class="font-serif text-lg mb-4">This week's popular services</div>
            <div class="flex flex-col gap-3">
                @foreach ($featuredServices->take(4) as $service)
                    <div class="flex items-center justify-between text-sm">
                        <span>{{ $service->name }}</span>
                        <span class="text-wine font-medium">${{ number_format($service->price, 0) }}</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ url('/app/register') }}" class="mt-5 block text-center bg-wine text-white px-4 py-2.5 rounded-md text-sm font-medium">Book one of these</a>
        </div>
    </section>

    {{-- Scannable benefit highlights: why book here instead of anywhere else --}}
    <section class="px-6 md:px-16 py-14 grid sm:grid-cols-3 gap-8">
        <div>
            <svg class="w-8 h-8 text-wine mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15M9 8.25V6a3 3 0 013-3v0a3 3 0 013 3v2.25M9 8.25h6" /></svg>
            <div class="font-medium mb-1">Upfront pricing, always</div>
            <div class="text-subtle text-sm">Every price is listed before you book. What you see is what you pay in the chair - no surprises at checkout.</div>
        </div>
        <div>
            <svg class="w-8 h-8 text-wine mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
            <div class="font-medium mb-1">Pick your stylist</div>
            <div class="text-subtle text-sm">Browse the team and their specialties, then book directly with the person you trust - or let us match you.</div>
        </div>
        <div>
            <svg class="w-8 h-8 text-wine mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 21.539a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
            <div class="font-medium mb-1">Reviews from real visits</div>
            <div class="text-subtle text-sm">Every review here is tied to a completed appointment - no fake five-star padding, just honest feedback.</div>
        </div>
    </section>

    {{-- Services --}}
    <section id="services" class="px-6 md:px-16 py-14 scroll-mt-16">
        <div class="font-serif text-2xl md:text-3xl mb-2">What we offer</div>
        <div class="text-subtle text-sm mb-8 max-w-lg">Every price is exactly what you'll pay in the chair, no surprises at checkout.</div>
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
            @foreach ($featuredServices as $service)
                <div class="bg-white border border-line rounded-lg shadow-sm overflow-hidden flex flex-col gap-2">
                    @if ($service->photo_url)
                        <img src="{{ $service->photo_url }}" alt="{{ $service->name }}" loading="lazy" class="w-full h-36 object-cover">
                    @endif
                    <div class="p-5 pt-4 flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <div class="font-medium">{{ $service->name }}</div>
                            <div class="text-wine font-medium">${{ number_format($service->price, 0) }}</div>
                        </div>
                        <div class="text-subtle text-xs">~{{ $service->duration_minutes }} min</div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Team --}}
    <section id="team" class="px-6 md:px-16 py-14 bg-rosesoft scroll-mt-16">
        <div class="font-serif text-2xl md:text-3xl mb-8">Meet the team</div>
        <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($stylists as $stylist)
                <a href="{{ url('/stylists/' . $stylist->id) }}" class="bg-white border border-line rounded-lg p-5 shadow-sm text-center block">
                    @if ($stylist->photo_url)
                        <img src="{{ $stylist->photo_url }}" alt="{{ $stylist->name }}" loading="lazy" class="w-16 h-16 rounded-full object-cover mx-auto mb-3">
                    @else
                        <div class="w-16 h-16 rounded-full bg-winesoft text-wine flex items-center justify-center text-lg font-medium mx-auto mb-3">
                            {{ collect(explode(' ', $stylist->name))->map(fn($p) => $p[0])->take(2)->implode('') }}
                        </div>
                    @endif
                    <div class="font-medium text-sm">{{ $stylist->name }}</div>
                    <div class="text-subtle text-xs">{{ $stylist->role }}</div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Reviews --}}
    @if ($reviews->count())
        <section id="reviews" class="px-6 md:px-16 py-14 scroll-mt-16">
            <div class="flex items-center justify-between flex-wrap gap-2 mb-8">
                <div class="font-serif text-2xl md:text-3xl">What clients say</div>
                @if ($reviewCount > 0)
                    <div class="text-sm text-subtle">{{ $avgRating }} average · {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</div>
                @endif
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                @foreach ($reviews as $review)
                    <div class="bg-white border border-line rounded-lg p-5 shadow-sm">
                        <div class="text-wine text-sm mb-2">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                        <p class="text-sm mb-3">"{{ $review->comment }}"</p>
                        <div class="text-subtle text-xs font-medium">{{ $review->client->name }} · {{ $review->stylist->name }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    @if (!empty($salonSettings->faqs))
        <section class="px-6 md:px-16 py-14 max-w-2xl bg-rosesoft">
            <div class="font-serif text-2xl md:text-3xl mb-8">Frequently asked</div>
            <div class="flex flex-col gap-2">
                @foreach ($salonSettings->faqs as $faq)
                    <details class="border border-line rounded-md bg-white">
                        <summary class="px-4 py-3 text-sm font-medium cursor-pointer">{{ $faq['question'] }}</summary>
                        <div class="px-4 pb-3 text-sm text-subtle">{{ $faq['answer'] }}</div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Final push before contact/footer --}}
    <section class="px-6 md:px-16 py-14 bg-wine text-white text-center">
        <div class="font-serif text-2xl md:text-3xl mb-3">Ready to book?</div>
        <p class="text-white/80 text-sm mb-6 max-w-md mx-auto">Takes less than a minute, and you'll know exactly what you're paying before you sit down.</p>
        <a href="{{ url('/app/register') }}" class="inline-block bg-white text-wine px-6 py-3 rounded-md text-sm font-medium">Book your appointment</a>
    </section>

    {{-- Contact --}}
    <section id="contact" class="px-6 md:px-16 py-14 grid md:grid-cols-2 gap-8 scroll-mt-16">
        <div>
            <div class="font-serif text-2xl mb-4">Visit us</div>
            <div class="text-sm mb-2">{{ $salonSettings->contact_address }}</div>
            <div class="text-sm mb-4">{{ $salonSettings->contact_phone }}</div>
            <div class="text-subtle text-xs">{{ $salonSettings->cancellation_policy }}</div>
        </div>
        <div class="border border-line rounded-lg overflow-hidden h-56 md:h-full min-h-[220px]">
            <iframe title="Salon location map" width="100%" height="100%" style="border:0" loading="lazy"
                src="https://maps.google.com/maps?q={{ urlencode($salonSettings->map_query ?? $salonSettings->contact_address) }}&output=embed"></iframe>
        </div>
    </section>

    <footer class="px-6 md:px-16 py-8 text-xs flex items-center justify-between flex-wrap gap-3 text-subtle border-t border-line">
        <span>© {{ date('Y') }} {{ $salonSettings->salon_name }}</span>
        <div class="flex gap-4">
            <a href="{{ url('/terms') }}" class="hover:underline">Terms of service</a>
            <a href="{{ url('/privacy') }}" class="hover:underline">Privacy policy</a>
        </div>
        <span>{{ $salonSettings->contact_address }} · {{ $salonSettings->contact_phone }}</span>
    </footer>

    </div>

    {{-- Mobile-only sticky CTA bar so "Book now" is always one tap away on phones --}}
    <div class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-line p-3 pb-safe">
        <a href="{{ url('/app/register') }}" class="block text-center bg-wine text-white px-4 py-3 rounded-md text-sm font-medium">Book your appointment</a>
    </div>
@endsection
