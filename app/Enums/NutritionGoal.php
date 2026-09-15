<?php

namespace App\Enums;

enum NutritionGoal: string
{
    case WeightLoss = 'weight_loss';
    case WeightGain = 'weight_gain';
    case Maintenance = 'maintenance';
    case MuscleGain = 'muscle_gain';
    case MedicalDiet = 'medical_diet';
    case Other = 'other';
}
