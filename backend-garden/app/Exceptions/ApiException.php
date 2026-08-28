<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Domain-level API error carrying a stable `error_code` the client reacts to.
 *
 * The HTTP status and machine code are the contract; `message` is human-facing
 * and translatable (see docs/api/_convenciones.md).
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors  Field-level validation detail, optional.
     * @param  array<string, mixed>  $meta  Extra machine-readable context merged into `meta`.
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $errors = [],
        public readonly array $meta = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function make(string $errorCode, string $message, int $status = 422): self
    {
        return new self($message, $errorCode, $status);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function withErrors(array $errors): self
    {
        return new self($this->getMessage(), $this->errorCode, $this->status, $errors, $this->meta, $this->getPrevious());
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        return new self($this->getMessage(), $this->errorCode, $this->status, $this->errors, [...$this->meta, ...$meta], $this->getPrevious());
    }
}
