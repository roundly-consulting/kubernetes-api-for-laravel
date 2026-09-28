<?php

declare(strict_types=1);

/*
 * Router for `php -S`: a tiny apiserver stand-in. Logs every request line, then:
 *  - a watch (`watch=1`) sends one event, goes quiet for 2 s, sends a second;
 *  - a log follow (`/log?…follow=true`) does the same with two log lines;
 *  - anything else answers with a small JSON object.
 */

file_put_contents((string) getenv('LOG'), $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']."\n", FILE_APPEND | LOCK_EX);

$query = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
parse_str($query, $params);

$quietly = static function (string $first, string $second): void {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    echo $first;
    flush();
    sleep(2);
    echo $second;
    flush();
};

if (($params['watch'] ?? null) === '1') {
    header('Content-Type: application/json');
    $event = static fn (string $type): string => json_encode(['type' => $type, 'object' => ['kind' => 'Pod', 'metadata' => ['name' => 'a']]])."\n";
    $quietly($event('ADDED'), $event('MODIFIED'));

    return true;
}

if (str_contains($_SERVER['REQUEST_URI'], '/log') && ($params['follow'] ?? null) === 'true') {
    header('Content-Type: text/plain');
    $quietly("first\n", "second\n");

    return true;
}

header('Content-Type: application/json');
echo json_encode(['kind' => 'Status', 'apiVersion' => 'v1', 'metadata' => ['name' => 'x'], 'items' => []]);

return true;
