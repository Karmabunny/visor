<?php
namespace karmabunny\echoserver;

/**
 * A server instance manages the lifecycle of a CLI server script.
 *
 * This does not implement the CLI server itself but instead will create and
 * destory a server instance. This is particularly useful for integration tests,
 * should your application support the CLI mode server.
 *
 * There are two log files:
 * - process.log - created by the host PHP (this file)
 * - server.log - created by the server PHP (the 'target' script)
 *
 * For an example implementation, {@see EchoServer} and the `echo.php` script.
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
     * Configure a server instance.
     *
     * This _does not_ start the server instance.
     *
     * Use {@see start()} method or the {@see create()}` shorthand.
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
     * If 'autostop' is configured, destroy the server instance.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->config->autostop) {
            $this->stop();
        }
    }


    /**
     * Configure and start a server.
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
     * The target server script.
     *
     * This is a script that executes in a 'cli-server' context.
     *
     * @return string
     */
    protected abstract function getTargetScript(): string;


    /**
     * Start this server.
     *
     * This does nothing if the server is already running.
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

        if ($this->process) {
            $status = proc_get_status($this->process);

            // Quit early.
            if ($status['running']) {
                return;
            }
            // Otherwise tidy-up.
            else {
                $this->process = null;
            }
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
     * Perform a healthcheck on this server.
     *
     * This is server-specific, the default implementation is a stub.
     *
     * @return bool
     */
    public function healthCheck(): bool
    {
        return true;
    }


    /**
     * Stop this server.
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
            $this->process = null;
            return false;
        }

        $this->log("Shutdown server: {$status['pid']}");

        proc_terminate($this->process);
        proc_close($this->process);

        if (!empty($status['pid'])) {
            posix_kill($status['pid'], SIGKILL);
        }

        $this->process = null;
        return true;
    }


    /**
     * Get the working directory for the server script.
     *
     * This is where log files will be generated.
     *
     * @return string
     */
    public function getWorkingPath(): string
    {
        return $this->config->path ?: getcwd();
    }


    /**
     * Get the host URL (including the schema + port number) of the server.
     *
     * @return string
     */
    public function getHostUrl(): string
    {
        return "http://{$this->config->host}:{$this->config->port}";
    }


    /**
     * Log a message to the server log.
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
     * Escape args in a CLI string.
     *
     * This is ripped from karmabunny/php.
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
