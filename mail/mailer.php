<?php

function mail_config(): array
{
    $config = require __DIR__ . '/../config/config.php';
    return $config['smtp'] ?? [];
}

function mail_send(string $to, string $subject, string $html, ?string $text = null): bool
{
    $config = mail_config();

    if (empty($config) || empty($config['enabled'])) {
        return false;
    }

    $host = $config['host'] ?? '';
    $port = (int) ($config['port'] ?? 465);
    $encryption = $config['encryption'] ?? 'ssl';
    $username = $config['username'] ?? '';
    $password = $config['password'] ?? '';
    $fromEmail = $config['from_email'] ?? $username;
    $fromName = $config['from_name'] ?? 'System';
    $replyTo = $config['reply_to'] ?? $fromEmail;

    $socketHost = $encryption === 'ssl' ? "ssl://{$host}" : $host;
    $stream = @stream_socket_client("{$socketHost}:{$port}", $errno, $errstr, 15);
    if (!$stream) {
        return false;
    }

    $read = function () use ($stream): string {
        $data = '';
        while ($line = fgets($stream, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function (string $cmd) use ($stream): void {
        fwrite($stream, $cmd . "\r\n");
    };

    $expect = function (string $prefix) use ($read): bool {
        $response = $read();
        return str_starts_with($response, $prefix);
    };

    if (!$expect('220')) {
        fclose($stream);
        return false;
    }

    $write('EHLO localhost');
    if (!$expect('250')) {
        fclose($stream);
        return false;
    }

    if ($encryption === 'tls') {
        $write('STARTTLS');
        if (!$expect('220')) {
            fclose($stream);
            return false;
        }
        stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $write('EHLO localhost');
        if (!$expect('250')) {
            fclose($stream);
            return false;
        }
    }

    if ($username !== '') {
        $write('AUTH LOGIN');
        if (!$expect('334')) {
            fclose($stream);
            return false;
        }
        $write(base64_encode($username));
        if (!$expect('334')) {
            fclose($stream);
            return false;
        }
        $write(base64_encode($password));
        if (!$expect('235')) {
            fclose($stream);
            return false;
        }
    }

    $write('MAIL FROM:<' . $fromEmail . '>');
    if (!$expect('250')) {
        fclose($stream);
        return false;
    }

    $write('RCPT TO:<' . $to . '>');
    if (!$expect('250') && !$expect('251')) {
        fclose($stream);
        return false;
    }

    $write('DATA');
    if (!$expect('354')) {
        fclose($stream);
        return false;
    }

    $headers = [
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . " <{$fromEmail}>",
        'To: <' . $to . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
    ];

    $body = implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n.";
    $write($body);
    if (!$expect('250')) {
        fclose($stream);
        return false;
    }

    $write('QUIT');
    fclose($stream);

    return true;
}
