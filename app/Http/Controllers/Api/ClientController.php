<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Review;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // A client only ever sees their own record (used for "Total spent" / "Visits").
        if ($user->isClient()) {
            return Client::where('user_id', $user->id)->get();
        }

        abort_unless($user->isManager(), 403);

        return Client::orderByDesc('spend')->limit(config('salon.clients_max'))->get();
    }

    /**
     * One client's history for the staff detail panel. Clients are shared across branches, so any manager
     * sees the whole history (each visit is labelled with its branch).
     */
    public function show(Request $request, Client $client)
    {
        abort_unless($request->user()->isManager(), 403);

        $visits = Appointment::with(['service:id,name', 'stylist:id,name', 'branch:id,name'])
            ->where('client_id', $client->id)->orderByDesc('scheduled_at')->limit(50)->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'scheduled_at' => $a->scheduled_at?->format('Y-m-d H:i:s'),
                'status' => $a->status,
                'price' => $a->price,
                'service' => $a->service?->name,
                'stylist' => $a->stylist?->name,
                'branch' => $a->branch?->name,
            ]);

        // Most booked among finished visits.
        $favourite = fn (string $column, string $relation) => Appointment::where('client_id', $client->id)->where('status', 'completed')
            ->selectRaw("{$column}, count(*) as n")->groupBy($column)->orderByDesc('n')->orderBy($column)->first()
            ?->{$relation}?->name;

        return response()->json([
            'client' => $client,
            'visits' => $visits,
            'favourite_service' => $favourite('service_id', 'service'),
            'favourite_stylist' => $favourite('stylist_id', 'stylist'),
            'average_spend' => $client->visits > 0 ? round($client->spend / $client->visits, 2) : null,
            'reviews' => Review::with('stylist:id,name')->where('client_id', $client->id)->latest()->limit(20)->get()
                ->map(fn (Review $r) => ['id' => $r->id, 'rating' => $r->rating, 'comment' => $r->comment, 'status' => $r->status, 'stylist' => $r->stylist?->name, 'created_at' => $r->created_at]),
            'waitlist' => WaitlistEntry::with(['service:id,name', 'stylist:id,name'])->where('client_id', $client->id)->where('status', 'waiting')->orderBy('preferred_date')->get()
                ->map(fn (WaitlistEntry $w) => ['id' => $w->id, 'service' => $w->service?->name, 'stylist' => $w->stylist?->name, 'preferred_date' => $w->preferred_date?->toDateString()]),
        ]);
    }
}
