<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Submitted = 'Submitted';
    case Graded = 'Graded';
    case Returned = 'Returned';
}
