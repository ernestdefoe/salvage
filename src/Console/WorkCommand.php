<?php

namespace Ernestdefoe\Salvage\Console;

use Ernestdefoe\Salvage\Work\Runner;
use Ernestdefoe\Salvage\Work\WorkJob;
use Flarum\Console\AbstractCommand;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Queue;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scheduled every five minutes. Hands a round of work to the queue (a worker
 * runs it, or with the default sync queue this CLI process does), unless one
 * is already waiting there: a stalled worker must not come back to a pile.
 */
class WorkCommand extends AbstractCommand
{
    public function __construct(
        private readonly Queue $queue,
        private readonly Cache $cache,
        private readonly Runner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('salvage:work')
            ->setDescription('Mirror hotlinked images and check links in posts (one bounded round).')
            ->addOption('now', null, InputOption::VALUE_NONE, 'Run the round in this process instead of queueing it, and print what it did.');
    }

    protected function fire(): int
    {
        if ($this->input->getOption('now')) {
            $result = $this->runner->run();

            $this->info($result === null ? 'Another round is running.' : json_encode($result, JSON_THROW_ON_ERROR));

            return 0;
        }

        if ($this->cache->add(Runner::QUEUED, 1, 900)) {
            $this->queue->push(new WorkJob());
        }

        return 0;
    }
}
