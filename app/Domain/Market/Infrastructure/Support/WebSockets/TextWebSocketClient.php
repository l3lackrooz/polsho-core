<?php

namespace App\Domain\Market\Infrastructure\Support\WebSockets;

use RuntimeException;

class TextWebSocketClient
{
    /** @var resource|null */
    private $socket = null;

    public function connect(string $url, array $headers = []): void
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Invalid websocket URL.');
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'wss' ? 443 : 80);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        $transport = $scheme === 'wss' ? 'ssl' : 'tcp';

        $socket = stream_socket_client(
            sprintf('%s://%s:%d', $transport, $host, $port),
            $errorCode,
            $errorMessage,
            10,
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            throw new RuntimeException("Websocket connection failed: {$errorMessage}", $errorCode);
        }

        stream_set_timeout($socket, 5);

        $key = base64_encode(random_bytes(16));
        $headerLines = [
            "GET {$path} HTTP/1.1",
            "Host: {$host}:{$port}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: '.$key,
            'Sec-WebSocket-Version: 13',
        ];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        fwrite($socket, implode("\r\n", $headerLines)."\r\n\r\n");

        $response = '';
        while (!str_contains($response, "\r\n\r\n")) {
            $chunk = fgets($socket);
            if ($chunk === false) {
                break;
            }
            $response .= $chunk;
        }

        if (!str_contains($response, ' 101 ')) {
            throw new RuntimeException('Websocket handshake failed: '.$response);
        }

        $this->socket = $socket;
    }

    public function sendText(string $payload): void
    {
        $this->writeFrame(0x1, $payload);
    }

    public function listen(callable $onMessage): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Websocket connection is not open.');
        }

        while (!feof($this->socket)) {
            $frame = $this->readFrame();

            if ($frame === null) {
                continue;
            }

            if ($frame['opcode'] === 0x8) {
                $this->close();
                return;
            }

            if ($frame['opcode'] === 0x9) {
                $this->writeFrame(0xA, $frame['payload']);
                continue;
            }

            if ($frame['opcode'] === 0x1) {
                $onMessage($frame['payload']);
            }
        }
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    /**
     * @return array{opcode:int,payload:string}|null
     */
    private function readFrame(): ?array
    {
        $header = fread($this->socket, 2);
        if ($header === false || strlen($header) < 2) {
            return null;
        }

        $first = ord($header[0]);
        $second = ord($header[1]);
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) === 0x80;
        $length = $second & 0x7F;

        if ($length === 126) {
            $extended = fread($this->socket, 2);
            $length = unpack('n', $extended)[1];
        } elseif ($length === 127) {
            $extended = fread($this->socket, 8);
            $parts = unpack('N2', $extended);
            $length = ($parts[1] << 32) | $parts[2];
        }

        $mask = $masked ? fread($this->socket, 4) : '';
        $payload = '';

        while (strlen($payload) < $length) {
            $chunk = fread($this->socket, $length - strlen($payload));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
        }

        if ($masked) {
            $payload = $this->applyMask($payload, $mask);
        }

        return [
            'opcode' => $opcode,
            'payload' => $payload,
        ];
    }

    private function writeFrame(int $opcode, string $payload): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Websocket connection is not open.');
        }

        $length = strlen($payload);
        $header = chr(0x80 | $opcode);

        if ($length < 126) {
            $header .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $header .= chr(0x80 | 126).pack('n', $length);
        } else {
            $header .= chr(0x80 | 127).pack('NN', 0, $length);
        }

        $mask = random_bytes(4);
        fwrite($this->socket, $header.$mask.$this->applyMask($payload, $mask));
    }

    private function applyMask(string $payload, string $mask): string
    {
        $output = '';
        $length = strlen($payload);

        for ($index = 0; $index < $length; $index++) {
            $output .= $payload[$index] ^ $mask[$index % 4];
        }

        return $output;
    }
}
