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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

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

    public function start(DateTimeImmutable $period, OutputInterface $output): void
    {
        $customers = $this->fetchActiveCustomer();
        $output->writeln(sprintf('<info>Loaded %d customers to process</info>'.PHP_EOL, count($customers)));

        $invoices = $this->generateInvoice($output, $customers, $period);
        $this->payInvoices($output, $invoices);
        $this->sendInvoices($output, $invoices);
        $this->exportMagazines($output, $period, $invoices);
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
    private function generateInvoice(OutputInterface $output, array $customers, DateTimeImmutable $period): array
    {
        $output->writeln('Generate Invoices:');

        $invoices = [];
        $progressBar = new ProgressBar($output, count($customers));
        $progressBar->start();
        foreach ($customers as $i => $customer) {
            $invoice = Invoice::forCustomer($customer, $period);
            $invoices[] = $invoice;
            $this->entityManager->persist($invoice);
            $progressBar->advance();
        }
        $progressBar->finish();

        $output->writeln('');
        $output->writeln(sprintf('<comment>Generated %d invoices to pay</comment>'.PHP_EOL, count($invoices)));

        $this->entityManager->flush();

        return $invoices;
    }

    private function payInvoices(OutputInterface $output, array $invoices): void
    {
        $output->writeln('Authorize invoices:');

        $progressBar = new ProgressBar($output, count($invoices));
        $progressBar->start();
        $success = 0;
        foreach ($invoices as $invoice) {
            try {
                $this->paymentProvider->authorize($invoice);
                ++$success;
            } catch (PaymentException $exception) {
                $output->writeln(
                    sprintf('<error>An error occurred while authorizing payment for invoice %s</error>', $invoice)
                );
            }

            $progressBar->advance();
        }
        $this->entityManager->flush();

        $progressBar->finish();

        $output->writeln('');
        $output->writeln(sprintf('<comment>Authorized %d invoices</comment>'.PHP_EOL, $success));
    }

    private function sendInvoices(OutputInterface $output, $invoices): void
    {
        $output->writeln('Generate PDFs and send notifications invoices:');

        $progressBar = new ProgressBar($output, count($invoices));
        $progressBar->start();
        foreach ($invoices as $invoice) {
            $this->mailer->sendInvoice($invoice);
            $progressBar->advance();
        }

        $progressBar->finish();

        $output->writeln('');
        $output->writeln(sprintf('<comment>Sent %d invoices</comment>'.PHP_EOL, count($invoices)));
    }

    private function exportMagazines(OutputInterface $output, DateTimeImmutable $period, array $invoices): void
    {
        $output->write('Export invoices: ');

        $this->exporter->export($period, $invoices);

        $output->writeln('<info>OK</info>');
    }
}
