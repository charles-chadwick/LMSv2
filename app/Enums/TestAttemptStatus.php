<?php

namespace App\Enums;

enum TestAttemptStatus: string
{
    case InProgress = 'In Progress';
    case Submitted = 'Submitted';
    case Graded = 'Graded';
}
