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
        'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
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

function mail_test(string $to): array
{
    $config = mail_config();

    if (empty($config) || empty($config['enabled'])) {
        return ['success' => false, 'message' => 'SMTP 未启用'];
    }

    if (empty($config['host']) || empty($config['username'])) {
        return ['success' => false, 'message' => 'SMTP 主机或用户名为空'];
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
        return ['success' => false, 'message' => "连接失败: {$errstr} ({$errno})"];
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

    $expect = function (string $prefix) use ($read): string {
        $response = $read();
        if (!str_starts_with($response, $prefix)) {
            return $response;
        }
        return '';
    };

    $response = $expect('220');
    if ($response) {
        fclose($stream);
        return ['success' => false, 'message' => "SMTP 握手失败: " . trim($response)];
    }

    $write('EHLO localhost');
    $response = $expect('250');
    if ($response) {
        fclose($stream);
        return ['success' => false, 'message' => "EHLO 失败: " . trim($response)];
    }

    if ($encryption === 'tls') {
        $write('STARTTLS');
        $response = $expect('220');
        if ($response) {
            fclose($stream);
            return ['success' => false, 'message' => "STARTTLS 失败: " . trim($response)];
        }
        stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $write('EHLO localhost');
        $response = $expect('250');
        if ($response) {
            fclose($stream);
            return ['success' => false, 'message' => "TLS EHLO 失败: " . trim($response)];
        }
    }

    if ($username !== '') {
        $write('AUTH LOGIN');
        $response = $expect('334');
        if ($response) {
            fclose($stream);
            return ['success' => false, 'message' => "AUTH LOGIN 初始化失败: " . trim($response)];
        }
        $write(base64_encode($username));
        $response = $expect('334');
        if ($response) {
            fclose($stream);
            return ['success' => false, 'message' => "AUTH LOGIN 用户名失败: " . trim($response)];
        }
        $write(base64_encode($password));
        $response = $expect('235');
        if ($response) {
            fclose($stream);
            return ['success' => false, 'message' => "AUTH LOGIN 认证失败: " . trim($response)];
        }
    }

    $write('MAIL FROM:<' . $fromEmail . '>');
    $response = $expect('250');
    if ($response) {
        fclose($stream);
        return ['success' => false, 'message' => "发件人地址被拒: " . trim($response)];
    }

    $write('RCPT TO:<' . $to . '>');
    $response = $expect('250');
    if ($response) {
        $response = $expect('251');
        if ($response) {
            fclose($stream);
            return ['success' => false, 'message' => "收件人地址被拒: " . trim($response)];
        }
    }

    $write('DATA');
    $response = $expect('354');
    if ($response) {
        fclose($stream);
        return ['success' => false, 'message' => "DATA 命令失败: " . trim($response)];
    }

    $subject = '=?UTF-8?B?' . base64_encode('SMTP 测试邮件') . '?=';
    $html = '<div style="font-family: Arial, sans-serif; padding: 24px; color: #1f2937;">
        <h2 style="margin: 0 0 16px;">SMTP 测试邮件</h2>
        <p style="margin: 0 0 12px;">如果你收到此邮件，说明 SMTP 配置正确。</p>
        <p style="margin: 0; color: #6b7280; font-size: 13px;">发送时间: ' . date('Y-m-d H:i:s') . '</p>
    </div>';

    $headers = [
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . " <{$fromEmail}>",
        'To: <' . $to . '>',
        'Reply-To: ' . $replyTo,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
    ];

    $body = implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n.";
    $write($body);
    $response = $expect('250');
    if ($response) {
        fclose($stream);
        return ['success' => false, 'message' => "邮件发送被拒: " . trim($response)];
    }

    $write('QUIT');
    fclose($stream);

    return ['success' => true, 'message' => '测试邮件发送成功！'];
}
