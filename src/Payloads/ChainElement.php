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

class ChainElement
{
    public function __construct(protected Payload $payload, protected ChainBuilder $chainBuilder)
    {
    }

    /**
     * Set priority for this specific job
     */
    public function setPriority(string $priority): self
    {
        $this->payload->setPriority($priority);

        return $this;
    }

    /**
     * Set delay for this specific job
     */
    public function setDelay(int $delay): self
    {
        $this->payload->setDelay($delay);

        return $this;
    }

    /**
     * Push the next job in the chain (method chaining)
     */
    public function push(string $queue, string $jobName, array $data = []): ChainElement
    {
        return $this->chainBuilder->push($queue, $jobName, $data);
    }
}
