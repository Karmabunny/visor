<?php

use karmabunny\visor\router\RouterServer;
use PHPUnit\Framework\TestCase;

/**
 * Testing the routers server.
 */
class RouterTest extends TestCase
{

    /** @var RouterServer */
    public static $server;


    public static function setUpBeforeClass(): void
    {
        if (!self::$server) {
            self::$server = RouterServer::create([
                'path' => dirname(__DIR__) . '/logs/router',
                'port' => 5632,
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

    public function testRouter()
    {
        $res = @file_get_contents(self::$server->getHostUrl() . '/_healthcheck');
        $this->assertEquals(self::$server->getServerId(), $res);

        $res = @file_get_contents(self::$server->getHostUrl() . '/test');
        $this->assertFalse($res);

        self::$server->loadControllers(__DIR__ . '/controllers');
        self::$server->reload();

        $res = @file_get_contents(self::$server->getHostUrl() . '/test');
        $this->assertEquals('Hello, world!', $res);
    }
}
