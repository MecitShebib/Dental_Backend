<?php

namespace App\Enums;

enum LabCaseStatus: string
{
    case Sent = 'sent';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Delivered = 'delivered';
}
