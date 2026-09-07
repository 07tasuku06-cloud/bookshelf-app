<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * ログインユーザーの通知一覧を表示する。
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->get();

        return view(
            'notifications.index',
            compact('notifications')
        );
    }

    /**
     * ログインユーザーが所有する通知を既読にする。
     */
    public function read(
        Request $request,
        string $notification
    ): RedirectResponse {
        $databaseNotification = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $databaseNotification->markAsRead();

        return back()->with(
            'success',
            '通知を既読にしました。'
        );
    }
}
