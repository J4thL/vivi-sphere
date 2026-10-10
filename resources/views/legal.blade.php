@extends('layouts.app')

@section('title', $title . ' — ' . $salonSettings->salon_name)

@section('content')
    <div class="container-page max-w-3xl py-10 md:py-16">
        <a href="{{ url('/') }}" class="mb-8 inline-flex items-center gap-1.5 text-sm font-medium text-subtle transition hover:text-wine">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to site
        </a>
        <h1 class="mb-6 font-serif text-3xl md:text-4xl">{{ $title }}</h1>
        <div class="mb-8 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            This is placeholder legal text for demo purposes. Have an actual lawyer review real terms before using this with real customer data.
        </div>
        <div class="card flex flex-col gap-5 p-6 leading-relaxed text-subtle md:p-8">
            @if ($kind === 'terms')
                <p>By creating an account and booking appointments through {{ $salonSettings->salon_name }}, you agree to arrive on time for scheduled services and to follow the cancellation policy shown at booking.</p>
                <p>We reserve the right to refuse service and to adjust pricing for services rendered outside the scope of what was booked.</p>
                <p>Accounts are for personal use; sharing a login with others who book on your behalf is fine, but you're responsible for appointments made under your account.</p>
            @else
                <p>We collect your name, email, phone number, and appointment history to operate the booking system and communicate with you about your visits.</p>
                <p>We don't sell your information to third parties. Stylists can see the appointments assigned to them; front-desk staff can see full client records.</p>
                <p>You can request a copy of your data or ask us to delete your account by contacting the salon directly.</p>
            @endif
        </div>
    </div>
@endsection
