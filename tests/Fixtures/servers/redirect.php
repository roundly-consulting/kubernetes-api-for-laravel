<?php

declare(strict_types=1);

/*
 * Router for `php -S`: an apiserver stand-in behind something that redirects. Logs every
 * request as `METHOD URI auth=<Authorization> body=<body>`, then:
 *  - a path under `/r{code}/` answers {code} with a Location to `/landing/path?token=abc`
 *    on the same origin;
 *  - a path under `/r{code}-{port}/` answers {code} with a Location to
 *    `http://127.0.0.1:{port}/landing/path?token=abc`, another origin;
 *  - anything else answers a small JSON object.
 */

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authorization = $headers['Authorization'] ?? '-';
$body = str_replace(["\r", "\n"], ' ', (string) file_get_contents('php://input'));

file_put_contents(
    (string) getenv('LOG'),
    "{$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} auth={$authorization} body={$body}\n",
    FILE_APPEND | LOCK_EX,
);

if (preg_match('#^/r(\d{3})(?:-(\d+))?/#', $_SERVER['REQUEST_URI'], $redirect) === 1) {
    $target = isset($redirect[2]) ? "http://127.0.0.1:{$redirect[2]}" : '';

    http_response_code((int) $redirect[1]);
    header("Location: {$target}/landing/path?token=abc");

    return true;
}

header('Content-Type: application/json');
echo json_encode(['kind' => 'Secret', 'apiVersion' => 'v1', 'metadata' => ['name' => 'landed'], 'gitVersion' => 'v1.34.0']);

return true;
