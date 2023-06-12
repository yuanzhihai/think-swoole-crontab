<?php
declare( strict_types = 1 );

namespace ThinkCrontab\Process;

use Swoole\Coroutine;
use Swoole\Timer;
use think\App;
use think\Log;
use think\swoole\Manager;
use ThinkCrontab\CrontabRegister;
use ThinkCrontab\Scheduler;
use ThinkCrontab\Strategy\CoroutineStrategy;
use ThinkCrontab\Strategy\StrategyInterface;

class CrontabDispatcherProcess
{

    private $server;

    /**
     * @var CrontabRegister
     */
    private CrontabRegister $crontabRegister;

    /**
     * @var Scheduler
     */
    private Scheduler $scheduler;

    /**
     * @var StrategyInterface
     */
    private StrategyInterface $strategy;

    /**
     * @var Log
     */
    private Log $logger;

    public function __construct(App $app)
    {
        $this->server          = $app->make( Manager::class );
        $this->crontabRegister = $app->make( CrontabRegister::class );
        $this->scheduler       = $app->make( Scheduler::class );
        $this->strategy        = $app->make( CoroutineStrategy::class );
        $this->logger          = $app->make( Log::class );
    }

    public function handle(): void
    {
        $func = function () {
            try {
                $this->crontabRegister->handle();
                while ( true ) {
                    $this->sleep();
                    $crontabs = $this->scheduler->schedule();
                    while ( !$crontabs->isEmpty() ) {
                        $crontab = $crontabs->dequeue();
                        $this->strategy->dispatch( $crontab );
                    }
                }
            } catch ( \Throwable $throwable ) {
                $this->logger->error( $throwable->getMessage() );
            } finally {
                Timer::clearAll();
                Coroutine::sleep( 5 );
            }
        };

        $this->server->addWorker( $func );
    }

    private function sleep()
    {
        $current = date( 's',time() );
        $sleep   = 60 - $current;
        $this->logger->debug( 'Crontab dispatcher sleep '.$sleep.'s.' );
        $sleep > 0 && Coroutine::sleep( $sleep );
    }
}