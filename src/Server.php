<?php
namespace karmabunny\echoserver;

/**
 *
 * @package karmabunny\echoserver
 */
abstract class Server
{
    /** @var ServerConfig */
    public $config;

    /** @var resource|null */
    protected $process = null;


    /**
     *
     * @param array|ServerConfig $config
     */
    public function __construct($config = [])
    {
        if (is_array($config)) {
            $config = new ServerConfig($config);
        }

        $this->config = $config;
    }


    /**
     *
     * @param array|ServerConfig $config
     * @return static
     * @throws \Exception
     */
    public static function create($config = [])
    {
        $server = new static($config);
        $server->start();
        return $server;
    }


    /**
     *
     * @return string
     */
    protected abstract function getTargetScript(): string;


    /**
     *
     * @return void
     * @throws \Exception
     */
    public function start()
    {
        $target = $this->getTargetScript();

        if (!is_file($target)) {
            throw new \Exception("Server target doesn't exist: '{$target}'");
        }

        $path = $this->getWorkingPath();

        if (!is_dir($path)) {
            mkdir($path, 0770, true);
        }

        $this->log('--------------------');

        $descriptors = [];
        // stdin
        $descriptors[0] = ['pipe', 'r'];
        // stdout
        $descriptors[1] = ['file', $path . '/process.log', 'a'];
        // stderr
        $descriptors[2] = ['file', $path . '/process.log', 'a'];

        $cmd = self::escape('exec php -S {addr} {self} test', [
            'addr' => sprintf('%s:%d', $this->config->host, $this->config->port),
            'self' => $target,
        ]);

        $this->log("Executing: {$cmd}");
        $this->log("cwd: {$path}");

        $pipes = [];
        $this->process = proc_open($cmd, $descriptors, $pipes, $path);

        // Check it.
        usleep($this->config->wait * 1000);
        $status = proc_get_status($this->process);

        if (!$status['running']) {
            $this->log('Failed to start echo server');
            throw new \Exception('Failed to start echo server');
        }

        $this->log("Server PID: {$status['pid']}");

        // Tidy up later.
        if ($this->config->autostop) {
            $this->log('Registering shutdown hook');
            register_shutdown_function([$this, 'stop']);
        }

        $ok = $this->healthCheck();

        if (!$ok) {
            $this->log('Failed to connect to echo server');
            throw new \Exception('Failed to connect to echo server');
        }
    }


    /**
     *
     * @return bool
     */
    public function healthCheck(): bool
    {
        return true;
    }


    /**
     *
     * @return bool
     */
    public function stop(): bool
    {
        if (!$this->process) {
            return false;
        }

        $status = proc_get_status($this->process);

        if (!$status['running']) {
            return false;
        }

        $this->log("Shutdown server: {$status['pid']}");

        proc_terminate($this->process);
        proc_close($this->process);

        if (!empty($status['pid'])) {
            posix_kill($status['pid'], SIGKILL);
        }

        return true;
    }


    /**
     *
     * @return string
     */
    public function getWorkingPath(): string
    {
        return $this->config->path ?: getcwd();
    }


    /**
     *
     * @return string
     */
    public function getHostUrl(): string
    {
        return "http://{$this->config->host}:{$this->config->port}";
    }


    /**
     *
     * @param string $message
     * @return void
     */
    protected function log(string $message)
    {
        $path = $this->getWorkingPath();
        $ts = date('Y-m-d H:i:s');
        $message = "[{$ts}] {$message}\n";
        file_put_contents($path . '/server.log', $message, FILE_APPEND);
    }


    /**
     *
     * @param string $cmd
     * @param array $args
     * @return string
     */
    protected static function escape(string $cmd, array $args): string
    {
        return preg_replace_callback('/{([^}]+)}/', function($matches) use ($args) {
            $index = $matches[1];
            return escapeshellarg($args[$index] ?? '');
        }, $cmd);
    }
}
