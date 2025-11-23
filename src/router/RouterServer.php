<?php
namespace karmabunny\visor\router;

use karmabunny\visor\Server;

/**
 * A server with controllers + routes.
 *
 * @package karmabunny\visor
 */
class RouterServer extends Server
{

    protected $controllers = [];

    public $routes = [];


    public function __construct($config = [])
    {
        parent::__construct($config);
    }


    /** @inheritdoc */
    public function healthCheck(): bool
    {
        $res = @file_get_contents($this->getHostUrl() . '/_healthcheck');
        return $res === $this->server_id;
    }


    /** @inheritdoc */
    protected function getTargetScript(): string
    {
        return __DIR__ . '/router.php';
    }


    public function getRoutes(): array
    {
        $routes = array_merge($this->routes, $this->controllers);
        return $routes;
    }


    protected function loadControllers(string $path): array
    {
        $files = scandir($path);

        $this->controllers = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $file = $path . '/' . $file;
            $content = file_get_contents($file);
            $matches = [];

            $PATTERN = '/
                (?:namespace\s+([^\s]+)\s*;)|
                (?:class\s+([^\s]+)\s+extends\s+Controller)
            /xi';

            if (!preg_match_all($PATTERN, $content, $matches, PREG_SET_ORDER)) {
                continue;
            }

            $namespace = $matches[0][1] ?? '';
            $class = $matches[1][2] ?? '';

            $class = ($namespace ? $namespace . '\\' : '') . $class;

            if (!is_subclass_of($class, Controller::class, true)) {
                continue;
            }

            $this->controllers[] = $class;
        }

        return $this->controllers;
    }
}
