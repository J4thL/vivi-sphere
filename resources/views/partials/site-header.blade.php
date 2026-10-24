@php
    // On the landing page anchors scroll in place; on other pages they jump back to it.
    $home = request()->routeIs('landing') ? '' : url('/');
    $links = [
        ['Services', $home . '#services'],
        ['Team', $home . '#team'],
    ];
    if (isset($branches) && $branches->count() > 1) {
        array_splice($links, 1, 0, [['Branches', $home . '#branches']]);
    }
    if (isset($reviews) && request()->routeIs('landing') && $reviews->count()) {
        $links[] = ['Reviews', '#reviews'];
    }
    $links[] = ['Contact', $home . '#contact'];
    $initial = mb_strtoupper(mb_substr($salonSettings->salon_name, 0, 1));
@endphp
<header class="sticky top-0 z-40 border-b border-line bg-white/90 backdrop-blur-md">
    <div class="container-page flex h-16 items-center justify-between gap-4">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5" aria-label="{{ $salonSettings->salon_name }} home">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-wine font-serif text-lg font-semibold text-white">{{ $initial }}</span>
            <span class="font-serif text-xl text-wine md:text-2xl">{{ $salonSettings->salon_name }}</span>
        </a>

        <nav class="hidden items-center gap-1 md:flex" aria-label="Main">
            @foreach ($links as [$label, $href])
                <a href="{{ $href }}" class="nav-link">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ url('/app/login') }}" class="btn btn-ghost hidden sm:inline-flex">Log in</a>
            <a href="{{ url('/app/register') }}" class="btn btn-primary hidden sm:inline-flex">Book now</a>

            {{-- Mobile menu: native <details>, no JavaScript needed --}}
            <details class="group relative md:hidden">
                <summary class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg border border-line text-wine transition hover:bg-winesoft" aria-label="Open menu">
                    <svg class="h-5 w-5 group-open:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg class="hidden h-5 w-5 group-open:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </summary>
                <div class="card absolute right-0 top-12 w-60 p-2 shadow-lift">
                    @foreach ($links as [$label, $href])
                        <a href="{{ $href }}" class="nav-link block">{{ $label }}</a>
                    @endforeach
                    <div class="my-2 border-t border-line"></div>
                    <a href="{{ url('/app/login') }}" class="nav-link block">Log in</a>
                </div>
            </details>
        </div>
    </div>
</header>
{{-- Closes the mobile menu after tapping a link, pressing Esc, or tapping outside it. --}}
<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        var menu = document.querySelector('header details');
        if (!menu) return;
        var close = function () { menu.removeAttribute('open'); };
        menu.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', close); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        document.addEventListener('click', function (e) { if (!menu.contains(e.target)) close(); });
    })();
</script>
