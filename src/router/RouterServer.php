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

    protected $controller_files = [];

    public $routes = [];


    public function init(): void
    {
        parent::init();

        if ($this->config->path and is_dir($this->config->path . '/controllers')) {
            $this->loadControllers($this->config->path . '/controllers');
        }
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

        foreach ($this->controller_files as $file) {
            (static function($_file) {
                require_once $_file;
            })($file);
        }

        return $routes;
    }


    public function loadControllers(string $path): array
    {
        $files = scandir($path);

        $this->controllers = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $file = $path . '/' . $file;
            $content = file_get_contents($file);
            $matches = [];

            $matches = [];
            if (!preg_match('/class\s+([^\s]+)\s+extends\s+Controller/', $content, $matches)) {
                continue;
            }

            [, $class] = $matches;

            $matches = [];
            if (preg_match('/namespace\s+([^\s]+)\s*;/', $content, $matches)) {
                [, $namespace] = $matches;
                $class = $namespace . '\\' . $class;
            }
            else {
                $this->controller_files[] = $file;

                (static function($_file) {
                    require_once $_file;
                })($file);
            }

            if (!is_subclass_of($class, Controller::class, true)) {
                continue;
            }

            $this->controllers[] = $class;
        }

        return $this->controllers;
    }
}
