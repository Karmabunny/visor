<?php

use karmabunny\visor\mock\MockServer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Http.php';

/**
 * Testing the mock server.
 *
 * Kinda wild, testing a testing library.
 */
class MockTest extends TestCase
{

    /** @var MockServer */
    public static $server;


    public static function setUpBeforeClass(): void
    {
        if (!self::$server) {
            self::$server = MockServer::create([
                'path' => dirname(__DIR__) . '/logs/mock',
                'port' => 5631,
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


    public function testMock()
    {
        $rando1 = sha1(random_bytes(16));
        $rando2 = sha1(random_bytes(16));

        // Set up a mock response.
        $expected_res_headers = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        $expected_res_body = 'hello=' . $rando2;

        self::$server->setMock('hello-mock.json', $expected_res_headers, $expected_res_body);

        // Build a request.
        $expected_req_query = [ 'rando1' => $rando1 ];
        $expected_req_path = self::$server->getHostUrl() . '/hello-mock.json?' . http_build_query($expected_req_query);
        $expected_req_headers = [ 'content-type' => 'application/json' ];
        $expected_req_body = [ 'rando3' => $rando1 . $rando2 ];

        // Fire off.
        $actual_res_body = Http::request($expected_req_path, $expected_req_headers, json_encode($expected_req_body));
        $actual_res_headers = Http::getHeaders();

        // Check the response.
        $this->assertEquals($expected_res_body, $actual_res_body);
        $this->assertEquals($expected_res_headers['content-type'], $actual_res_headers['content-type']);

        $actual_req = self::$server->getLastPayload();

        // Check the request.
        $this->assertEquals('/hello-mock.json', $actual_req['path']);
        $this->assertEquals('POST', $actual_req['method']);
        $this->assertEquals('application/json', $actual_req['headers']['content-type']);
        $this->assertEquals($expected_req_query, $actual_req['query']);
        $this->assertEquals($expected_req_body, json_decode($actual_req['body'], true));
    }
}
