<?php

namespace App\Enums;

enum CapitalTransactionType: string
{
    case Injection = 'injection';
    case Withdrawal = 'withdrawal';
}
