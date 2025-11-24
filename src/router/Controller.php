<?php
namespace karmabunny\visor\router;

use karmabunny\router\Action;
use karmabunny\visor\CliRequest;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * A base controller.
 *
 * @package karmabunny\visor
 */
abstract class Controller
{

    /** @var RouterServer */
    public $server;

    /** @var CliRequest */
    public $request;

    /** @var ResponseInterface */
    public $response;

    /** @var Action|null */
    public $action;


    /**
     * Initialize the controller.
     *
     * @return void
     */
    public function init(): void
    {
    }


    /**
     * Run an action.
     *
     * @param Action $action
     * @return mixed
     */
    public function _run(Action $action)
    {
        $this->action = $action;
        return $action->invoke($this);
    }


    /**
     * Log a message to the server log.
     *
     * @param string $message
     * @return void
     */
    public function log(string $message)
    {
        $this->server->log($message);
    }


    /**
     * Send a JSON response.
     *
     * @param mixed $data
     * @return ResponseInterface
     */
    public function asJson($data)
    {
        $this->response = $this->response->withHeader('Content-Type', 'application/json');
        $this->response = $this->response->withBody(Stream::create(json_encode($data)));
        return $this->response;
    }


    /**
     * Send a response to the client.
     *
     * @param ResponseInterface $response
     * @return void
     */
    public static function send(ResponseInterface $response)
    {
        $version = $response->getProtocolVersion();
        $reason = $response->getReasonPhrase();
        $status = $response->getStatusCode();

        header("HTTP/{$version} {$status} {$reason}", true, $status);

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("{$name}: {$value}", false);
            }
        }

        $stream = $response->getBody();

        if ($stream->isReadable()) {
            while (!$stream->eof()) {
                echo $stream->read(1024 * 8);
            }
        }
    }
}
