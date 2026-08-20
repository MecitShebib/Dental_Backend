<?php

namespace App\Enums;

enum CariTransactionType: string
{
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Refund = 'return';
    case Adjustment = 'adjustment';
}
