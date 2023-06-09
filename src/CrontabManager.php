<?php
declare( strict_types = 1 );

namespace ThinkCrontab;


class CrontabManager
{
    /**
     * @var array<string, Crontab>
     */
    protected array $crontabs = [];

    /**
     * @var Parser
     */
    protected Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function register(Crontab $crontab): bool
    {
        if (!$this->isValidCrontab( $crontab ) || !$crontab->isEnable()) {
            return false;
        }
        $this->crontabs[$crontab->getName()] = $crontab;
        return true;
    }

    public function parse(): array
    {
        $result   = [];
        $crontabs = $this->getCrontabs();
        $last     = time();
        foreach ( $crontabs ?? [] as $key => $crontab ) {
            if (!$crontab instanceof Crontab) {
                unset( $this->crontabs[$key] );
                continue;
            }
            $time = $this->parser->parse( $crontab->getRule(),$last );
            if ($time) {
                foreach ( $time as $t ) {
                    $result[] = clone $crontab->setExecuteTime( $t );
                }
            }
        }
        return $result;
    }

    public function getCrontabs(): array
    {
        return $this->crontabs;
    }

    private function isValidCrontab(Crontab $crontab): bool
    {
        return $crontab->getName() && $crontab->getRule() && $crontab->getCallback() && $this->parser->isValid( $crontab->getRule() );
    }
}