<?php

namespace karmabunny\visor;

/**
 *
 * @package karmabunny/echoserver
 */
class CliRequest
{
    /** @var string */
    public $path;

    /** @var string[]|array[] [key => value] */
    public $query;

    /** @var string */
    public $method;

    /** @var string[] [key => value] */
    public $headers;

    /** @var string */
    public $body;


    protected function __construct(array $config)
    {
        foreach ($config as $key => $value) {
            if (!property_exists($this, $key)) continue;
            $this->$key = $value;
        }
    }


    public static function create(bool $log = true): self
    {
        // Path.
        [$path, $query_string] = explode('?', $_SERVER['REQUEST_URI'], 2) + ['', ''];

        // Method.
        $method = strtoupper(@$_SERVER['REQUEST_METHOD'] ?: 'GET');

        // Query strings.
        $query = null;
        parse_str($query_string, $query);

        // Parse headers.
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            $key = strtolower($key);

            if (strpos($key, 'http_') === 0) {
                $key = str_replace('_', '-', substr($key, 5));
            }
            else if (strpos($key, 'content_') === 0) {
                $key = str_replace('_', '-', $key);
            }
            else {
                continue;
            }

            $headers[$key] = $value;
        }

        // Raw bodies.
        // Doesn't support multipart/form-data.
        $body = file_get_contents('php://input');
        if ($body === false) {
            $body = null;
        }

        $request = new self(compact('path', 'query', 'method', 'headers', 'body'));

        if ($log) {
            error_log("[{$request->method}] {$request->path}");
        }

        return $request;
    }


    public function toArray(): array
    {
        return get_object_vars($this);
    }

}
