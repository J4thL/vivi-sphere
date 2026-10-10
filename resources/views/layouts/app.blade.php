<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Vivi - Sphere')</title>
    <meta name="description" content="@yield('description', 'Book with your favorite stylist at Vivi - Sphere.')">
    <meta name="theme-color" content="#7A3956">
    <meta property="og:title" content="@yield('title', 'Vivi - Sphere')">
    <meta property="og:description" content="@yield('description', 'Book with your favorite stylist at Vivi - Sphere.')">
    <meta property="og:type" content="website">
    {{-- Replace with a real hosted image once you have salon photography --}}
    {{-- <meta property="og:image" content="{{ asset('images/og-cover.jpg') }}"> --}}

    {{-- Inline SVG favicon: no extra request, always on-brand --}}
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%237A3956'/%3E%3Ctext x='16' y='22.5' font-family='Georgia,serif' font-size='19' font-weight='600' text-anchor='middle' fill='white'%3EV%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@500;600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite('resources/css/app.css')

    @if (isset($salonSettings))
        <script type="application/ld+json">
        {!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'HairSalon',
            'name' => $salonSettings->salon_name,
            'telephone' => $salonSettings->contact_phone,
            'address' => $salonSettings->contact_address,
            'openingHours' => 'Mo-Su ' . $salonSettings->open_time . '-' . $salonSettings->close_time,
            'aggregateRating' => isset($avgRating, $reviewCount) && $reviewCount > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => $avgRating,
                'reviewCount' => $reviewCount,
            ] : null,
        ])) !!}
        </script>
    @endif
</head>
<body class="min-h-screen bg-cream pb-20 font-sans text-ink md:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-wine focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">Skip to content</a>

    @isset($salonSettings)
        @include('partials.site-header')
    @endisset

    <main id="main">
        @yield('content')
    </main>

    @isset($salonSettings)
        @include('partials.site-footer')

        {{-- Mobile-only sticky CTA bar so "Book now" is always one tap away on phones --}}
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white/95 p-3 pb-safe backdrop-blur md:hidden">
            <a href="{{ url('/app/register') }}" class="btn btn-primary w-full py-3">Book your appointment</a>
        </div>
    @endisset
</body>
</html>
