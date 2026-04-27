<?php

declare(strict_types=1);

/**
 * Swoole Context
 *
 * Per-coroutine storage backed by Swoole\Coroutine::getContext().
 * Each coroutine gets its own isolated ArrayObject, automatically
 * destroyed by Swoole when the coroutine exits.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Swoole;

use ArrayObject;
use PHPdot\Contracts\Container\ContextInterface;
use RuntimeException;
use Swoole\Coroutine;

final class SwooleContext implements ContextInterface
{
    /**
     * Get the current coroutine's context ArrayObject.
     *
     * @return ArrayObject<string, object>
     */
    private function context(): ArrayObject
    {
        $ctx = Coroutine::getContext();

        if (!$ctx instanceof ArrayObject) {
            throw new RuntimeException('Swoole coroutine context is not available.');
        }

        return $ctx;
    }

    /**
     * Check if a service exists in the current coroutine's context.
     */
    public function has(string $id): bool
    {
        return isset($this->context()[$id]);
    }

    /**
     * Get a service from the current coroutine's context.
     */
    public function get(string $id): object|null
    {
        /** @var object|null */
        return $this->context()[$id] ?? null;
    }

    /**
     * Store a service in the current coroutine's context.
     */
    public function set(string $id, object $instance): void
    {
        $this->context()[$id] = $instance;
    }

    /**
     * Remove a service from the current coroutine's context.
     */
    public function unset(string $id): void
    {
        unset($this->context()[$id]);
    }

    /**
     * Clear all services from the current coroutine's context.
     */
    public function reset(): void
    {
        $this->context()->exchangeArray([]);
    }
}
