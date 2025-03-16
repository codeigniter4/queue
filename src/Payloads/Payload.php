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

use CodeIgniter\Queue\Exceptions\QueueException;
use CodeIgniter\Queue\Traits\HasQueueValidation;
use JsonSerializable;

class Payload implements JsonSerializable
{
    use HasQueueValidation;

    /**
     * Job metadata
     */
    protected PayloadMetadata $metadata;

    public function __construct(protected string $job, protected array $data, ?PayloadMetadata $metadata = null)
    {
        $this->metadata = $metadata ?? new PayloadMetadata();
    }

    public function getJob(): string
    {
        return $this->job;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getMetadata(): PayloadMetadata
    {
        return $this->metadata;
    }

    public function setMetadata(PayloadMetadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Set the queue name
     *
     * @throws QueueException
     */
    public function setQueue(string $queue): self
    {
        $this->validateQueue($queue);

        $this->metadata->set('queue', $queue);

        return $this;
    }

    public function getQueue(): ?string
    {
        return $this->metadata->get('queue');
    }

    /**
     * Set the priority
     *
     * @throws QueueException
     */
    public function setPriority(string $priority): self
    {
        $this->validatePriority($priority);

        $this->metadata->set('priority', $priority);

        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->metadata->get('priority');
    }

    /**
     * Set the delay
     *
     * @throws QueueException
     */
    public function setDelay(int $delay): self
    {
        $this->validateDelay($delay);

        $this->metadata->set('delay', $delay);

        return $this;
    }

    public function getDelay(): ?int
    {
        return $this->metadata->get('delay');
    }

    public function setChainedJobs(PayloadCollection $payloads): self
    {
        $this->metadata->setChainedJobs($payloads);

        return $this;
    }

    public function getChainedJobs(): ?PayloadCollection
    {
        return $this->metadata->getChainedJobs();
    }

    public function hasChainedJobs(): bool
    {
        return $this->metadata->hasChainedJobs();
    }

    public function jsonSerialize(): array
    {
        return [
            'job'      => $this->job,
            'data'     => $this->data,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create a Payload from an array
     */
    public static function fromArray(array $data): self
    {
        $job      = $data['job'] ?? '';
        $jobData  = $data['data'] ?? [];
        $metadata = isset($data['metadata']) ? PayloadMetadata::fromArray($data['metadata']) : null;

        return new self($job, $jobData, $metadata);
    }
}
