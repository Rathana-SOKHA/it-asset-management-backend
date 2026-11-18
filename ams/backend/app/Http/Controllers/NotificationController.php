<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications()->latest()->paginate(30);
    }

    public function markRead(Request $request, DatabaseNotification $notification)
    {
        abort_unless(
            $notification->notifiable_id === $request->user()->getKey()
                && $notification->notifiable_type === $request->user()->getMorphClass(),
            404,
        );
        $notification->markAsRead();

        return response()->noContent();
    }
}
