<?php

declare(strict_types = 1);

namespace App\Invoice;

use DateTimeImmutable;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

class Exporter
{
    private Filesystem $filesystem;
    private SerializerInterface $serializer;
    private string $storage;

    public function __construct(Filesystem $filesystem, SerializerInterface $serializer, string $storage)
    {
        $this->filesystem = $filesystem;
        $this->serializer = $serializer;
        $this->storage = $storage;
    }

    public function export(DateTimeImmutable $period, array $invoices): void
    {
        $this->filesystem->mkdir($this->storage);

        $this->filesystem->dumpFile(
            sprintf('%s/invoices-%s.csv', $this->storage, $period->format('Y-m')),
            $this->serializer->serialize($invoices, 'csv')
        );
    }
}
