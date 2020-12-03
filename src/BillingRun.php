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
use Psr\Log\LoggerInterface;

class BillingRun
{
    private EntityManagerInterface $entityManager;
    private PaymentProvider $paymentProvider;
    private Mailer $mailer;
    private Exporter $exporter;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        PaymentProvider $paymentProvider,
        Mailer $mailer,
        Exporter $exporter,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->paymentProvider = $paymentProvider;
        $this->mailer = $mailer;
        $this->exporter = $exporter;
        $this->logger = $logger;
    }

    public function start(DateTimeImmutable $period): void
    {
        $customers = $this->fetchActiveCustomer();
        $this->logger->info(sprintf('Loaded %d customers to process', count($customers)));

        $invoices = $this->generateInvoice($customers, $period);
        $this->payInvoices($invoices);
        $this->sendInvoices($invoices);
        $this->exportMagazines($period, $invoices);
    }

    /**
     * @return Customer[]
     */
    private function fetchActiveCustomer(): array
    {
        return $this->entityManager->getRepository(Customer::class)->findByActive(true);
    }

    /**
     * @return Invoice[]
     */
    private function generateInvoice(array $customers, DateTimeImmutable $period): array
    {
        $invoices = [];
        foreach ($customers as $i => $customer) {
            $invoice = Invoice::forCustomer($customer, $period);
            $invoices[] = $invoice;
            $this->entityManager->persist($invoice);
        }
        $this->logger->debug(sprintf('Generated %d invoices to pay', count($invoices)));

        $this->entityManager->flush();

        return $invoices;
    }

    private function payInvoices(array $invoices): void
    {
        $success = 0;
        foreach ($invoices as $invoice) {
            try {
                $this->paymentProvider->authorize($invoice);
                ++$success;
            } catch (PaymentException $exception) {
                $this->logger->error(
                    sprintf('An error occurred while authorizing payment for invoice %s', $invoice)
                );
            }
        }
        $this->entityManager->flush();

        $this->logger->debug(sprintf('Authorized %d invoices', $success));
    }

    private function sendInvoices($invoices): void
    {
        $this->logger->debug('Generate PDFs and send notifications invoices:');

        foreach ($invoices as $invoice) {
            $this->mailer->sendInvoice($invoice);
        }

        $this->logger->debug(sprintf('Sent %d invoices', count($invoices)));
    }

    private function exportMagazines(DateTimeImmutable $period, array $invoices): void
    {
        $this->logger->debug('Export invoices: Start');

        $this->exporter->export($period, $invoices);

        $this->logger->debug('Export invoices: Done');
    }
}
