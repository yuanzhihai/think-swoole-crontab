<?php
declare(strict_types=1);

namespace ThinkCrontab\Strategy;

use Carbon\Carbon;
use Closure;
use InvalidArgumentException;
use Swoole\Timer;
use think\App;
use think\cache\driver\Redis;
use think\facade\Console;
use think\helper\Arr;
use think\Log;
use ThinkCrontab\Channel;
use ThinkCrontab\Crontab;
use Throwable;

class Executor
{
    /**
     * @var App
     */
    private App $app;

    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var Log
     */
    private Log $logger;

    public function __construct(App $app)
    {
        $this->app    = $app;
        $this->logger = $app->log;
        $this->redis  = $app->cache->store('redis');
    }

    public function execute(Crontab $crontab)
    {
        if (!$crontab->getExecuteTime()) {
            return;
        }
        try {
            $diff     = Carbon::now()->diffInRealSeconds($crontab->getExecuteTime(), false);
            $runnable = null;
            switch ($crontab->getType()) {
                case 'callback':
                    [$class, $method] = $crontab->getCallback();
                    $parameters = $crontab->getCallback()[2] ?? null;
                    if ($class && $method && class_exists($class) && method_exists($class, $method)) {
                        $runnable = function () use ($class, $method, $parameters, $crontab) {
                            try {
                                $result   = true;
                                $instance = $this->app->make($class);
                                if ($parameters && is_array($parameters)) {
                                    $instance->{$method}(...$parameters);
                                } else {
                                    $instance->{$method}();
                                }
                            } catch (\Throwable $throwable) {
                                $result = false;
                            } finally {
                                $this->logResult($crontab, $result, $throwable ?? null);
                            }
                        };
                    }
                    break;
                case 'command':
                    $command  = $crontab->getCallback();
                    $runnable = function () use ($command, $crontab) {
                        try {
                            $result = true;
                            if (!empty($command['arguments'])) {
                                Console::call($command['command'], [$command['arguments']]);
                            } else {
                                Console::call($command['command']);
                            }
                        } catch (\Throwable $throwable) {
                            $result = false;
                        } finally {
                            $this->logResult($crontab, $result, $throwable ?? null);
                        }
                    };
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('Crontab task type [%s] is invalid.', $crontab->getType()));
            }

            $channel = app()->make(Channel::class);

            $runnable = function () use ($channel, $crontab, $runnable) {
                if ($channel->isClosing()) {
                    $crontab->close();
                    $this->logResult($crontab, false);
                    return;
                }
                $this->decorateRunnable($crontab, $runnable)();
                $crontab->complete();
            };

            Timer::after($diff > 0 ? $diff * 1000 : 1, $runnable);
        } catch (Throwable $exception) {
            $crontab->close();
            throw $exception;
        }
    }

    protected function runInSingleton(Crontab $crontab, Closure $runnable): Closure
    {
        return function () use ($crontab, $runnable) {
            if ($this->existsMutex($crontab) || !$this->createMutex($crontab)) {
                $this->logger->info(sprintf('Crontab task [%s] skipped execution at %s.', $crontab->getName(), date('Y-m-d H:i:s')));
                return;
            }
            try {
                $runnable();
            } finally {
                $this->removeMutex($crontab);
            }
        };
    }

    protected function runOnOneServer(Crontab $crontab, Closure $runnable): Closure
    {
        return function () use ($crontab, $runnable) {
            if (!$this->attemptMutex($crontab)) {
                $this->logger->info(sprintf('Crontab task [%s] skipped execution at %s.', $crontab->getName(), date('Y-m-d H:i:s')));
                return;
            }

            $runnable();
        };
    }


    protected function decorateRunnable(Crontab $crontab, Closure $runnable): Closure
    {
        if ($crontab->isSingleton()) {
            $runnable = $this->runInSingleton($crontab, $runnable);
        }

        if ($crontab->isOnOneServer()) {
            $runnable = $this->runOnOneServer($crontab, $runnable);
        }
        return $runnable;
    }

    protected function logResult(Crontab $crontab, bool $isSuccess, ?Throwable $throwable = null)
    {
        if ($isSuccess) {
            $this->logger->info(sprintf('Crontab task [%s] executed successfully at %s.', $crontab->getName(), date('Y-m-d H:i:s')));
        } else {
            $this->logger->error(sprintf('Crontab task [%s] failed execution at %s.', $crontab->getName(), date('Y-m-d H:i:s')));
        }
    }

    /**
     * 任务标识
     */
    public function mutexName($crontab)
    {
        return 'crontab-' . sha1($crontab->getName() . $crontab->getRule());
    }

    protected function removeMutex(Crontab $crontab)
    {
        return $this->redis->delete($this->mutexName($crontab));
    }

    protected function createMutex(Crontab $crontab)
    {
        return $this->redis->set($this->mutexName($crontab), $crontab->getName(), $crontab->getMutexExpires());
    }

    protected function existsMutex(Crontab $crontab)
    {
        return (bool)$this->redis->has($this->mutexName($crontab));
    }

    protected function attemptMutex(Crontab $crontab)
    {
        $mutexName = 'framework' . DIRECTORY_SEPARATOR . $this->mutexName($crontab);
        $result    = (bool)$this->redis($mutexName, $this->getMacAddress(), $crontab->getMutexExpires());
        if ($result === true) {
            return true;
        }
        return $this->redis->get($mutexName) === $this->getMacAddress();

    }

    protected function getMacAddress(): ?string
    {
        $macAddresses = swoole_get_local_mac();

        foreach (Arr::wrap($macAddresses) as $name => $address) {
            if ($address && $address !== '00:00:00:00:00:00') {
                return $name . ':' . str_replace(':', '', $address);
            }
        }
        return null;
    }
}