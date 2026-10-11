<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        return InventoryItem::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::create($data);
        AuditLog::record($request->user(), 'inventory.created', $item);

        return response()->json($item, 201);
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'qty' => ['sometimes', 'integer', 'min:0'],
            'reorder_level' => ['sometimes', 'integer', 'min:0'],
            'unit_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $inventoryItem->update($data);
        AuditLog::record($request->user(), 'inventory.updated', $inventoryItem, $data);

        return response()->json($inventoryItem);
    }
}
