<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Queue.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Queue\Payloads;

use CodeIgniter\Queue\Handlers\BaseHandler;
use CodeIgniter\Queue\QueuePushResult;

class ChainBuilder
{
    /**
     * Collection of jobs in the chain
     */
    protected PayloadCollection $payloads;

    public function __construct(protected BaseHandler $handler)
    {
        $this->payloads = new PayloadCollection();
    }

    /**
     * Add a job to the chain
     */
    public function push(string $queue, string $jobName, array $data = []): ChainElement
    {
        $payload = new Payload($jobName, $data);

        $payload->setQueue($queue);

        $this->payloads->add($payload);

        return new ChainElement($payload, $this);
    }

    /**
     * Dispatch the chain of jobs
     */
    public function dispatch(): QueuePushResult
    {
        if ($this->payloads->count() === 0) {
            return QueuePushResult::failure('No jobs to dispatch.');
        }

        $current  = $this->payloads->shift();
        $priority = $current->getPriority();
        $delay    = $current->getDelay();

        if ($priority !== null) {
            $this->handler->setPriority($priority);
        }

        if ($delay !== null) {
            $this->handler->setDelay($delay);
        }

        // Set chained jobs for the next job
        // @phpstan-ignore greater.alwaysTrue
        if ($this->payloads->count() > 0) {
            $current->setChainedJobs($this->payloads);
        }

        // Push to the queue with the specified queue name
        return $this->handler->push(
            $current->getQueue(),
            $current->getJob(),
            $current->getData(),
            $current->getMetadata(),
        );
    }
}
