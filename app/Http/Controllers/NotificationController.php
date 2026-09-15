<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * ログインユーザーの通知一覧を表示する。
     *
     * @param  Request  $request  認証済みユーザーを含むリクエスト
     * @return View 通知一覧画面
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
     *
     * @param  Request  $request  認証済みユーザーを含むリクエスト
     * @param  string  $notification  既読にする通知のID
     * @return RedirectResponse 直前の画面へのリダイレクト
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
