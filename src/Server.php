<?php
namespace karmabunny\visor;

use karmabunny\interfaces\ConfigurableInitInterface;
use karmabunny\visor\errors\VisorException;

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
abstract class Server implements ConfigurableInitInterface
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
     */
    public function __construct()
    {
        $this->config = new ServerConfig();
        $this->server_id = static::generateServerId();
        $this->cwd = getcwd();
    }


    /** @inheritdoc */
    public function __destruct()
    {
        if ($this->config->autostop) {
            $this->stop();
        }
    }


    /** @inheritdoc */
    public function __serialize(): array
    {
        $vars = get_object_vars($this);
        unset($vars['process']);
        return $vars;
    }


    /** @inheritdoc */
    public function init(): void
    {
    }


    /** @inheritdoc */
    public function update($config)
    {
        $this->config->update($config);
    }


    /**
     * Configure and start a server.
     *
     * @param array|ServerConfig $config
     * @return static
     * @throws VisorException
     */
    public static function create($config = [])
    {
        // @phpstan-ignore-next-line: gotta do what we gotta do.
        $server = new static;
        $server->update($config);
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
     * @throws VisorException
     */
    public function start()
    {
        if (PHP_SAPI === 'cli-server') {
            throw new VisorException('Cannot start server in cli-server mode');
        }

        if ($this->isRunning()) {
            return;
        }

        $this->init();

        $this->cwd = getcwd();

        $target = $this->getTargetScript();

        if (!is_file($target)) {
            throw new VisorException("Server target doesn't exist: '{$target}'");
        }

        $path = $this->getWorkingPath();
        $vendor = $this->getVendorPath();
        $logpath = $this->getLogPath();

        if (!is_dir($path)) {
            mkdir($path, 0770, true);
        }

        $this->log('--------------------');

        $this->log("cwd: {$path}");
        $this->log("vendor: {$vendor}");

        $this->writeBinary();
        $this->internalStart();

        // Tidy up later.
        if ($this->config->autostop) {
            $this->log('Registering shutdown hook');
            register_shutdown_function([$this, 'stop']);
        }

        $ok = $this->healthCheck();

        if (!$ok) {
            $this->log('Failed to verify server connection');

            $logs = getenv('CI') ? $this->getLogs() : "View logs at: {$logpath}";
            throw new VisorException("Failed to verify server connection\n" . $logs);
        }
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
     * Reload the server.
     *
     * This will stop the server and start it again.
     *
     * @return void
     */
    public function reload()
    {
        $this->stop();
        $this->writeBinary();
        $this->internalStart();
    }


    /**
     * Check if the server is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        if (!$this->process) {
            return false;
        }

        $status = proc_get_status($this->process);
        return (bool) $status['running'];
    }


    /**
     * Get the command to start the server.
     *
     * @return string
     */
    protected function getCommand(): string
    {
        $docroot = $this->getDocRootPath();
        $target = $this->getTargetScript();

        return self::escape('exec php -S {addr} -t {docroot} {self}', [
            'addr' => sprintf('%s:%d', $this->config->host, $this->config->port),
            'docroot' => $docroot,
            'self' => $target,
        ]);
    }


    /**
     * Start the server.
     *
     * This is the internal implementation of the server start.
     *
     * No validation, no healthcheck, etc.
     *
     * @return void
     */
    protected function internalStart()
    {
        $path = $this->getWorkingPath();
        $logpath = $this->getLogPath();
        $vendor = $this->getVendorPath();
        $binary = $this->getBinaryPath();

        $cmd = $this->getCommand();
        $this->log("Executing: {$cmd}");

        $descriptors = [];
        // stdin
        $descriptors[0] = ['pipe', 'r'];
        // stdout
        $descriptors[1] = ['file', $logpath, 'a'];
        // stderr
        $descriptors[2] = ['file', $logpath, 'a'];


        $pipes = [];
        $this->process = proc_open($cmd, $descriptors, $pipes, $path, [
            'VENDOR_PATH' => $vendor,
            'VISOR_CLASS' => static::class,
            'VISOR_BINARY' => $binary,
        ]);

        // Check it.
        usleep($this->config->wait * 1000);
        $status = proc_get_status($this->process);

        if (!$status['running']) {
            $this->log('Failed to start server');

            $logs = getenv('CI') ? $this->getLogs() : "View logs at: {$logpath}";
            throw new VisorException("Failed to start server\n" . $logs);
        }

        $this->log("Server PID: {$status['pid']}");
    }


    /**
     * Write the server binary.
     *
     * This is used to pass the server state to the child process.
     *
     * @return void
     */
    protected function writeBinary()
    {
        $binary = $this->getBinaryPath();
        file_put_contents($binary, serialize($this));
    }


    /**
     * Get the server ID.
     *
     * This is a unique identifier for the server instance.
     *
     * @return string
     */
    public function getServerId(): string
    {
        return $this->server_id;
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
     * Where the server binary is written.
     *
     * This is used to store the server state and configuration.
     *
     * @return string
     */
    public function getBinaryPath(): string
    {
        return $this->getWorkingPath() . '/visor.bin';
    }


    /**
     * Where the vendor directory is located.
     *
     * This is provided to the server script so it can load its own dependencies.
     *
     * @return string
     * @throws VisorException
     */
    public function getVendorPath(): string
    {
        $path = $this->getDocRootPath();

        for (;;) {
            if (is_dir($path . '/vendor')) {
                return $path . '/vendor';
            }

            $path = dirname($path);

            if ($path == '/') {
                throw new VisorException('Unable to find vendor directory');
            }
        }
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
    public function log(string $message)
    {
        $path = $this->getLogPath();
        $ts = date('D M d H:i:s Y');
        $message = "[{$ts}] VISOR: {$message}\n";
        file_put_contents($path, $message, FILE_APPEND);
    }


    /**
     * Escape args in a CLI string.
     *
     * Like `cmd -x {arg1} {arg2}` to correspond to the key of the args array.
     *
     * This is ripped from karmabunny/kb.
     *
     * @param string $cmd
     * @param array $args
     * @return string
     */
    public static function escape(string $cmd, array $args): string
    {
        return preg_replace_callback('/{([^}]+)}/', function($matches) use ($args) {
            $index = $matches[1];
            return escapeshellarg($args[$index] ?? '');
        }, $cmd);
    }


    protected static function generateServerId(): string
    {
        return uniqid(strtolower(preg_replace('/[^0-9a-z]+/i', '-', static::class)));
    }
}
