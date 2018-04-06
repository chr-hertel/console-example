<?php

declare(strict_types = 1);

namespace App\Tests\Command;

use App\Command\BillingRunCommand;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Invoice\Exporter;
use App\Invoice\Mailer;
use App\Payment\Provider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\FileLoaderInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;

class BillingRunCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $entityManager;
    private int $activeCustomers;

    public function testBillingRun(): void
    {
        $this->commandTester->execute([
            'command'  => 'app:billing:run',
            'period' => '12-2018',
        ]);

        $this->assertBillingRunExitedCorrectly();
        $this->assertInvoicesGotCreated();
        $this->assertExportGotCreated();
        $this->assertPdfsGotCreated();
        $this->assertInvoicesGotMailed();
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('app:billing:run');
        $this->commandTester = new CommandTester($command);

        $fileLoader = $kernel->getContainer()->get('test.'.FileLoaderInterface::class);
        $this->entityManager = $kernel->getContainer()->get('test.'.EntityManagerInterface::class);

        $application->add(new BillingRunCommand(
            $this->entityManager->getRepository(Customer::class),
            $this->entityManager,
            $kernel->getContainer()->get('test.'.Provider::class),
            $kernel->getContainer()->get('test.'.Mailer::class),
            $kernel->getContainer()->get('test.'.Exporter::class),
            $kernel->getContainer()->getParameter('kernel.environment')
        ));

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $fixtures = $fileLoader->loadFile(__DIR__.'/../../fixtures/customer-setup.yaml')->getObjects();
        array_walk($fixtures, [$this->entityManager, 'persist']);
        $this->entityManager->flush();

        $this->activeCustomers = array_reduce($fixtures, static function(int $carry, object $fixture) {
            return $fixture instanceof Customer && $fixture->isActive() ? ++$carry : $carry;
        }, 0);
    }

    protected function tearDown(): void
    {
        (new SchemaTool($this->entityManager))->dropDatabase();

        (new Filesystem())->remove([
            __DIR__.'/../../var/test-export',
            __DIR__.'/../../var/test-invoices',
        ]);
    }

    private function assertBillingRunExitedCorrectly(): void
    {
        $output = $this->commandTester->getDisplay();
        self::assertStringEndsWith('Done.'.PHP_EOL.PHP_EOL, $output, 'Command done');
        self::assertSame(0, $this->commandTester->getStatusCode(), 'Exit code');
    }

    private function assertInvoicesGotCreated(): void
    {
        self::assertCount($this->activeCustomers, $this->entityManager->getRepository(Invoice::class)->findAll(), 'Invoices generated');
    }

    private function assertExportGotCreated(): void
    {
        $export = __DIR__.'/../../var/test-export/invoices-2018-12.csv';
        self::assertFileExists($export);

        $count = -1; // IGNORE CSV HEADLINE
        $csv = fopen($export, 'rb');
        while (fgetcsv($csv)) {
            ++$count;
        }
        fclose($csv);
        self::assertSame($this->activeCustomers, $count, 'Export generated');
    }

    private function assertPdfsGotCreated(): void
    {
        $path = __DIR__.'/../../var/test-invoices/2018-12';

        $pdfs = (new Finder())
            ->in($path)
            ->name('magazine-invoice-*.pdf');

        self::assertCount($this->activeCustomers, $pdfs, 'PDFs generated');
    }

    private function assertInvoicesGotMailed(): void
    {
        /** @var MessageLoggerListener $messageListener */
        $messageListener = self::$kernel->getContainer()->get('test.mailer.logger_message_listener');
        self::assertCount($this->activeCustomers, $messageListener->getEvents()->getMessages(), 'Mails sent');
    }
}
