<?php
namespace karmabunny\visor;

/**
 * Configure a server instance.
 *
 * @package karmabunny\visor
 */
class ServerConfig
{

    /** @var string */
    public $host = 'localhost';

    /** @var int */
    public $port = 8080;

    /** @var int milliseconds */
    public $wait = 100;

    /** @var bool */
    public $autostop = true;

    /**
     * Configure a working directory where the server will also log files.
     *
     * If your application requires a specific directory it needs to perform
     * a chdir() during its own bootstrap.
     *
     * @var string|null
     */
    public $path;


    /**
     * Create a configuration object.
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
