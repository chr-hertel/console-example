<?php

declare(strict_types = 1);

namespace App\Payment;

use App\Entity\Invoice;

class Provider
{
    public function authorize(Invoice $invoice): void
    {
        usleep(5000);

        if (random_int(1, 10) === 1) {
            throw Exception::forInvoice($invoice);
        }

        $invoice->paid();
    }
}
