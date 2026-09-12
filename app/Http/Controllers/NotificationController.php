<?php

namespace App\Http\Controllers;

use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $this->notifications
            ->paginateFor($user)
            ->through(fn (DatabaseNotification $notification) => $this->notifications->present($notification));

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $this->notifications->unreadCount($user),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        // findOrFail lewat relasi milik pengguna: notifikasi pengguna lain -> 404.
        $record = $request->user()->notifications()->findOrFail($notification);

        $this->notifications->markRead($record);

        return redirect()->to($this->notifications->present($record)['url']);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return redirect()->route('notifications.index')->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
