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

use JsonSerializable;

class PayloadMetadata implements JsonSerializable
{
    public function __construct(protected array $data = [])
    {
    }

    /**
     * Set chained jobs
     */
    public function setChainedJobs(?PayloadCollection $payloads): self
    {
        if ($payloads !== null) {
            $this->data['chainedJobs'] = $payloads;
        } else {
            unset($this->data['chainedJobs']);
        }

        return $this;
    }

    /**
     * Get chained jobs
     */
    public function getChainedJobs(): ?PayloadCollection
    {
        return $this->data['chainedJobs'] ?? null;
    }

    /**
     * Check if has chained jobs
     */
    public function hasChainedJobs(): bool
    {
        return isset($this->data['chainedJobs']) && $this->data['chainedJobs']->count() > 0;
    }

    /**
     * Set a generic metadata value
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get a generic metadata value
     *
     * @param mixed|null $default
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if a metadata key exists
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove a metadata key
     */
    public function remove(string $key): self
    {
        unset($this->data[$key]);

        return $this;
    }

    /**
     * Get all metadata as an array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * JSON serialize implementation
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public static function fromArray(array $data): PayloadMetadata
    {
        $metadata = new self();

        foreach ($data as $key => $value) {
            // Handle chainedJobs specially
            if ($key === 'chainedJobs' && is_array($value)) {
                $payloadCollection = new PayloadCollection();

                foreach ($value as $jobData) {
                    if (isset($jobData['job'], $jobData['data'])) {
                        $payload = Payload::fromArray($jobData);
                        $payloadCollection->add($payload);
                    }
                }

                $metadata->setChainedJobs($payloadCollection);
            } else {
                // Regular metadata
                $metadata->set($key, $value);
            }
        }

        return $metadata;
    }
}
