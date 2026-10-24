<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Review;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'client' => $client->makeVisible('notes'),
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

    /**
     * A manager corrects a client's name or phone and keeps private notes. Visits, spend and last visit
     * are system-controlled and are never read from the request. A phone number that already belongs to
     * another client is refused unless the manager confirms (families can share a number).
     */
    public function update(Request $request, Client $client)
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ]);

        foreach (['name', 'phone', 'notes'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        abort_if(array_key_exists('name', $data) && $data['name'] === '', 422, 'Please enter a name.');
        foreach (['phone', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (! empty($data['phone']) && ! $request->boolean('confirm_duplicate')) {
            $digits = preg_replace('/\D+/', '', $data['phone']);
            $same = Client::where('id', '!=', $client->id)->whereNotNull('phone')
                ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') = ?", [$digits])
                ->get(['id', 'name']);
            if ($digits !== '' && $same->isNotEmpty()) {
                return response()->json([
                    'message' => 'Another client already uses this phone number: '.$same->pluck('name')->implode(', ').'.',
                    'code' => 'duplicate_phone',
                    'duplicates' => $same,
                ], 422);
            }
        }

        DB::transaction(function () use ($request, $client, $data) {
            $client->fill(collect($data)->only(['name', 'phone', 'notes'])->all());
            $changed = array_keys($client->getDirty());
            $client->save();
            // A client with a login keeps the same name on both records.
            if (in_array('name', $changed, true) && $client->user_id) {
                $client->user()->update(['name' => $client->name]);
            }
            if ($changed) {
                // Which fields changed, not their values: notes can hold private details.
                AuditLog::record($request->user(), 'client.updated', $client, ['fields' => $changed]);
            }

        });

        return response()->json($client->fresh()->makeVisible('notes'));
    }
}
