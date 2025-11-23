<?php
namespace karmabunny\visor;

use karmabunny\interfaces\ConfigurableInterface;

/**
 * Configure a server instance.
 *
 * @package karmabunny\visor
 */
class ServerConfig implements ConfigurableInterface
{

    /** @var string */
    public $host = 'localhost';

    /**
     * ```
     * array_sum(array_map(fn($c) => ord($c), str_split('visor'))) * 10
     * // => 5630
     * ```
     *
     * @var int
     */
    public $port = 5630;

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
     * Where to serve files from.
     *
     * @var string|null
     */
    public $docroot;


    /**
     * Create a configuration object.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->update($config);
    }


    /** @inheritdoc */
    public function update($config)
    {
        foreach ($config as $key => $value) {
            if (!property_exists($this, $key)) continue;
            $this->$key = $value;
        }
    }
}
