<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Review;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SalonDemoSeeder extends Seeder
{
    public function run(): void
    {
        // --- Admin login ---------------------------------------------------
        $admin = User::create([
            'name' => 'Jules Colton',
            'email' => 'admin@giltandgrace.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // --- Stylists + one sample stylist login ----------------------------
        $stylistData = [
            ['name' => 'Marisol Reyes', 'role' => 'Colorist'],
            ['name' => 'Jasmine Cole', 'role' => 'Senior Stylist'],
            ['name' => 'Andre Boyd', 'role' => 'Barber'],
            ['name' => 'Priya Nair', 'role' => 'Nail Tech'],
        ];
        $stylists = collect($stylistData)->mapWithKeys(function ($s) {
            $stylist = Stylist::create(['name' => $s['name'], 'role' => $s['role'], 'active' => true]);
            return [$s['name'] => $stylist];
        });

        User::create([
            'name' => 'Marisol Reyes',
            'email' => 'marisol@giltandgrace.test',
            'password' => Hash::make('password'),
            'role' => 'stylist',
            'stylist_id' => $stylists['Marisol Reyes']->id,
        ]);

        // --- Services --------------------------------------------------------
        $serviceData = [
            'Haircut & Style' => ['price' => 65, 'duration' => 45],
            'Balayage' => ['price' => 180, 'duration' => 120],
            'Root Touch-Up' => ['price' => 95, 'duration' => 60],
            'Keratin Treatment' => ['price' => 220, 'duration' => 150],
            'Gel Manicure' => ['price' => 45, 'duration' => 45],
            'Beard Trim' => ['price' => 30, 'duration' => 20],
            'Deep Conditioning' => ['price' => 55, 'duration' => 30],
            'Bridal Updo' => ['price' => 150, 'duration' => 90],
        ];
        $services = collect($serviceData)->mapWithKeys(function ($info, $name) {
            return [$name => Service::create(['name' => $name, 'price' => $info['price'], 'duration_minutes' => $info['duration']])];
        });

        // --- Clients, including one with a client login ---------------------
        $clientData = [
            ['name' => 'Elena Marsh', 'phone' => '(415) 555-0138', 'visits' => 14, 'spend' => 1840],
            ['name' => 'Naomi Fisk', 'phone' => '(415) 555-0192', 'visits' => 22, 'spend' => 3120],
            ['name' => 'Carl Denton', 'phone' => '(415) 555-0173', 'visits' => 6, 'spend' => 420],
            ['name' => 'Priya Shah', 'phone' => '(415) 555-0110', 'visits' => 9, 'spend' => 980],
            ['name' => 'Wendell Kerr', 'phone' => '(415) 555-0157', 'visits' => 3, 'spend' => 195],
            ['name' => 'Grace Odom', 'phone' => '(415) 555-0129', 'visits' => 18, 'spend' => 1560],
            ['name' => 'Delia Foss', 'phone' => '(415) 555-0146', 'visits' => 11, 'spend' => 705],
        ];
        $clients = collect($clientData)->mapWithKeys(function ($c) {
            return [$c['name'] => Client::create($c)];
        });

        $elenaUser = User::create([
            'name' => 'Elena Marsh',
            'email' => 'elena@example.test',
            'password' => Hash::make('password'),
            'role' => 'client',
        ]);
        $clients['Elena Marsh']->update(['user_id' => $elenaUser->id]);

        // --- A day of appointments -------------------------------------------
        $today = today();
        $rows = [
            ['09:00', 'Elena Marsh', 'Balayage', 'Marisol Reyes', 'completed'],
            ['09:30', 'Carl Denton', 'Beard Trim', 'Andre Boyd', 'completed'],
            ['10:15', 'Grace Odom', 'Gel Manicure', 'Priya Nair', 'completed'],
            ['11:00', 'Naomi Fisk', 'Root Touch-Up', 'Marisol Reyes', 'in-progress'],
            ['11:30', 'Carl Denton', 'Haircut & Style', 'Jasmine Cole', 'in-progress'],
            ['13:00', 'Priya Shah', 'Keratin Treatment', 'Marisol Reyes', 'confirmed'],
            ['14:00', 'Wendell Kerr', 'Haircut & Style', 'Andre Boyd', 'confirmed'],
            ['16:00', 'Delia Foss', 'Beard Trim', 'Andre Boyd', 'cancelled'],
        ];

        foreach ($rows as [$time, $clientName, $serviceName, $stylistName, $status]) {
            $appt = Appointment::create([
                'client_id' => $clients[$clientName]->id,
                'stylist_id' => $stylists[$stylistName]->id,
                'service_id' => $services[$serviceName]->id,
                'scheduled_at' => $today->copy()->setTimeFromTimeString($time),
                'status' => $status,
                'price' => $services[$serviceName]->price,
            ]);
            if ($clientName === 'Elena Marsh' && $status === 'completed') {
                $elenaBalayage = $appt;
            }
        }

        // --- A sample approved review ------------------------------------------
        if (isset($elenaBalayage)) {
            Review::create([
                'appointment_id' => $elenaBalayage->id,
                'client_id' => $elenaBalayage->client_id,
                'stylist_id' => $elenaBalayage->stylist_id,
                'rating' => 5,
                'comment' => 'My colorist actually remembers what I asked for last time.',
                'status' => 'approved',
            ]);
        }

        // --- Editable landing page content -----------------------------------
        SalonSetting::create([
            'salon_name' => 'Gilt & Grace Salon',
            'hero_headline' => 'A salon that plans your whole visit, not just your appointment',
            'hero_sub' => 'Book with your favorite stylist, track your visit history, and see exactly what your next appointment will cost.',
            'featured_services' => array_slice(array_keys($serviceData), 0, 6),
            'testimonials' => [
                ['name' => 'Elena M.', 'quote' => 'My colorist actually remembers what I asked for last time.'],
                ['name' => 'Carl D.', 'quote' => 'Booking took thirty seconds and the price was exactly what I paid.'],
            ],
            'faqs' => [
                ['question' => 'Do I need to create an account to book?', 'answer' => 'Yes, a quick free account lets us keep your appointment history and speed up future bookings.'],
                ['question' => 'Can I choose my stylist?', 'answer' => 'Yes, or leave it on "no preference" and we will assign whoever is next available.'],
            ],
            'contact_phone' => '(415) 555-0100',
            'contact_address' => '214 Bellflower Ave, San Francisco, CA',
            'map_query' => '214 Bellflower Ave, San Francisco, CA',
            'cancellation_policy' => 'Cancellations within 24 hours of your appointment may be subject to a fee.',
            'open_time' => '09:00',
            'close_time' => '19:00',
        ]);
    }
}
