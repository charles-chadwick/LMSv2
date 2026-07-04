<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserSummaryResource;
use App\Models\Message;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? [
                        ...UserSummaryResource::make($request->user())->resolve($request),
                        'email' => $request->user()->email,
                        'roles' => $request->user()->getRoleNames()->all(),
                        'can' => [
                            'create_courses' => $request->user()->can('create courses'),
                        ],
                        'unread_notifications_count' => $request->user()->unreadNotifications()->count(),
                        'unread_messages_count' => Message::query()
                            ->whereHas('conversation', fn ($query) => $query
                                ->where('student_id', $request->user()->id)
                                ->orWhere('instructor_id', $request->user()->id))
                            ->where('sender_id', '!=', $request->user()->id)
                            ->whereNull('read_at')
                            ->count(),
                    ]
                    : null,
            ],
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
