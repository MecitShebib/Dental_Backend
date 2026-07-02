<?php

namespace App\Enums;

enum AppointmentType: string
{
    case Booked = 'booked';
    case Unavailable = 'unavailable';
}
