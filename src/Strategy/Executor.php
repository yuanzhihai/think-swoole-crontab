<?php

namespace ThinkCrontab\Strategy;

use Carbon\Carbon;
use InvalidArgumentException;
use Swoole\Timer;
use think\App;
use think\Console;
use think\Log;
use ThinkCrontab\Crontab;
use Throwable;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

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
                    $input       = $this->app->make( ArrayInput::class,[$crontab->getCallback()] );
                    $output      = $this->app->make( NullOutput::class );
                    $application = $this->app->get( Console::class );
                    $runnable    = function () use ($application,$input,$output) {
                        if ($application->doRun( $input,$output ) !== 0) {
                            throw new \RuntimeException( 'Crontab task failed to execute.' );
                        }
                    };
                    break;
                default:
                    throw new InvalidArgumentException( sprintf( 'Crontab task type [%s] is invalid.',$crontab->getType() ) );
            }

            $runnable = function ($isClosing) use ($crontab,$runnable) {
                if ($isClosing) {
                    $crontab->close();
                    $this->logResult( $crontab,false );
                    return;
                }
                $runnable();
                $crontab->complete();
            };
            Timer::after( max( $diff,0 ),$runnable );
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