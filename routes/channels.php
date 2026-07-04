<?php

use App\Models\Discussion;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('discussions.{discussion}', function (User $user, int $discussion): bool {
    $model = Discussion::with('course')->find($discussion);

    return $model !== null && $user->can('view', $model);
});
