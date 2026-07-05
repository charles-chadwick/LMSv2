<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelectOptions;

enum UserRole: string
{
    use HasSelectOptions;

    case Admin = 'Admin';
    case Instructor = 'Instructor';
    case Student = 'Student';
}
