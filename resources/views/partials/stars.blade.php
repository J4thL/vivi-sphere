{{-- Usage: @include('partials.stars', ['rating' => 4, 'size' => 'h-4 w-4']) --}}
@php $size = $size ?? 'h-4 w-4'; $filled = (int) round($rating); @endphp
<span class="inline-flex items-center gap-0.5" role="img" aria-label="{{ $filled }} out of 5 stars">
    @for ($i = 1; $i <= 5; $i++)
        <svg class="{{ $size }} {{ $i <= $filled ? 'text-wine' : 'text-line' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.5l2.6 5.5 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6L1.4 7.8l6-.8L10 1.5z"/></svg>
    @endfor
</span>
