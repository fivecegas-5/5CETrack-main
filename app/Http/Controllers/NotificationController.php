<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\FuelTank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'unread_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $user = $request->user();
        $notifications = $user->notifications()
            ->when(
                ($data['unread_only'] ?? false) === true || ($data['unread_only'] ?? null) === '1',
                fn ($query) => $query->whereNull('read_at'),
            )
            ->latest()
            ->paginate($data['per_page'] ?? 25);

        return response()->json([
            'data' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json([
            'data' => [
                'id' => $record->id,
                'read_at' => $record->read_at,
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $markedRead = $request->user()->unreadNotifications()->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'marked_read' => $markedRead,
            'unread_count' => 0,
        ]);
    }

    public function viewInventory(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $details = $record->data;
        abort_unless(($details['event'] ?? null) === 'low_stock_threshold_reached', 404);

        $branchId = filter_var($details['branch_id'] ?? null, FILTER_VALIDATE_INT);
        $tankId = filter_var($details['tank_id'] ?? null, FILTER_VALIDATE_INT);
        abort_unless(is_int($branchId) && is_int($tankId), 404);

        $branch = Branch::query()
            ->whereKey($branchId)
            ->where('is_active', true)
            ->firstOrFail();
        abort_unless($request->user()->canAccessBranch($branch), 404);

        $tank = FuelTank::query()
            ->whereKey($tankId)
            ->where('branch_id', $branch->id)
            ->firstOrFail();

        if ($request->user()->isOwner() && $request->user()->branch_id === null) {
            $request->session()->put('active_branch_id', $branch->id);
        }

        return redirect()->route('inventory.index', ['highlight_tank' => $tank->id]);
    }
}
