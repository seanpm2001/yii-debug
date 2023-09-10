<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Yiisoft\Yii\Debug\DevServer;

use Generator;
use RuntimeException;
use Socket;
use Throwable;

/**
 * List of socket errors: {@see https://www.ibm.com/docs/en/zos/2.4.0?topic=calls-sockets-return-codes-errnos}
 */
final class Connection
{
    public const DEFAULT_TIMEOUT = 10 * 1000; // 10 milliseconds
    public const DEFAULT_BUFFER_SIZE = 1 * 1024; // 1 kilobyte

    public const TYPE_RESULT = 0x001B;
    public const TYPE_ERROR = 0x002B;

    public const MESSAGE_TYPE_VAR_DUMPER = 0x001B;
    public const MESSAGE_TYPE_LOGGER = 0x002B;

    private string $uri;

    public function __construct(
        private Socket $socket,
    ) {
    }

    public static function create(): self
    {
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);

        $socket_last_error = socket_last_error($socket);

        if ($socket_last_error) {
            throw new RuntimeException(
                sprintf(
                    '"socket_last_error" returned %d: "%s".',
                    $socket_last_error,
                    socket_strerror($socket_last_error),
                ),
            );
        }

        return new self(
            $socket,
        );
    }

    public function bind(): void
    {
        $n = random_int(0, PHP_INT_MAX);
        $file = sprintf(sys_get_temp_dir() . '/yii-dev-server-%d.sock', $n);
        $this->uri = $file;
        if (!socket_bind($this->socket, $file)) {
            $socket_last_error = socket_last_error($this->socket);

            throw new RuntimeException(
                sprintf(
                    'An error occurred while reading the socket. "socket_last_error" returned %d: "%s".',
                    $socket_last_error,
                    socket_strerror($socket_last_error),
                ),
            );
        }
    }

    /**
     * @return Generator<int, array{0: self::TYPE_ERROR|self::TYPE_RESULT, 1: string, 2: int|string, 3?: int}>
     */
    public function read(): Generator
    {
        while (true) {
            if (!socket_recvfrom($this->socket, $buffer, self::DEFAULT_BUFFER_SIZE, MSG_DONTWAIT, $ip, $port)) {
                $socket_last_error = socket_last_error($this->socket);
                if ($socket_last_error === 35) {
                    usleep(self::DEFAULT_TIMEOUT);
                    continue;
                }
                $this->close();
                yield [self::TYPE_ERROR, $socket_last_error, socket_strerror($socket_last_error)];
                continue;
            }
            yield [self::TYPE_RESULT, $buffer, $ip, $port];
        }
    }

    public function broadcast(int $type, string $data): void
    {
        $files = glob(sys_get_temp_dir() . '/yii-dev-server-*.sock', GLOB_NOSORT);
        //echo 'Files: ' . implode(', ', $files) . "\n";
        $uniqueErrors = [];
        $payload = json_encode([$type, $data]);
        $payloadLength = strlen($payload);
        foreach ($files as $file) {
            $socket = @fsockopen('udg://' . $file, -1, $errno, $errstr);
            if ($errno === 61) {
                @unlink($file);
                continue;
            }
            if ($errno !== 0) {
                $uniqueErrors[$errno] = $errstr;
                continue;
            }
            try {
                if (!@fwrite($socket, $payload, $payloadLength)) {
                    $err = socket_last_error($socket);
                    $uniqueErrors[$err] = socket_strerror($err);
                    /**
                     * Connection is closed.
                     */
                    continue;
                }
            } catch (Throwable $e) {
                //@unlink($file);
                throw $e;
            } finally {
                fflush($socket);
                fclose($socket);
            }
        }
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function close(): void
    {
        @socket_getsockname($this->socket, $path);
        @socket_close($this->socket);
        @unlink($path);
    }
}
