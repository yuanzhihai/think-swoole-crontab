<?php
declare( strict_types = 1 );

namespace ThinkCrontab;

use think\Config;
use think\Log;

class CrontabRegister
{
    /**
     * @var CrontabManager
     */
    private CrontabManager $crontabManager;

    /**
     * @var Log
     */
    private Log $logger;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * CrontabRegisterListener constructor.
     * @param CrontabManager $crontabManager
     */
    public function __construct(CrontabManager $crontabManager,Log $logger,Config $config)
    {
        $this->crontabManager = $crontabManager;
        $this->logger         = $logger;
        $this->config         = $config;
    }

    public function handle(): void
    {
        $crontabs = $this->parseCrontabs();
        foreach ( $crontabs as $crontab ) {
            if ($crontab instanceof Crontab) {
                $this->logger->debug( sprintf( 'Crontab %s have been registered.',$crontab->getName() ) );
                $this->crontabManager->register( $crontab );
            }
        }
    }

    private function parseCrontabs(): array
    {
        return $this->config->get( 'crontab.crontab',[] );
    }
}