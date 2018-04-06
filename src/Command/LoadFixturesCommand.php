<?php

declare(strict_types = 1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\FileLoaderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LoadFixturesCommand extends Command
{
    protected static $defaultName = 'app:fixtures:load';

    private FileLoaderInterface $fileLoader;
    private EntityManagerInterface $entityManager;
    private string $fixturesFile;

    public function __construct(
        FileLoaderInterface $fileLoader,
        EntityManagerInterface $entityManager,
        string $projectDir
    ) {
        $this->fileLoader = $fileLoader;
        $this->fixturesFile = $projectDir . '/fixtures/customer-setup.yaml';
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Loading fixtures to database');

        $io->comment('Rebuilding database schema');
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $io->comment('Generating objects');
        $fixtures = $this->fileLoader->loadFile($this->fixturesFile)->getObjects();

        $io->comment('Persisting objects');
        array_walk($fixtures, [$this->entityManager, 'persist']);

        $io->comment('Flushing to database');
        $this->entityManager->flush();

        $io->success('Done');

        return 0;
    }
}
