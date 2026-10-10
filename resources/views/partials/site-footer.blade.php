<footer class="border-t border-line bg-white">
    <div class="container-page grid gap-8 py-12 md:grid-cols-3">
        <div>
            <div class="mb-2 font-serif text-xl text-wine">{{ $salonSettings->salon_name }}</div>
            <p class="max-w-xs text-sm leading-relaxed text-subtle">Book with the stylist you trust, see your price upfront, and keep your visit history in one place.</p>
        </div>
        <div>
            <div class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-ink">Explore</div>
            <ul class="space-y-2 text-sm text-subtle">
                <li><a href="{{ url('/') }}#services" class="transition hover:text-wine">Services &amp; pricing</a></li>
                <li><a href="{{ url('/') }}#team" class="transition hover:text-wine">Our team</a></li>
                <li><a href="{{ url('/app/login') }}" class="transition hover:text-wine">Log in</a></li>
                <li><a href="{{ url('/app/register') }}" class="transition hover:text-wine">Create an account</a></li>
            </ul>
        </div>
        <div>
            <div class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-ink">Visit</div>
            <ul class="space-y-2 text-sm text-subtle">
                <li>{{ $salonSettings->contact_address }}</li>
                <li>{{ $salonSettings->contact_phone }}</li>
            </ul>
        </div>
    </div>
    <div class="border-t border-line">
        <div class="container-page flex flex-wrap items-center justify-between gap-3 py-5 text-xs text-subtle">
            <span>© {{ date('Y') }} {{ $salonSettings->salon_name }}</span>
            <div class="flex gap-5">
                <a href="{{ url('/terms') }}" class="transition hover:text-wine">Terms of service</a>
                <a href="{{ url('/privacy') }}" class="transition hover:text-wine">Privacy policy</a>
            </div>
        </div>
    </div>
</footer>
