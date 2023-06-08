<?php
declare( strict_types = 1 );

namespace ThinkCrontab\Strategy;

use ThinkCrontab\Crontab;

interface StrategyInterface
{
    public function dispatch(Crontab $crontab);
}