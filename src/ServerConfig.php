<?php
namespace karmabunny\echoserver;

/**
 *
 * @package karmabunny\echoserver
 */
class ServerConfig
{

    /** @var string */
    public $host = 'localhost';

    /** @var int */
    public $port = 8080;

    /** @var string|null */
    public $logpath;

    /** @var int milliseconds */
    public $wait = 100;

    /** @var bool */
    public $autostop = true;


    /**
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }

}
