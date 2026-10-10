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
        if ($request->user()?->isManager()) {
            return Review::with(['client', 'stylist'])->latest()->get();
        }
        return Review::with(['client', 'stylist'])->where('status', 'approved')->latest()->get();
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

        $review = Review::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'client_id' => $appointment->client_id,
                'stylist_id' => $appointment->stylist_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'status' => 'pending',
            ]
        );

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
