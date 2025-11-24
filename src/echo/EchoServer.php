<?php
namespace karmabunny\visor\echo;

use karmabunny\visor\Server;

/**
 * A server instance that echos whatever is given to it.
 *
 * The most recent payload is available for inspection with
 * the `getLastPayload()` method.
 *
 * The server implementation is located aside this class, named `echo.php`.
 *
 * @package karmabunny\visor
 */
class EchoServer extends Server
{

    /** @inheritdoc */
    protected function getTargetScript(): string
    {
        return __DIR__ . '/echo.php';
    }


    /** @inheritdoc */
    public function healthCheck(): bool
    {
        $test_url = $this->getHostUrl() . '/test.json?success=1';
        $this->log("testing: {$test_url}");

        $test_body = @file_get_contents($test_url);
        $test_body = json_decode($test_body ?: '[]', true);

        $ok = !empty($test_body['query']['success']);

        if (!$ok) {
            $this->log(json_encode($test_body));
        }
        else {
            // Trash the testing payload.
            $path = $this->getWorkingPath() . '/latest.json';
            @unlink($path);
        }

        return $ok;
    }


    /**
     * Get the last payload received by the echo server.
     *
     * @return array|null
     */
    public function getLastPayload(): ?array
    {
        $path = $this->getWorkingPath() . '/latest.json';
        $payload = @file_get_contents($path);
        $payload = json_decode($payload ?: 'null', true);
        return $payload;
    }
}
