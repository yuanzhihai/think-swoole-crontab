<?php
declare( strict_types = 1 );

namespace ThinkCrontab\Strategy;

use think\App;

abstract class AbstractStrategy implements StrategyInterface
{
    /**
     * @var App
     */
    protected App $app;

    /**
     * AbstractStrategy constructor.
     * @param $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }
}