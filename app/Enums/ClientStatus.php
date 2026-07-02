<?php

namespace App\Enums;

enum ClientStatus: string
{
    case New = 'new';
    case UnderTreatment = 'under_treatment';
    case Completed = 'completed';
    case Inactive = 'inactive';
}
