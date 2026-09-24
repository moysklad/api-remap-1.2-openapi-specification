<?php

declare(strict_types=1);

/**
 * Роутер локального HTTP-стаба (php -S). Состояние лежит в docroot, который задаёт тест:
 * queue.json - очередь фиксированных ответов, hits - обращения к адресу из Location.
 */

$stateDir = $_SERVER['DOCUMENT_ROOT'];
$path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_contains($path, '/print-result')) {
    file_put_contents($stateDir . '/hits', '.', FILE_APPEND);
    http_response_code(200);

    return true;
}

$queueFile = $stateDir . '/queue.json';
$queue = json_decode((string) file_get_contents($queueFile), true) ?: [];
$response = array_shift($queue);
file_put_contents($queueFile, json_encode($queue));

if ($response === null) {
    http_response_code(599);

    return true;
}

if ($response['headerName'] !== null) {
    // Статус задаётся вместе с заголовком: иначе PHP подменяет его на 302 для Location
    header($response['headerName'] . ': ' . $response['headerValue'], true, $response['status']);
} else {
    http_response_code($response['status']);
}

if ($response['body'] !== null) {
    header('Content-Type: application/json;charset=utf-8');
    echo $response['body'];
}

return true;
