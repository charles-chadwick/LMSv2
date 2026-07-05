<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelectOptions;

enum EnrollmentStatus: string
{
    use HasSelectOptions;

    case Active = 'Active';
    case Completed = 'Completed';
    case Dropped = 'Dropped';

    /**
     * Whether an enrollment in this status may be dropped (self-drop or instructor removal).
     */
    public function isDroppable(): bool
    {
        return $this === self::Active;
    }
}
