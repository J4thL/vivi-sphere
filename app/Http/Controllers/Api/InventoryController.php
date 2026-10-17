<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        $user = $request->user();

        // Each branch keeps its own stock. A manager sees their branch; the admin sees all, or one with ?branch_id=.
        return InventoryItem::orderBy('name')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($user->isAdmin() && $request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->query('branch_id')))
            ->get();
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('active', true)],
        ]);

        // A manager always adds to their own branch; the admin chooses.
        $user = $request->user();
        $data['branch_id'] = $user->isAdmin() ? ($data['branch_id'] ?? null) : $user->branch_id;
        abort_if($data['branch_id'] === null, 422, 'Please choose a branch for this item.');

        $item = InventoryItem::create($data);
        AuditLog::record($request->user(), 'inventory.created', $item);

        return response()->json($item, 201);
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'qty' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'unit_cost' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'branch_id' => ['sometimes', Rule::exists('branches', 'id')->where('active', true)],
        ]);

        $user = $request->user();
        abort_unless($user->canAccessBranch($inventoryItem->branch_id), 403);
        abort_if(array_key_exists('branch_id', $data) && ! $user->isAdmin(), 403, 'Only an admin can move stock to another branch.');

        $inventoryItem->update($data);
        AuditLog::record($request->user(), 'inventory.updated', $inventoryItem, $data);

        return response()->json($inventoryItem);
    }
}
