<?php
namespace karmabunny\echoserver;

/**
 * Configure a server instance.
 *
 * @package karmabunny\echoserver
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
     * If this is NULL, the instance will decide where the the working
     * directory is located with the {@see Server::getWorkingPath()} method.
     * By default, this inherits the working directory of the host.
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
