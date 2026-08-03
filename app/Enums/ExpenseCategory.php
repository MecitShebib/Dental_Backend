<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case DentalSupplies = 'dental_supplies';
    case LabFees = 'lab_fees';
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Equipment = 'equipment';
    case Marketing = 'marketing';
    case Insurance = 'insurance';
    case Maintenance = 'maintenance';
    case Taxes = 'taxes';
    case Other = 'other';
}
