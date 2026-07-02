<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'Admin';
    case Instructor = 'Instructor';
    case Student = 'Student';
}
