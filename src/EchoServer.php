<?php
namespace karmabunny\echoserver;

$_exec = (
    PHP_SAPI === 'cli-server'
    and get_included_files()[0] === __FILE__
);

/**
 *
 * @package karmabunny\echoserver
 */
class EchoServer
{

    /** @var string */
    public $host = 'localhost';

    /** @var int */
    public $port = 8080;

    /** @var string */
    public $logpath;

    /** @var int milliseconds */
    public $wait = 100;

    /** @var bool */
    public $autostop = true;

    /** @var resource|null */
    protected $process = null;


    /**
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

        if ($this->logpath === null) {
            $this->logpath = getcwd() . '/echolog';
        }

        if (!is_dir($this->logpath)) {
            mkdir($this->logpath, 0770, true);
        }
    }


    /**
     *
     * @param array $config
     * @return self
     * @throws \Exception
     */
    public static function create(array $config = [])
    {
        $server = new self($config);
        $server->start();
        return $server;
    }


    /**
     *
     * @return void
     * @throws \Exception
     */
    public function start()
    {
        $this->log('--------------------');

        $descriptors = [
            // stdin
            ['pipe', 'r'],
            // stdout
            ['file', $this->logpath . '/process.log', 'w'],
            // stderr
            ['file', $this->logpath . '/process.log', 'w'],
        ];

        $pipes = [];

        $cmd = self::escape('exec php -S {addr} {self}', [
            'addr' => sprintf('%s:%d', $this->host, $this->port),
            'self' => __FILE__,
        ]);

        $this->log("Executing: {$cmd}");

        $this->process = proc_open($cmd, $descriptors, $pipes, $this->logpath);

        // Check it.
        usleep($this->wait * 1000);
        $status = proc_get_status($this->process);

        if (!$status['running']) {
            $this->log('Failed to start echo server');
            throw new \Exception('Failed to start echo server');
        }

        $this->log("Server PID: {$status['pid']}");

        // Tidy up later.
        if ($this->autostop) {
            $this->log('Registering shutdown hook');
            register_shutdown_function([$this, 'stop']);
        }

        // Check it again.
        $test_url = "http://{$this->host}:{$this->port}/test.json?success=1";
        $this->log($test_url);

        $test_body = @file_get_contents($test_url);
        $test_body = json_decode($test_body, true);

        if (empty($test_body['query']['success'])) {
            $this->log(json_encode($test_body));
            $this->log('Failed to connect to echo server');
            throw new \Exception('Failed to connect to echo server');
        }

        // Trash the first payload.
        @unlink($this->logpath . '/latest.json');
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
     * @return array
     */
    public function getLastPayload(): array
    {
        $payload = $this->logpath . '/latest.json';
        $payload = file_get_contents($payload);
        $payload = json_decode($payload, true);
        return $payload;
    }


    /**
     *
     * @return string
     */
    public function getHostUrl(): string
    {
        return "http://{$this->host}:{$this->port}";
    }


    /**
     *
     * @param string $message
     * @return void
     */
    protected function log(string $message)
    {
        $ts = date('Y-m-d H:i:s');
        $message = "[{$ts}] {$message}\n";
        file_put_contents($this->logpath . '/server.log', $message, FILE_APPEND);
    }


    /**
     *
     * @return void
     * @throws \Exception
     */
    public static function serve()
    {
        if (PHP_SAPI !== 'cli-server') {
            throw new \Exception('Cannot execute EchoServer, sapi=' . PHP_SAPI);
        }

        // Path.
        [$path, $query_string] = explode('?', $_SERVER['REQUEST_URI'], 2) + [null, null];

        // Method.
        $method = strtoupper(@$_SERVER['REQUEST_METHOD'] ?: 'GET');

        // Query strings.
        $query = null;
        parse_str($query_string, $query);

        // JSON bodies.
        $body = file_get_contents('php://input');
        $body = json_decode($body, true);

        // Parse headers.
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            $key = strtolower($key);

            if (strpos($key, 'http_') === 0) {
                $key = str_replace('_', '-', substr($key, 5));
            }
            else if (strpos($key, 'content_') === 0) {
                $key = str_replace('_', '-', $key);
            }
            else {
                continue;
            }

            $headers[$key] = $value;
        }

        // Dump it.
        $payload = json_encode([
            'headers' => $headers,
            'path' => $path,
            'query' => $query,
            'method' => $method,
            'body' => $body,
        ], JSON_PRETTY_PRINT);

        header('content-type: application/json');
        echo $payload;

        file_put_contents(getcwd() . '/latest.json', $payload);
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


if ($_exec) {
    EchoServer::serve();
}
