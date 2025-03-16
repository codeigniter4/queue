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

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
class PayloadCollection implements IteratorAggregate, Countable, JsonSerializable
{
    /**
     * Create a new payload collection
     *
     * @param list<T> $items
     */
    public function __construct(protected array $items = [])
    {
    }

    /**
     * Add a payload to the collection
     */
    public function add(Payload $payload): self
    {
        $this->items[] = $payload;

        return $this;
    }

    /**
     * Get the first payload and remove it.
     */
    public function shift(): ?Payload
    {
        if ($this->count() === 0) {
            return null;
        }

        return array_shift($this->items);
    }

    /**
     * Convert the collection to an array
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->items as $payload) {
            $result[] = $payload->jsonSerialize();
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator<int, T>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Create a new PayloadCollection from an array
     */
    public static function fromArray(array $payloads): self
    {
        $collection = new self();

        foreach ($payloads as $payload) {
            if (isset($payload['job'], $payload['data'])) {
                $collection->add(Payload::fromArray($payload));
            }
        }

        return $collection;
    }
}
