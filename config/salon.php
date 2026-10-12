<?php

/*
| Upper bounds so a growing salon cannot make list endpoints slower and bigger forever.
| Each can be changed in the environment without touching code.
*/
return [
    // Staff appointment lists, when no ?date/?from is given, start this many days back (future
    // bookings are always included). The dashboard's "Stylist leaderboard" is labelled to match.
    'appointments_window_days' => (int) env('SALON_APPOINTMENTS_WINDOW_DAYS', 180),

    // Manager client list: the highest spenders first, up to this many.
    'clients_max' => (int) env('SALON_CLIENTS_MAX', 2000),

    // Newest reviews returned to managers / publicly / on one stylist's profile page.
    'reviews_max' => (int) env('SALON_REVIEWS_MAX', 500),
    'public_reviews_max' => (int) env('SALON_PUBLIC_REVIEWS_MAX', 100),

    // Waiting-list entries shown to managers.
    'waitlist_max' => (int) env('SALON_WAITLIST_MAX', 500),
];
