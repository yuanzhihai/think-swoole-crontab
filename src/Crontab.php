<?php

declare( strict_types = 1 );

namespace ThinkCrontab;

use Carbon\Carbon;


class Crontab
{
    /**
     * @var null|string
     */
    protected ?string $name;

    /**
     * @var string
     */
    protected string $type = 'callback';

    /**
     * @var null|string
     */
    protected ?string $rule;

    /**
     * @var bool
     */
    protected bool $singleton = false;

    /**
     * @var string
     */
    protected string $mutexPool = 'default';

    /**
     * @var int
     */
    protected int $mutexExpires = 3600;

    /**
     * @var bool
     */
    protected bool $onOneServer = false;

    /**
     * @var mixed
     */
    protected mixed $callback;

    /**
     * @var null|string
     */
    protected ?string $memo;

    /**
     * @var null|Carbon
     */
    protected ?Carbon $executeTime;

    protected bool $enable = true;

    protected ?Channel $running = null;

    public function __clone()
    {
        $this->running = new Channel( 1 );
    }

    public function __serialize(): array
    {
        return [
            "\x00*\x00name"         => $this->name,
            "\x00*\x00type"         => $this->type,
            "\x00*\x00rule"         => $this->rule,
            "\x00*\x00singleton"    => $this->singleton,
            "\x00*\x00mutexPool"    => $this->mutexPool,
            "\x00*\x00mutexExpires" => $this->mutexExpires,
            "\x00*\x00onOneServer"  => $this->onOneServer,
            "\x00*\x00callback"     => $this->callback,
            "\x00*\x00memo"         => $this->memo,
            "\x00*\x00executeTime"  => $this->executeTime,
            "\x00*\x00enable"       => $this->enable,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->name         = $data["\x00*\x00name"] ?? $this->name;
        $this->type         = $data["\x00*\x00type"] ?? $this->type;
        $this->rule         = $data["\x00*\x00rule"] ?? $this->rule;
        $this->singleton    = $data["\x00*\x00singleton"] ?? $this->singleton;
        $this->mutexPool    = $data["\x00*\x00mutexPool"] ?? $this->mutexPool;
        $this->mutexExpires = $data["\x00*\x00mutexExpires"] ?? $this->mutexExpires;
        $this->onOneServer  = $data["\x00*\x00onOneServer"] ?? $this->onOneServer;
        $this->callback     = $data["\x00*\x00callback"] ?? $this->callback;
        $this->memo         = $data["\x00*\x00memo"] ?? $this->memo;
        $this->executeTime  = $data["\x00*\x00executeTime"] ?? $this->executeTime;
        $this->enable       = $data["\x00*\x00enable"] ?? $this->enable;
        $this->running      = new Channel( 1 );
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): Crontab
    {
        $this->name = $name;
        return $this;
    }

    public function getRule(): ?string
    {
        return $this->rule;
    }

    public function setRule(?string $rule): Crontab
    {
        $this->rule = $rule;
        return $this;
    }

    public function isSingleton(): bool
    {
        return $this->singleton;
    }

    public function setSingleton(bool $singleton): Crontab
    {
        $this->singleton = $singleton;
        return $this;
    }

    public function getMutexPool(): string
    {
        return $this->mutexPool;
    }

    public function setMutexPool(string $mutexPool): Crontab
    {
        $this->mutexPool = $mutexPool;
        return $this;
    }

    public function getMutexExpires(): int
    {
        return $this->mutexExpires;
    }

    public function setMutexExpires(int $mutexExpires): Crontab
    {
        $this->mutexExpires = $mutexExpires;
        return $this;
    }

    public function isOnOneServer(): bool
    {
        return $this->onOneServer;
    }

    public function setOnOneServer(bool $onOneServer): Crontab
    {
        $this->onOneServer = $onOneServer;
        return $this;
    }

    public function getCallback()
    {
        return $this->callback;
    }

    public function setCallback($callback): Crontab
    {
        $this->callback = $callback;
        return $this;
    }

    public function getMemo(): ?string
    {
        return $this->memo;
    }

    public function setMemo(?string $memo): Crontab
    {
        $this->memo = $memo;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): Crontab
    {
        $this->type = $type;
        return $this;
    }

    public function getExecuteTime(): ?Carbon
    {
        return $this->executeTime;
    }

    public function setExecuteTime(Carbon $executeTime): Crontab
    {
        $this->executeTime = $executeTime;
        return $this;
    }

    public function isEnable(): bool
    {
        return $this->enable;
    }

    public function setEnable(bool $enable): static
    {
        $this->enable = $enable;
        return $this;
    }

    public function complete(): void
    {
        $this->running?->close();
    }

    public function close(): void
    {
        $this->running?->close();
    }

    public function wait(): void
    {
        $this->running?->pop();
    }
}