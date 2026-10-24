<?php

namespace App\Modules\Retail\Enums;

enum InvoiceDeliveryMethod: string
{
    case Print = 'print';
    case Email = 'email';
}
