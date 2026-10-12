<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Models\Stylist;

class PublicPageController extends Controller
{
    public function landing()
    {
        $salonSettings = SalonSetting::current();
        $featuredNames = $salonSettings->featured_services ?: Service::pluck('name')->take(6)->all();
        $approvedReviews = Review::with(['client', 'stylist'])->where('status', 'approved');

        return view('landing', [
            'salonSettings' => $salonSettings,
            'featuredServices' => Service::whereIn('name', $featuredNames)->get(),
            'stylists' => Stylist::where('active', true)->orderBy('name')->get(),
            'reviews' => (clone $approvedReviews)->latest()->take(6)->get(),
            'avgRating' => round((clone $approvedReviews)->avg('rating') ?? 0, 1),
            'reviewCount' => (clone $approvedReviews)->count(),
        ]);
    }

    public function stylistProfile(Stylist $stylist)
    {
        return view('stylist-profile', [
            'salonSettings' => SalonSetting::current(),
            'stylist' => $stylist,
            'reviews' => Review::where('stylist_id', $stylist->id)->where('status', 'approved')->latest()->take(config('salon.public_reviews_max'))->get(),
        ]);
    }

    public function legal(string $kind)
    {
        abort_unless(in_array($kind, ['terms', 'privacy']), 404);

        return view('legal', [
            'salonSettings' => SalonSetting::current(),
            'kind' => $kind,
            'title' => $kind === 'terms' ? 'Terms of service' : 'Privacy policy',
        ]);
    }
}
