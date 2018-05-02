<?php

declare(strict_types = 1);

namespace App\Command;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Invoice\Exporter;
use App\Invoice\Mailer;
use App\Payment\Exception as PaymentException;
use App\Payment\Provider as PaymentProvider;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Monthly Billing run for all active subscribers of our magazines.
 *
 * - Invoice gets generated
 * - Payment is executed
 * - Email is sent
 * - Magazine export is generated
 *
 * @author C■■■■■■■■■■ H■■■■■ <mail@c■■■■■■■■■■-h■■■■■.de>
 */
class BillingRunCommand extends Command
{
    protected static $defaultName = 'app:billing:run';

    private CustomerRepository $customerRepository;
    private EntityManagerInterface $entityManager;
    private PaymentProvider $paymentProvider;
    private Mailer $mailer;
    private Exporter $exporter;
    private string $environment;

    public function __construct(
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        PaymentProvider $paymentProvider,
        Mailer $mailer,
        Exporter $exporter,
        string $environment
    ) {
        $this->customerRepository = $customerRepository;
        $this->entityManager = $entityManager;
        $this->paymentProvider = $paymentProvider;
        $this->mailer = $mailer;
        $this->exporter = $exporter;
        $this->environment = $environment;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('period', InputArgument::REQUIRED, 'Billing Period');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ('dev' === $this->environment) {
            $stopwatch = new Stopwatch();
            $stopwatch->start('billing-run');
        }

        $period = $input->getArgument('period');

        $output->writeln(sprintf('<info>Start billing run for %s</info>', $period->format('m-Y')));
        $output->writeln('============================='.PHP_EOL);

        $customers = $this->fetchActiveCustomer();
        $output->writeln(sprintf('<info>Loaded %d customers to process</info>'.PHP_EOL, count($customers)));

        $invoices = $this->generateInvoice($output, $customers, $period);
        $this->payInvoices($output, $invoices);
        $this->sendInvoices($output, $invoices);
        $this->exportMagazines($output, $period, $invoices);

        $output->writeln(['', '<info>Done.</info>', '']);

        if ('dev' === $this->environment) {
            $output->writeln((string) $stopwatch->stop('billing-run'));
        }

        return 0;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $period = \DateTimeImmutable::createFromFormat('m-Y', (string) $input->getArgument('period'));

        if (false === $period) {
            $questionHelper = $this->getHelper('question');
            $question = new Question('Which period do you want? (format: mm-yyyy)');
            $question->setNormalizer(function ($period) {
                return \DateTimeImmutable::createFromFormat('m-Y', (string) $period);
            });
            $question->setValidator(function ($period) {
                if (false === $period) {
                    throw new \InvalidArgumentException('The given value was not a valid period, use mm-yyyy as format');
                }

                return $period;
            });
            $period = $questionHelper->ask($input, $output, $question);
        }

        $input->setArgument('period', $period);
    }

    /**
     * @return Customer[]
     */
    private function fetchActiveCustomer(): array
    {
        return $this->customerRepository->findByActive(true);
    }

    /**
     * @return Invoice[]
     */
    private function generateInvoice(OutputInterface $output, array $customers, \DateTimeImmutable $period): array
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

    private function exportMagazines(OutputInterface $output, \DateTimeImmutable $period, array $invoices): void
    {
        $output->write('Export invoices: ');

        $this->exporter->export($period, $invoices);

        $output->writeln('<info>OK</info>');
    }
}
