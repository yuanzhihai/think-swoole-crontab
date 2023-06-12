<?php
declare( strict_types = 1 );

namespace ThinkCrontab\Strategy;

use Carbon\Carbon;
use InvalidArgumentException;
use Swoole\Coroutine;
use Swoole\Timer;
use think\App;
use think\facade\Console;
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
     * @var Log
     */
    private Log $logger;

    public function __construct(App $app)
    {
        $this->app    = $app;
        $this->logger = $app->log;
    }

    public function execute(Crontab $crontab)
    {
        if (!$crontab->getExecuteTime()) {
            return;
        }
        try {
            $diff     = Carbon::now()->diffInRealSeconds( $crontab->getExecuteTime(),false );
            $runnable = null;
            switch ( $crontab->getType() ) {
                case 'callback':
                    [$class,$method] = $crontab->getCallback();
                    $parameters = $crontab->getCallback()[2] ?? null;
                    if ($class && $method && class_exists( $class ) && method_exists( $class,$method )) {
                        $runnable = function () use ($class,$method,$parameters,$crontab) {
                            try {
                                $result   = true;
                                $instance = $this->app->make( $class );
                                if ($parameters && is_array( $parameters )) {
                                    $instance->{$method}( ...$parameters );
                                } else {
                                    $instance->{$method}();
                                }
                            } catch ( \Throwable $throwable ) {
                                $result = false;
                            } finally {
                                $this->logResult( $crontab,$result,$throwable ?? null );
                            }
                        };
                    }
                    break;
                case 'command':
                    $command  = $crontab->getCallback();
                    $runnable = function () use ($command,$crontab) {
                        try {
                            $result = true;
                            if (!empty( $command['arguments'] )) {
                                Console::call( $command['command'],[$command['arguments']] );
                            } else {
                                Console::call( $command['command'] );
                            }
                        } catch ( \Throwable $throwable ) {
                            $result = false;
                        } finally {
                            $this->logResult( $crontab,$result,$throwable ?? null );
                        }
                    };
                    break;
                default:
                    throw new InvalidArgumentException( sprintf( 'Crontab task type [%s] is invalid.',$crontab->getType() ) );
            }

            $channel = app()->make( Channel::class );

            $runnable = function () use ($channel,$crontab,$runnable) {
                if ($channel->isClosing()) {
                    $crontab->close();
                    $this->logResult( $crontab,false );
                    return;
                }
                Coroutine::create( $runnable );
                $crontab->complete();
            };

            Timer::after( $diff > 0 ? $diff * 1000 : 1,$runnable );
        } catch ( Throwable $exception ) {
            $crontab->close();
            throw $exception;
        }
    }


    protected function logResult(Crontab $crontab,bool $isSuccess,?Throwable $throwable = null)
    {
        if ($isSuccess) {
            $this->logger->info( sprintf( 'Crontab task [%s] executed successfully at %s.',$crontab->getName(),date( 'Y-m-d H:i:s' ) ) );
        } else {
            $this->logger->error( sprintf( 'Crontab task [%s] failed execution at %s.',$crontab->getName(),date( 'Y-m-d H:i:s' ) ) );
        }
    }
}