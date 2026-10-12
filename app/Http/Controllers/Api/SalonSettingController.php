<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SalonSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'featured_services' => ['sometimes', 'array', 'max:50'],
            'featured_services.*' => ['string', 'max:255'],
            'testimonials' => ['sometimes', 'array', 'max:50'],
            'testimonials.*' => ['array'],
            // The landing page reads $faq['question'] and $faq['answer'] directly, so a malformed
            // entry used to save fine and then break the homepage with a 500.
            'faqs' => ['sometimes', 'array', 'max:50'],
            'faqs.*' => ['array'],
            'faqs.*.question' => ['required', 'string', 'max:500'],
            'faqs.*.answer' => ['required', 'string', 'max:5000'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contact_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'map_query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cancellation_policy' => ['sometimes', 'nullable', 'string'],
            'open_time' => ['sometimes', 'date_format:H:i'],
            'close_time' => ['sometimes', 'date_format:H:i'],
        ]);

        $settings = SalonSetting::current();

        // Judge the hours as they would be after this save, even if only one of the two was sent.
        $open = $data['open_time'] ?? substr((string) $settings->open_time, 0, 5);
        $close = $data['close_time'] ?? substr((string) $settings->close_time, 0, 5);
        if (isset($data['open_time']) || isset($data['close_time'])) {
            if ($open >= $close) {
                throw ValidationException::withMessages(['close_time' => 'Closing time must be later than opening time.']);
            }
        }

        $settings->update($data);
        AuditLog::record($request->user(), 'settings.updated', $settings, ['fields' => array_keys($data)]);

        return response()->json($settings);
    }
}
