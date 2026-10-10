<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalonSetting;
use Illuminate\Http\Request;

class SalonSettingController extends Controller
{
    /** Public: the landing page needs this before anyone logs in. */
    public function show()
    {
        return SalonSetting::current();
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'salon_name' => ['sometimes', 'string', 'max:255'],
            'hero_headline' => ['sometimes', 'string', 'max:255'],
            'hero_sub' => ['sometimes', 'string'],
            'featured_services' => ['sometimes', 'array'],
            'testimonials' => ['sometimes', 'array'],
            'faqs' => ['sometimes', 'array'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contact_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'map_query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cancellation_policy' => ['sometimes', 'nullable', 'string'],
            'open_time' => ['sometimes', 'date_format:H:i'],
            'close_time' => ['sometimes', 'date_format:H:i'],
        ]);

        $settings = SalonSetting::current();
        $settings->update($data);

        return response()->json($settings);
    }
}
