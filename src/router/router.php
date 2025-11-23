<?php

use karmabunny\interfaces\HttpExceptionInterface;
use karmabunny\router\Router;
use karmabunny\visor\CliRequest;
use karmabunny\visor\errors\VisorException;
use karmabunny\visor\router\Controller;
use karmabunny\visor\router\RouterServer;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

error_reporting(E_ALL ^ E_NOTICE);

$vendor = getenv('VENDOR_PATH');
$class = getenv('VISOR_CLASS');
$binary = getenv('VISOR_BINARY');

require $vendor . '/autoload.php';

$server = unserialize(file_get_contents($binary));

if (!$server instanceof RouterServer) {
    throw new VisorException('Invalid server, not a RouterServer: ' . get_class($server));
}

$router = Router::create([
    'extract' => Router::EXTRACT_ATTRIBUTES,
    'mode' => Router::MODE_SINGLE,
]);

$router->load($server->getRoutes());

$request = CliRequest::create();
$action = $router->find($request->method, $request->path);

if (!$action) {
    error_log('[404] not found');
    http_response_code(404);
    echo 'Not found';
    exit;
}

try {
    if ($action->isController(Controller::class)) {
        [$class, $method] = $action->target;

        /** @var Controller $controller */
        $controller = new $class();
        $controller->server = $server;
        $controller->request = $request;
        $controller->response = new Response();
        $controller->init();

        $result = $controller->_run($action);

        if ($result instanceof ResponseInterface) {
            $response = $result;
        }
        else {
            $response = $controller->response;

            if (is_array($result)) {
                $response = $response->withHeader('Content-Type', 'application/json');
                $response = $response->withBody(Stream::create(json_encode($result)));
            }
            else if ($result instanceof StreamInterface) {
                $response = $response->withBody(Stream::create($result));
            }
            else {
                $response = $response->withBody(Stream::create((string) $result));
            }
        }

        $controller->send($response);
    }
}
catch (HttpExceptionInterface $error) {
    http_response_code($error->getStatusCode());
    error_log('[ERROR] ' . $error->getMessage());
    echo $error->getMessage();
}
catch (Throwable $error) {
    http_response_code(500);
    error_log('[ERROR] ' . $error->getMessage());
    error_log($error->getTraceAsString());
    echo $error->getMessage();
}
