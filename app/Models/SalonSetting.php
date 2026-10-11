<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalonSetting extends Model
{
    protected $fillable = [
        'salon_name', 'hero_headline', 'hero_sub', 'featured_services',
        'testimonials', 'faqs', 'contact_phone', 'contact_address',
        'map_query', 'cancellation_policy', 'open_time', 'close_time',
    ];

    protected function casts(): array
    {
        return [
            'featured_services' => 'array',
            'testimonials' => 'array',
            'faqs' => 'array',
        ];
    }

    /** There's only ever one row: fetch it, or create sensible defaults. */
    public static function current(): self
    {
        $settings = static::firstOrCreate([], [
            'hero_headline' => 'A salon that plans your whole visit, not just your appointment',
            'hero_sub' => 'Book with your favorite stylist, track your visit history, and see exactly what your next appointment will cost.',
            'featured_services' => [],
            'testimonials' => [],
            'faqs' => [],
        ]);

        // A freshly inserted row only holds the attributes we passed in; reload it so
        // column defaults (salon_name, open_time, close_time) are present on the very
        // first request after a new install instead of null.
        return $settings->wasRecentlyCreated ? $settings->refresh() : $settings;
    }
}
