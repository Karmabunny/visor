<?php

use karmabunny\visor\ditto\DittoServer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Http.php';

/**
 * A basic echo test of path + body + headers.
 *
 * The ditto server is created in the before-class hook.
 */
class DittoTest extends TestCase
{

    /** @var DittoServer */
    public static $server;


    public static function setUpBeforeClass(): void
    {
        if (!self::$server) {
            self::$server = DittoServer::create([
                'path' => dirname(__DIR__) . '/logs/echo',
            ]);
        }
    }


    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            self::$server->stop();
            self::$server = null;
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

        Http::request($path, $headers, json_encode($body));

        $res = self::$server->getLastPayload();

        $this->assertEquals('/hello-world.json', $res['path']);
        $this->assertEquals('POST', $res['method']);
        $this->assertEquals('application/json', $res['headers']['content-type']);
        $this->assertEquals($query, $res['query']);
        $this->assertEquals($body, json_decode($res['body'], true));
    }
}
