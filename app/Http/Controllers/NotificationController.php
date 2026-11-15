<?php

namespace App\Http\Controllers;

use App\Notifications\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected function visible(Request $request): Builder
    {
        return $request->user('web')->notifications()->getQuery()->where('type', UserNotification::class);
    }

    public function index(Request $request): JsonResponse
    {
        $page = $this->visible($request)->orderByDesc('created_at')->orderByDesc('id')->cursorPaginate(15);
        $page->through(fn ($notification) => [
            'id' => $notification->id, 'title' => $notification->data['title'], 'body' => $notification->data['body'],
            'url' => route($notification->data['route'], $notification->data['parameters'] ?? [], false),
            'read_at' => $notification->read_at, 'created_at' => $notification->created_at,
        ]);

        return response()->json(['data' => $page->items(), 'next_cursor' => $page->nextCursor()?->encode(), 'unread_count' => $this->visible($request)->whereNull('read_at')->count()]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $data = $request->validate(['read' => ['sometimes', 'boolean']]);
        $notice = $this->visible($request)->findOrFail($notification);
        ($data['read'] ?? true) ? $notice->markAsRead() : $notice->markAsUnread();

        return response()->json(['read_at' => $notice->read_at, 'unread_count' => $this->visible($request)->whereNull('read_at')->count()]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->visible($request)->findOrFail($notification)->delete();

        return response()->json(['unread_count' => $this->visible($request)->whereNull('read_at')->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->visible($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['unread_count' => $this->visible($request)->whereNull('read_at')->count()]);
    }
}
