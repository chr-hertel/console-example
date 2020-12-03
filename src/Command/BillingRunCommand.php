<?php

declare(strict_types = 1);

namespace App\Command;

use App\BillingRun;
use App\Payment\Exception as PaymentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

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
        $period = $input->getArgument('period');

        $output->writeln(sprintf('<info>Start billing run for %s</info>', $period->format('m-Y')));
        $output->writeln('============================='.PHP_EOL);

        $progress = new ProgressBar($output);
        $onProgress = function (int $count, int $max) use ($output, $progress) {
            $this->onProgress($output, $progress, $count, $max);
        };

        $onError = function (PaymentException $exception) use ($output) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');
        };

        $this->billingRun->start($period, $onProgress, $onError);

        $output->writeln(['', '<info>Done.</info>', '']);

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

    private function onProgress(OutputInterface $output, ProgressBar $progress, int $current, int $max): void
    {
        if ($current === 1) {
            $output->writeln(sprintf('<info>Loaded %d customers to process</info>', $max));
            $progress->setMaxSteps($max);
        }

        $progress->advance();

        if ($current === $max) {
            $progress->finish();
            $output->writeln(sprintf('<info>Processed %d customers</info>', $max));
        }
    }
}
