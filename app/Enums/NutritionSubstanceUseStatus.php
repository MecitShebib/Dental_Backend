<?php

namespace App\Enums;

enum NutritionSubstanceUseStatus: string
{
    case None = 'none';
    case Occasional = 'occasional';
    case Regular = 'regular';
}
