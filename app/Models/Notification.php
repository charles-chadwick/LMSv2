<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\Filters\CallbackFilter;
use App\Models\Concerns\Filters\ExactFilter;
use App\Models\Concerns\Filters\Filter;
use App\Models\Concerns\Filters\RangeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use Filterable, HasFactory;

    /**
     * Filterable fields for the notifications list.
     *
     * @return array<string, Filter>
     */
    protected function filterableFields(): array
    {
        return [
            'type' => new ExactFilter('data->type'),
            'read' => new CallbackFilter(function (Builder $query, mixed $value): void {
                $values = (array) $value;
                $has_read = in_array('read', $values, true);
                $has_unread = in_array('unread', $values, true);

                if ($has_read === $has_unread) {
                    return;
                }

                if ($has_read) {
                    $query->whereNotNull('read_at');
                } else {
                    $query->whereNull('read_at');
                }
            }),
            'created_at' => new RangeFilter('created_at', asDate: true),
        ];
    }
}
