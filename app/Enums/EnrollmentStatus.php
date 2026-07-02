<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'Active';
    case Completed = 'Completed';
    case Dropped = 'Dropped';
}
