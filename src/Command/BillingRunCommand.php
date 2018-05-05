<?php

declare(strict_types = 1);

namespace App\Command;

use App\BillingRun;
use App\Payment\Exception as PaymentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    private BillingRun $billingRun;

    public function __construct(BillingRun $billingRun)
    {
        $this->billingRun = $billingRun;

        parent::__construct('app:billing:run');
    }

    protected function configure(): void
    {
        $this->addArgument('period', InputArgument::REQUIRED, 'Billing Period');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $period = $input->getArgument('period');

        $io->title(sprintf('Start billing run for %s', $period->format('m-Y')));

        $progressIO = $this->style($input, $output);
        $progress = $progressIO->createProgressBar();
        $onProgress = function (int $current, int $max) use ($progressIO, $progress) {
            $this->onProgress($progressIO, $progress, $current, $max);
        };

        $errorIO = $this->style($input, $output);
        $onError = function (PaymentException $exception) use ($errorIO) {
            $errorIO->error($exception->getMessage());
        };

        $this->billingRun->start($period, $onProgress, $onError);

        $io->success('Done.');

        return 0;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
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

    private function onProgress(SymfonyStyle $io, ProgressBar $progress, int $current, int $max): void
    {
        if ($current === 1) {
            $io->note(sprintf('Loaded %d customers to process', $max));
            $progress->setMaxSteps($max);
        }

        $progress->advance();

        if ($current === $max) {
            $progress->finish();
            $io->note(sprintf('Processed %d customers', $max));
        }
    }

    /**
     * Needed for testing context due to OutputInterface implementation
     */
    private function style(InputInterface $input, OutputInterface $output): SymfonyStyle
    {
        $out = $output instanceof ConsoleOutput ? $output->section() : $output;

        return new SymfonyStyle($input, $out);
    }
}
