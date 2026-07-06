<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Http\Filters\FilterOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Notifications shown per page.
     */
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()
            ->withFilters($request->input('filters'))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn ($notification): array => [
                'id' => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                ...$notification->data,
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => $request->input('filters', []),
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * Declarative filter controls for the notifications list.
     *
     * @return list<array<string, mixed>>
     */
    private function filterOptions(): array
    {
        return FilterOption::toArrayList([
            FilterOption::select('type', 'Type', NotificationType::options()),
            FilterOption::select('read', 'Status', [
                ['value' => 'unread', 'label' => 'Unread'],
                ['value' => 'read', 'label' => 'Read'],
            ], multiple: false),
            FilterOption::dateRange('created_at', 'Received'),
        ]);
    }
}
