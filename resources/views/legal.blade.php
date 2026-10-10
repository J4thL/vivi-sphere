@extends('layouts.app')

@section('title', $title . ' — Gilt & Grace Salon')

@section('content')
    <div class="max-w-2xl mx-auto px-6 py-12">
        <a href="{{ url('/') }}" class="text-subtle text-xs mb-6 inline-block">← Back to site</a>
        <div class="font-serif text-2xl mb-2">{{ $title }}</div>
        <div class="bg-yellow-50 text-yellow-800 text-xs rounded-md px-3 py-2 mb-6">
            This is placeholder legal text for demo purposes. Have an actual lawyer review real terms before using this with real customer data.
        </div>
        <div class="text-subtle text-sm flex flex-col gap-4">
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
