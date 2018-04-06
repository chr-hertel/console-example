<?php

declare(strict_types = 1);

namespace App\Payment;

use App\Entity\Invoice;

class Exception extends \DomainException
{
    public static function forInvoice(Invoice $invoice): self
    {
        return new self(sprintf('Payment authorization failed for invoice %s', $invoice));
    }
}
