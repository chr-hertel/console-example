<?php

declare(strict_types = 1);

namespace App;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Invoice\Exporter;
use App\Invoice\Mailer;
use App\Payment\Exception as PaymentException;
use App\Payment\Provider as PaymentProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class BillingRun
{
    private EntityManagerInterface $entityManager;
    private PaymentProvider $paymentProvider;
    private Mailer $mailer;
    private Exporter $exporter;

    public function __construct(
        EntityManagerInterface $entityManager,
        PaymentProvider $paymentProvider,
        Mailer $mailer,
        Exporter $exporter
    ) {
        $this->entityManager = $entityManager;
        $this->paymentProvider = $paymentProvider;
        $this->mailer = $mailer;
        $this->exporter = $exporter;
    }

    public function start(DateTimeImmutable $period, callable $progress, callable $error): void
    {
        $customers = $this->entityManager->getRepository(Customer::class)->findByActive(true);
        $customerNum = count($customers);

        $invoices = [];
        foreach ($customers as $i => $customer) {
            $invoice = Invoice::forCustomer($customer, $period);
            try {
                $this->paymentProvider->authorize($invoice);
            } catch (PaymentException $exception) {
                $error($exception);
            }
            $this->mailer->sendInvoice($invoice);
            $this->entityManager->persist($invoice);
            $invoices[] = $invoice;
            $progress(++$i, $customerNum);
        }

        $this->exporter->export($period, $invoices);
        $this->entityManager->flush();
    }
}
