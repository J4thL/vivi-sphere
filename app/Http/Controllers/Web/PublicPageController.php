<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchService;
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

        $branches = Branch::where('active', true)->orderBy('name')->get();
        $featuredServices = Service::whereIn('name', $featuredNames)->get();

        // A branch can charge its own price or not offer a service. Show "from" the lowest price where
        // branches differ, and leave out a service no open branch offers.
        $overrides = BranchService::whereIn('branch_id', $branches->pluck('id'))->get()->groupBy('service_id');
        $featuredServices = $featuredServices->map(function (Service $service) use ($branches, $overrides) {
            $prices = $branches->map(function (Branch $b) use ($service, $overrides) {
                $o = $overrides->get($service->id)?->firstWhere('branch_id', $b->id);

                return ($o && ! $o->available) ? null : (float) ($o?->price ?? $service->price);
            })->filter(fn ($p) => $p !== null);

            $service->offered = $branches->isEmpty() || $prices->isNotEmpty();
            $service->display_price = $prices->isNotEmpty() ? $prices->min() : (float) $service->price;
            $service->price_varies = $prices->unique()->count() > 1 || ($prices->isNotEmpty() && $prices->count() < $branches->count());

            return $service;
        })->filter(fn (Service $s) => $s->offered)->values();

        return view('landing', [
            'salonSettings' => $salonSettings,
            'branches' => $branches,
            'featuredServices' => $featuredServices,
            'stylists' => Stylist::with('branch:id,name')->where('active', true)->orderBy('name')->get(),
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
