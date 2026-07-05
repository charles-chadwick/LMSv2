<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelectOptions;

enum CourseLevel: string
{
    use HasSelectOptions;

    case Beginner = 'Beginner';
    case Intermediate = 'Intermediate';
    case Advanced = 'Advanced';
}
