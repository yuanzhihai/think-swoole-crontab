<?php
declare( strict_types = 1 );

namespace ThinkCrontab;

use RuntimeException;

class Channel extends \Swoole\Coroutine\Channel
{
    protected bool $closed = false;

    public function push(mixed $data,float $timeout = -1): bool
    {
        return parent::push( $data,$timeout );
    }

    public function pop(float $timeout = -1): mixed
    {
        return parent::pop( $timeout );
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function getLength(): int
    {
        return $this->length();
    }

    public function isAvailable(): bool
    {
        return !$this->isClosing();
    }

    public function close(): bool
    {
        $this->closed = true;
        return parent::close();
    }

    public function hasProducers(): bool
    {
        throw new RuntimeException( 'Not supported.' );
    }

    public function hasConsumers(): bool
    {
        throw new RuntimeException( 'Not supported.' );
    }

    public function isReadable(): bool
    {
        throw new RuntimeException( 'Not supported.' );
    }

    public function isWritable(): bool
    {
        throw new RuntimeException( 'Not supported.' );
    }

    public function isClosing(): bool
    {
        return $this->closed || $this->errCode === SWOOLE_CHANNEL_CLOSED;
    }

    public function isTimeout(): bool
    {
        return !$this->closed && $this->errCode === SWOOLE_CHANNEL_TIMEOUT;
    }
}