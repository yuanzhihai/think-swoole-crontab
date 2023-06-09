<?php
declare( strict_types = 1 );

namespace ThinkCrontab\Strategy;

use Carbon\Carbon;
use Swoole\Coroutine;
use ThinkCrontab\Crontab;

class CoroutineStrategy extends AbstractStrategy
{
    public function dispatch(Crontab $crontab)
    {
        Coroutine::create( function () use ($crontab) {
            if ($crontab->getExecuteTime() instanceof Carbon) {
                $wait = $crontab->getExecuteTime()->getTimestamp() - time();
                $wait > 0 && Coroutine::sleep( $wait );
                $executor = $this->app->make( Executor::class );
                $executor->execute( $crontab );
            }
        } );
    }
}