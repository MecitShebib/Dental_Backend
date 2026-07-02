<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Attended = 'attended';
    case NoShow = 'no_show';
    case WalkIn = 'walk_in';
}
