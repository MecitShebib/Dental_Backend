<?php

namespace App\Enums;

enum CariPartyType: string
{
    case Supplier = 'supplier';
    case ContractedInstitution = 'contracted_institution';
    case HealthAgency = 'health_agency';
    case CashRegister = 'cash_register';
    case BankAccount = 'bank_account';
    case Other = 'other';
}
