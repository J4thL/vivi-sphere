<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Vivi - Sphere')</title>
    <meta name="description" content="@yield('description', 'Book with your favorite stylist at Vivi - Sphere.')">
    <meta property="og:title" content="@yield('title', 'Vivi - Sphere')">
    <meta property="og:description" content="@yield('description', 'Book with your favorite stylist at Vivi - Sphere.')">
    <meta property="og:type" content="website">
    {{-- Replace with a real hosted image once you have salon photography --}}
    {{-- <meta property="og:image" content="{{ asset('images/og-cover.jpg') }}"> --}}

    <link rel="preconnect" href="https://fonts.googleapis.com">
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
<body class="font-sans bg-white text-ink">
    @yield('content')
</body>
</html>
