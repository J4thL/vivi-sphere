<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** Public: approved reviews only, for the landing page. */
    public function index(Request $request)
    {
        $user = $request->user('sanctum');

        if ($user?->isManager()) {
            return Review::with(['client', 'stylist'])->latest()->limit(config('salon.reviews_max'))->get();
        }

        // Everyone else gets a minimal, privacy-safe shape: never the client's
        // phone, spend, visit count or user id. A logged-in client also gets the
        // appointment_id of their OWN reviews so the dashboard can hide
        // "review this visit" prompts for visits they already reviewed.
        $ownClientId = $user?->isClient() ? $user->client?->id : null;

        return Review::with(['client:id,name', 'stylist:id,name'])
            ->where('status', 'approved')
            ->latest()
            ->limit(config('salon.public_reviews_max'))
            ->get()
            ->map(function (Review $review) use ($ownClientId) {
                $row = [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'status' => $review->status,
                    'created_at' => $review->created_at,
                    'client' => ['name' => $review->client->public_name],
                    'stylist' => ['id' => $review->stylist->id, 'name' => $review->stylist->name],
                ];
                if ($ownClientId !== null && $review->client_id === $ownClientId) {
                    $row['appointment_id'] = $review->appointment_id;
                }

                return $row;
            })
            ->values();
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isClient(), 403);

        $data = $request->validate([
            'appointment_id' => ['required', 'exists:appointments,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $appointment = $user->client->appointments()->where('id', $data['appointment_id'])->firstOrFail();
        abort_unless($appointment->status === 'completed', 422, 'You can only review a completed appointment.');

        // A review can be corrected only while it is still waiting for moderation. Re-submitting an
        // approved one used to silently pull it off the website and overwrite the rating; a rejected
        // one must not be resubmitted to dodge the manager's decision.
        $review = Review::firstOrNew(['appointment_id' => $appointment->id]);
        abort_if($review->exists && $review->status !== 'pending', 422, 'This review has already been moderated and can no longer be changed.');

        $review->fill([
            'client_id' => $appointment->client_id,
            'stylist_id' => $appointment->stylist_id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'status' => 'pending',
        ])->save();

        return response()->json($review, 201);
    }

    public function update(Request $request, Review $review)
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);
        $review->update($data);
        AuditLog::record($request->user(), "review.{$data['status']}", $review);

        return response()->json($review);
    }
}
