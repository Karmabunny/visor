<?php

use karmabunny\echoserver\EchoServer;
use PHPUnit\Framework\TestCase;

/**
 * A basic echo test of path + body + headers.
 *
 * The echo server is created in the before-class hook.
 */
class EchoTest extends TestCase
{

    /** @var EchoServer */
    public static $server;


    public static function setUpBeforeClass(): void
    {
        if (!self::$server) {
            self::$server = EchoServer::create([
                'logpath' => dirname(__DIR__) . '/echolog',
            ]);
        }
    }


    public function testEcho()
    {
        $rando1 = sha1(random_bytes(16));
        $rando2 = sha1(random_bytes(16));

        $query = [ 'rando1' => $rando1 ];
        $path = self::$server->getHostUrl() . '/hello-world.json?' . http_build_query($query);
        $headers = [ 'content-type' => 'application/json' ];
        $body = [ 'rando2' => $rando2 ];

        self::request($path, $headers, json_encode($body));

        $res = self::$server->getLastPayload();

        $this->assertEquals('/hello-world.json', $res['path']);
        $this->assertEquals('POST', $res['method']);
        $this->assertEquals('application/json', $res['headers']['content-type']);
        $this->assertEquals($query, $res['query']);
        $this->assertEquals($body, $res['body']);
    }



    /**
     * Perform a request.
     *
     * @param mixed $url
     * @param array $headers
     * @param mixed $body
     * @return string|false
     */
    public static function request(string $url, array $headers = [], $body = null)
    {
        $http = [];

        if ($body !== null) {
            $http['method'] = 'POST';
            $http['content'] = $body;
            $http['ignore_errors'] = true;
        }

        $header = '';
        foreach ($headers as $name => $value) {
            $header .= "{$name}: {$value}\r\n";
        }

        $http['header'] = rtrim($header, "\r\n");

        $context = stream_context_create([ 'http' => $http ]);
        return @file_get_contents($url, false, $context);
    }
}
