<?php
declare( strict_types = 1 );

namespace ThinkCrontab\Strategy;

use Carbon\Carbon;
use Swoole\Coroutine;
use think\App;
use ThinkCrontab\Crontab;

class CoroutineStrategy extends AbstractStrategy
{

    protected Executor $executor;


    public function __construct(App $app)
    {
        $this->executor = $app->make( Executor::class );
        parent::__construct( $app );
    }

    public function dispatch(Crontab $crontab)
    {
        Coroutine::create( function () use ($crontab) {
            if ($crontab->getExecuteTime() instanceof Carbon) {
                $wait = $crontab->getExecuteTime()->getTimestamp() - time();
                $wait > 0 && Coroutine::sleep( $wait );
                $this->executor->execute( $crontab );
            }
        } );
    }


    public function executeOnce(Crontab $crontab)
    {
        Coroutine::create( function () use ($crontab) {
            $this->executor->execute( $crontab );
        } );
    }

}