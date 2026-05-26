<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);

                if ($user->role_id) {
                    $query->orWhere('role_id', $user->role_id);
                }
            })
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => NotificationResource::collection($notifications),
        ]);
    }

    public function markAsRead(Notification $notification, Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $notification->user_id === null || $notification->user_id === $user->id || $notification->role_id === $user->role_id,
            403,
        );

        $notification->forceFill([
            'read_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => NotificationResource::make($notification),
        ]);
    }
}
