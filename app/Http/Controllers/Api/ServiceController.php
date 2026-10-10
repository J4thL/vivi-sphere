<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        return Service::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:services,name'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:5'],
            'photo_url' => ['nullable', 'url'],
        ]);

        $service = Service::create($data);
        AuditLog::record($request->user(), 'service.created', $service);

        return response()->json($service, 201);
    }

    public function update(Request $request, Service $service)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:services,name,' . $service->id],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5'],
            'photo_url' => ['sometimes', 'nullable', 'url'],
        ]);

        $old = $service->price;
        $service->update($data);
        if (isset($data['price'])) {
            AuditLog::record($request->user(), 'service.price_updated', $service, ['old' => $old, 'new' => $data['price']]);
        }

        return response()->json($service);
    }

    public function destroy(Request $request, Service $service)
    {
        abort_unless($request->user()->isAdmin(), 403);

        // The appointments table cascade-deletes on service removal, which
        // would silently wipe real booking history - refuse instead, and
        // tell the admin to just stop featuring it on the landing page.
        if ($service->appointments()->exists()) {
            return response()->json([
                'message' => 'This service has appointments booked against it and can\'t be deleted. Remove it from "Featured services" instead if you want to stop offering it.',
            ], 422);
        }

        $name = $service->name;
        $service->delete();
        AuditLog::record($request->user(), 'service.deleted', null, ['name' => $name]);

        return response()->json(['message' => 'Service deleted.']);
    }
}
