<?php
namespace karmabunny\echoserver;

/**
 *
 * @package karmabunny\echoserver
 */
class EchoServer extends Server
{

    /** @var string */
    public $server_id;


    /** @inheritdoc */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->server_id = uniqid('echo');
        $this->target = __DIR__ . '/echo.php';
    }


    /** @inheritdoc */
    public function getWorkingPath(): string
    {
        return $this->config->logpath ?: (sys_get_temp_dir() . '/' . $this->server_id);
    }


    /** @inheritdoc */
    public function start()
    {
        parent::start();

        // Trash the first payload.
        $path = $this->getWorkingPath() . '/latest.json';
        @unlink($path);
    }


    /** @inheritdoc */
    public function healthCheck(): bool
    {
        $test_url = $this->getHostUrl() . '/test.json?success=1';
        $this->log("testing: {$test_url}");

        $test_body = @file_get_contents($test_url);
        $test_body = json_decode($test_body, true);

        $ok = !empty($test_body['query']['success']);

        if (!$ok) {
            $this->log(json_encode($test_body));
        }

        return $ok;
    }


    /**
     *
     * @return array
     */
    public function getLastPayload(): array
    {
        $path = $this->getWorkingPath() . '/latest.json';
        $payload = file_get_contents($path);
        $payload = json_decode($payload, true);
        return $payload;
    }
}