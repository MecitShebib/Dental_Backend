<?php

namespace App\Enums;

enum LabCaseWorkType: string
{
    case Crown = 'crown';
    case Bridge = 'bridge';
    case DentureFull = 'denture_full';
    case DenturePartial = 'denture_partial';
    case Veneer = 'veneer';
    case Retainer = 'retainer';
    case NightGuard = 'night_guard';
    case ImplantAbutment = 'implant_abutment';
    case Aligner = 'aligner';
    case Other = 'other';
}
