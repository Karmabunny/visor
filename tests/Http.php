<?php

/**
 * Just a minimal request helper built on fopen.
 *
 * Seriously don't use this for anything, it's so dumb.
 */
class Http
{

    /** @var array */
    protected static $headers = [];

    /** @var int */
    protected static $status = 0;


    /**
     * Perform a request.
     *
     * @param string $url
     * @param array $headers
     * @param string|null $body
     * @return string|false
     */
    public static function request(string $url, array $headers = [], ?string $body = null)
    {
        $http = [];

        if ($body !== null) {
            $http['method'] = 'POST';
            $http['content'] = $body;
            $http['ignore_errors'] = true;
        }

        $header = '';
        foreach ($headers as $name => $value) {
            $header .= "{$name}: {$value}\r\n";
        }

        $http['header'] = rtrim($header, "\r\n");

        $context = stream_context_create([ 'http' => $http ]);
        $res = @file_get_contents($url, false, $context);

        self::$status = 0;
        self::$headers = [];

        // Parse status code.
        $matches = [];
        $status = array_shift($http_response_header);

        if (preg_match('/ ([0-9]+) /', $status, $matches)) {
            self::$status = (int) $matches[1];
        }

        // Parse headers.
        foreach ($http_response_header as $header) {
            $parts = explode(':', $header, 2);
            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1] ?? '');
            self::$headers[$name] = $value;
        }

        return $res;
    }


    /**
     * The status code of the most recent response.
     *
     * @return int
     */
    public static function getStatusCode(): int
    {
        return self::$status;
    }


    /**
     * Get the response headers of the most recent response.
     *
     * @return string[] [name => value]
     */
    public static function getHeaders(): array
    {
        return self::$headers;
    }
}
