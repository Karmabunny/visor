<?php

use karmabunny\interfaces\HttpExceptionInterface;
use karmabunny\router\Router;
use karmabunny\visor\CliRequest;
use karmabunny\visor\errors\HttpException;
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

$router->load(['/_healthcheck' => function() use ($server) {
    return $server->getServerId();
}]);

$router->load($server->getRoutes());

try {

    $request = CliRequest::create();
    $action = $router->find($request->method, $request->path);

    if (!$action) {
        throw new HttpException(404, 'Not found');
    }

    if ($action->isController(Controller::class)) {
        /** @var Controller $controller */
        $controller = new ($action->target[0])();
        $controller->server = $server;
        $controller->request = $request;
        $controller->response = new Response();
        $controller->init();

        $result = $controller->_run($action);
        $response = $controller->response;
    }
    else if (is_callable($action->target)) {
        $result = ($action->target)(...$action->args);
        $response = new Response();
    }
    else {
        throw new VisorException('Invalid action target: ' . get_class($action->target));
    }

    if ($result instanceof ResponseInterface) {
        $response = $result;
    }
    else {
        if (is_array($result)) {
            $response = $response->withHeader('Content-Type', 'application/json');
            $response = $response->withBody(Stream::create(json_encode($result)));
        }
        else if ($result instanceof StreamInterface) {
            $response = $response->withBody($result);
        }
        else {
            $response = $response->withBody(Stream::create((string) $result));
        }
    }

    Controller::send($response);
}
catch (HttpExceptionInterface $error) {
    http_response_code($error->getStatusCode());
    error_log("[{$error->getStatusCode()}] " . $error->getMessage());
    echo $error->getMessage();
}
catch (Throwable $error) {
    http_response_code(500);
    error_log('[ERROR] ' . $error->getMessage());
    error_log($error->getTraceAsString());
    echo $error->getMessage();
}
