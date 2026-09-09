<?php

declare(strict_types=1);

namespace App\Services\Postal;

use Illuminate\Support\Facades\Redis;

/**
 * Redis SET implementation of the random-recipient pool. Uses the `predis`
 * client so no PHP extension is required (ADR-0010).
 */
class RedisRandomRecipientPool implements RandomRecipientPool
{
    private string $key;

    public function __construct(?string $key = null)
    {
        $this->key = $key ?? (string) config('postal.random.pool_key', 'random-pool:recipients');
    }

    public function replace(array $userIds): void
    {
        $tmp = $this->key.':building:'.uniqid();

        if ($userIds !== []) {
            Redis::sadd($tmp, ...$userIds);
            Redis::rename($tmp, $this->key);
        } else {
            Redis::del($this->key);
        }
    }

    public function random(): ?string
    {
        $member = Redis::srandmember($this->key);

        return is_string($member) && $member !== '' ? $member : null;
    }

    public function forget(string $userId): void
    {
        Redis::srem($this->key, $userId);
    }

    public function count(): int
    {
        return (int) Redis::scard($this->key);
    }

    public function all(): array
    {
        /** @var list<string> $members */
        $members = Redis::smembers($this->key);

        return $members;
    }
}
