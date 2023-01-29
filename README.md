# EchoServer

This is a small utility to wrap the built-in PHP [cli-server](https://www.php.net/manual/en/features.commandline.webserver.php) feature.

There are two parts to this library:

1. An abstract 'server' instance that will manage the lifecyle of any cli-server compatible script.

2. An 'echo server' implementation that, uh, echos. What you say at it, it says back.


## Install

Using Composer:

```
composer require karmabunny/echoserver
```


## Usage

This is ideal for creating small integration tests with a local application or creating mock servers and testing HTTP libraries.


### Server Instance

```php
use karmabunny\echoserver\Server;
use PHPUnit\Framework\TestCase;

/**
 * The application bootstrap is found at: 'index.php'. This must be capable
 * of accepting cli-server requests.
 */
class MyServer extends Server
{
    protected function getTargetScript(): string
    {
        return __DIR__ . '/index.php';
    }
}

class MyServerTest extends TestCase
{
    public function testThings()
    {
        // This create a server at localhost:8080
        MyServer::create();

        // One can then perform tests against the application.
        $res = file_get_contents('http://localhost:8080/health');
        $this->assertEquals('ok', $res);
    }
}
```


### Echo Server

```php
use karmabunny\echoserver\EchoServer;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testThings()
    {
        // This create an echo server at localhost:8080
        $server = EchoServer::create();

        // Imagine this to be some kind of client that hits a remote
        // server of sorts.
        $res = file_get_contents('http://localhost:8080/hello?test=123');

        // Not only is 'res' a JSON body of the payload, the payload is
        // also accessible from the the server instance.

        $payload = $server->getLatestPayload();

        $this->assertEquals('/hello', $payload['path']);
        $this->assertEquals(['test' => '123'], $payload['query']);
    }
}
```


## Config

TODO


## Log files

TODO


## Echo payload

TODO


## TODOs

- publish
- readme todos
- Set response mocks for the echo server to respond with
