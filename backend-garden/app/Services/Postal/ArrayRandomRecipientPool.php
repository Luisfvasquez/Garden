<?php

declare(strict_types=1);

namespace App\Services\Postal;

/**
 * In-memory pool. Used for local dev without Redis and as the test double —
 * the picker's DB re-check is what actually guards correctness, so an exact
 * pool implementation is not needed to exercise the flow.
 */
class ArrayRandomRecipientPool implements RandomRecipientPool
{
    /**
     * @var array<string, true>
     */
    private array $members = [];

    public function replace(array $userIds): void
    {
        $this->members = [];
        foreach ($userIds as $id) {
            $this->members[$id] = true;
        }
    }

    public function random(): ?string
    {
        if ($this->members === []) {
            return null;
        }

        $keys = array_keys($this->members);

        return $keys[random_int(0, count($keys) - 1)];
    }

    public function forget(string $userId): void
    {
        unset($this->members[$userId]);
    }

    public function count(): int
    {
        return count($this->members);
    }

    public function all(): array
    {
        return array_keys($this->members);
    }
}
