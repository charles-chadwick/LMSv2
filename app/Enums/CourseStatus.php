<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelectOptions;

enum CourseStatus: string
{
    use HasSelectOptions;

    case Draft = 'Draft';
    case Published = 'Published';
    case Archived = 'Archived';
}
