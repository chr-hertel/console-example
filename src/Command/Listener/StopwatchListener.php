<?php

declare(strict_types = 1);

namespace App\Command\Listener;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class StopwatchListener implements EventSubscriberInterface
{
    private Stopwatch $stopwatch;

    public function __construct(Stopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleCommandEvent::class => 'startStopwatch',
            ConsoleTerminateEvent::class => 'stopStopwatch',
        ];
    }

    public function startStopwatch(ConsoleCommandEvent $event): void
    {
        $this->stopwatch->start($event->getCommand()->getName());
    }

    public function stopStopwatch(ConsoleTerminateEvent $event): void
    {
        $name = $event->getCommand()->getName();

        if (!$this->stopwatch->isStarted($name)) {
            return;
        }

        $event->getOutput()->writeln((string) $this->stopwatch->stop($name));
    }
}
