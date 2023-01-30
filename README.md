# EchoServer

This is a small utility to wrap the built-in PHP [cli-server](https://www.php.net/manual/en/features.commandline.webserver.php) feature.

There are ~~two~~ three parts to this library:

1. An abstract 'server' instance that will manage the lifecycle of any cli-server compatible script.

2. An 'echo server' implementation that, uh, echos. What you say at it, it says back.

3. A 'mock server' implementation that will reply with defined responses.


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
        $server = MyServer::create();

        // One can then perform tests against the application.
        $res = file_get_contents($server->getHostUrl() . '/health');
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
        // This creates an echo server at localhost:8080
        $server = EchoServer::create();

        // Imagine this to be some kind of client that hits a remote
        // server of sorts.
        $res = file_get_contents($server->getHostUrl() . '/hello?test=123');

        // Not only is 'res' a JSON body of the payload, the payload is
        // also accessible from the the server instance.

        $payload = $server->getLatestPayload();

        $this->assertEquals('/hello', $payload['path']);
        $this->assertEquals(['test' => '123'], $payload['query']);
    }
}
```


### Mock Server

```php
use karmabunny\echoserver\MockServer;
use PHPUnit\Framework\TestCase;

class FakeTest extends TestCase
{
    public function testThings()
    {
        // This creates a mock server at localhost:8080
        $server = MockServer::create();

        $server->setMock('/mock-this', [], 'a fake response');
        $res = file_get_contents($server->getHostUrl() . '/mock-this');

        $payload = $server->getLatestPayload();

        $this->assertEquals('/mock-this', $payload['path']);
        $this->assertEquals('a fake response', $res);
    }
}
```


## Config

| name | -                                              | default   |
|------|------------------------------------------------|-----------|
| host | a binding address                              | localhost |
| port | HTTP port number                               | 8080      |
| wait | pause until the server is ready (milliseconds) | 100       |
| path | working directory of the server                | -         |

By default the log file path is randomised in a temporary system directory.


## Log files

The server emit a series of log files to aid testing and debugging.

Core files:

- `process.log` this is the stdout/stderr of the server process. All request logs and any calls to `error_log()` will appear here.

- `server.log` these are debug points within the host `Server` class itself. Extending classes can emit to the file with the `Server::log()` method.


### Mock + Echo Server

The included implementations will log additional data also.

- `latest.json` is the request payload stored for introspection. Used by `getLastPayload()`.
- `mocks.json` is a store of response objects for the mock server.


## Echo (+ Mock) payloads

Both Mock and Echo servers store the request object in a specific format.

Note that the body is unchanged, if you've sent a JSON or URL payload this will be 'as is' in it's encoded string form.

- `path` - the request path, without the query string
- `query` - an key-value array, from `parse_str()`
- `method` - always uppercase
- `headers` - key-value pairs, keys are lowercase
- `body` - string body, from `php://input`


The JSON-encoded log file looks like this:

```json
{
    "path": "/hello-world.json",
    "query": {
        "rando1": "7bb1166f0cf451cc3eb4cbb977ad932f674aac6c"
    },
    "method": "POST",
    "headers": {
        "host": "localhost:8080",
        "connection": "close",
        "content-length": "53",
        "content-type": "application/json"
    },
    "body": "{\"rando2\":\"267f3bf70d8939c2c7e77d1f8ea164e1df071bba\"}"
}
```
