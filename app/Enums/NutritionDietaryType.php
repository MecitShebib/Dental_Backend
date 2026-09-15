<?php

namespace App\Enums;

enum NutritionDietaryType: string
{
    case Omnivore = 'omnivore';
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Halal = 'halal';
    case Kosher = 'kosher';
    case Other = 'other';
}
