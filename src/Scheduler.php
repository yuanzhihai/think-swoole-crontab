<?php
declare( strict_types = 1 );

namespace ThinkCrontab;

class Scheduler
{
    /**
     * @var CrontabManager
     */
    protected CrontabManager $crontabManager;

    /**
     * @var \SplQueue
     */
    protected \SplQueue $schedules;

    public function __construct(CrontabManager $crontabManager)
    {
        $this->schedules      = new \SplQueue();
        $this->crontabManager = $crontabManager;
    }

    public function schedule(): \SplQueue
    {
        foreach ( $this->getSchedules() ?? [] as $schedule ) {
            $this->schedules->enqueue( $schedule );
        }
        return $this->schedules;
    }

    protected function getSchedules(): array
    {
        return $this->crontabManager->parse();
    }
}