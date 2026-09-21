<?php

declare(strict_types=1);

namespace MoySklad\Tests\Location;

/**
 * Локальный HTTP-стаб с фиксированными ответами и счётчиком обращений к адресу из Location.
 *
 * Сервер поднимается отдельным процессом (php -S), состояние передаётся через файлы в docroot,
 * поэтому тест проверяет реальный HTTP-обмен, а не подменённый Guzzle-handler.
 */
final class LocationStubServer
{
    private string $stateDir;
    private int $port;

    /** @var resource */
    private $process;

    public function __construct()
    {
        $this->stateDir = sys_get_temp_dir() . '/location-stub-' . bin2hex(random_bytes(8));
        mkdir($this->stateDir);
        file_put_contents($this->stateDir . '/queue.json', '[]');
        file_put_contents($this->stateDir . '/hits', '');

        $this->port = self::findFreePort();
        $this->start();
    }

    public function getHost(): string
    {
        return 'http://127.0.0.1:' . $this->port . '/api/remap/1.2';
    }

    public function locationUrl(string $name): string
    {
        return 'http://127.0.0.1:' . $this->port . '/print-result/' . $name;
    }

    /** Количество обращений к адресу, переданному в Location. */
    public function getLocationHits(): int
    {
        return strlen((string) file_get_contents($this->stateDir . '/hits'));
    }

    /** Ответы отдаются в порядке постановки в очередь, по одному на вызов. */
    public function enqueue(int $status, ?string $headerName = null, ?string $headerValue = null, ?string $body = null): void
    {
        $queueFile = $this->stateDir . '/queue.json';
        $queue = json_decode((string) file_get_contents($queueFile), true) ?: [];
        $queue[] = [
            'status' => $status,
            'headerName' => $headerName,
            'headerValue' => $headerValue,
            'body' => $body,
        ];
        file_put_contents($queueFile, json_encode($queue));
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);

        foreach (glob($this->stateDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->stateDir);
    }

    private function start(): void
    {
        $command = sprintf(
            '%s -S 127.0.0.1:%d -t %s %s',
            escapeshellarg(PHP_BINARY),
            $this->port,
            escapeshellarg($this->stateDir),
            escapeshellarg(__DIR__ . '/stub-router.php')
        );

        $descriptors = [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Не удалось запустить локальный HTTP-стаб');
        }

        $this->process = $process;
        $this->waitUntilReady();
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(50_000);
        }

        throw new \RuntimeException('Локальный HTTP-стаб не поднялся за 5 секунд');
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new \RuntimeException('Не удалось подобрать свободный порт: ' . $errstr);
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
