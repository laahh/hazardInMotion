<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Notification;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Notification\Notification;
use App\Models\EmergencyResponse\Notification\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('EmergencyResponse.notification.index', ['notifications' => $notifications]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function preferences(Request $request): View
    {
        $preference = NotificationPreference::firstOrCreate(['user_id' => $request->user()->id]);

        return view('EmergencyResponse.notification.preferences', ['preference' => $preference]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $preference = NotificationPreference::firstOrCreate(['user_id' => $request->user()->id]);
        $preference->update([
            'email_enabled' => $request->boolean('email_enabled'),
            'in_app_enabled' => $request->boolean('in_app_enabled'),
        ]);

        return back()->with('success', 'Preferensi notifikasi disimpan.');
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        $recent = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'message', 'link_url', 'is_read', 'created_at']);

        return response()->json([
            'unread_count' => Notification::query()->where('user_id', $request->user()->id)->where('is_read', false)->count(),
            'recent' => $recent,
        ]);
    }
}
