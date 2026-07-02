<?php

namespace App\Enums;

enum CourseStatus: string
{
    case Draft = 'Draft';
    case Published = 'Published';
    case Archived = 'Archived';
}
