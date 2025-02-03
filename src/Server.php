<?php
namespace karmabunny\visor;

/**
 * A server instance manages the lifecycle of a CLI server script.
 *
 * This does not implement the CLI server itself but instead will create and
 * destroy a server instance. This is particularly useful for integration tests,
 * should your application support the CLI mode server.
 *
 * The log is located in the working directory: `visor.log`
 *
 * For an example implementation, {@see EchoServer} and the `echo.php` script.
 *
 * @package karmabunny\visor
 */
abstract class Server
{
    /** @var ServerConfig */
    public $config;

    /** @var resource|null */
    protected $process = null;

    /** @var string */
    protected $server_id;

    /** @var string The CWD on start */
    protected $cwd;

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
        $this->server_id = uniqid(strtolower(preg_replace('/[^0-9a-z]+/i', '-', static::class)));
        $this->cwd = getcwd();
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
        $this->cwd = getcwd();

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
        $logpath = $this->getLogPath();
        $docroot = $this->getDocRootPath();

        if (!is_dir($path)) {
            mkdir($path, 0770, true);
        }

        $this->log('--------------------');

        $descriptors = [];
        // stdin
        $descriptors[0] = ['pipe', 'r'];
        // stdout
        $descriptors[1] = ['file', $logpath, 'a'];
        // stderr
        $descriptors[2] = ['file', $logpath, 'a'];

        $cmd = self::escape('exec php -S {addr} -t {docroot} {self}', [
            'addr' => sprintf('%s:%d', $this->config->host, $this->config->port),
            'docroot' => $docroot,
            'self' => $target,
        ]);

        $this->log("cwd: {$path}");
        $this->log("Executing: {$cmd}");

        $pipes = [];
        $this->process = proc_open($cmd, $descriptors, $pipes, $path);

        // Check it.
        usleep($this->config->wait * 1000);
        $status = proc_get_status($this->process);

        if (!$status['running']) {
            $this->log('Failed to start server');
            throw new \Exception("Failed to start server\nView logs at: {$logpath}");
        }

        $this->log("Server PID: {$status['pid']}");

        // Tidy up later.
        if ($this->config->autostop) {
            $this->log('Registering shutdown hook');
            register_shutdown_function([$this, 'stop']);
        }

        $ok = $this->healthCheck();

        if (!$ok) {
            $this->log('Failed to verify server connection');
            throw new \Exception("Failed to verify server connection\nView logs at: {$logpath}");
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
     * This logs to a temp folder if 'path' is not specified.
     *
     * @return string
     */
    public function getWorkingPath(): string
    {
        return rtrim($this->config->path ?: (sys_get_temp_dir() . '/' . $this->server_id), '/');
    }


    /**
     * Get the docroot where to serve files from.
     *
     * This the working directory if 'docroot' is not specified.
     *
     * @return string
     */
    public function getDocRootPath(): string
    {
        return rtrim($this->config->docroot ?: $this->cwd, '/');
    }


    /**
     * Where log files will be written.
     *
     * Defaults to the working path if 'path' is not configured.
     *
     * @return string
     */
    public function getLogPath(): string
    {
        return $this->getWorkingPath() . '/visor.log';
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
     * Return current logs.
     *
     * @return string
     */
    public function getLogs(): string
    {
        $path = $this->getLogPath();
        return @file_get_contents($path) ?: '';
    }


    /**
     * Log a message to the server log.
     *
     * @param string $message
     * @return void
     */
    protected function log(string $message)
    {
        $path = $this->getLogPath();
        $ts = date('D M d H:i:s Y');
        $message = "[{$ts}] VISOR: {$message}\n";
        file_put_contents($path, $message, FILE_APPEND);
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
