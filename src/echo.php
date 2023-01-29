<?php

if (
    PHP_SAPI === 'cli-server'
    and get_included_files()[0] === __FILE__
) {
    // Path.
    [$path, $query_string] = explode('?', $_SERVER['REQUEST_URI'], 2) + [null, null];

    // Method.
    $method = strtoupper(@$_SERVER['REQUEST_METHOD'] ?: 'GET');

    // Query strings.
    $query = null;
    parse_str($query_string, $query);

    // JSON bodies.
    $body = file_get_contents('php://input');
    $body = json_decode($body, true);

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

    // Dump it.
    $payload = json_encode([
        'headers' => $headers,
        'path' => $path,
        'query' => $query,
        'method' => $method,
        'body' => $body,
    ], JSON_PRETTY_PRINT);

    header('content-type: application/json');
    echo $payload;

    file_put_contents(getcwd() . '/latest.json', $payload);
}
