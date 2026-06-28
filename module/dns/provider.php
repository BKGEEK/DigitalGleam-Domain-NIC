<?php

require_once __DIR__ . '/service.php';

function dns_provider_base_config(string $provider): array
{
    return dns_provider_config($provider);
}

function dns_provider_is_enabled(string $provider): bool
{
    $config = dns_provider_base_config($provider);
    return !empty($config['enabled']);
}

function dns_provider_stub_result(string $provider, string $action): array
{
    return [
        'success' => false,
        'provider' => $provider,
        'action' => $action,
        'message' => 'provider not implemented yet',
    ];
}

function dns_provider_stub_list(string $provider): array
{
    return [
        'success' => false,
        'provider' => $provider,
        'items' => [],
        'message' => 'provider not implemented yet',
    ];
}

function dns_provider_http_request(string $method, string $url, array $payload = [], array $headers = []): array
{
    $method = strtoupper($method);
    $body = '';
    $hasContentType = false;

    foreach ($headers as $header) {
        if (stripos($header, 'content-type:') === 0) {
            $hasContentType = true;
            break;
        }
    }

    if ($method !== 'GET' && !empty($payload)) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$hasContentType) {
            $headers[] = 'Content-Type: application/json';
        }
    } elseif ($method === 'GET' && !empty($payload)) {
        $query = http_build_query($payload);
        $url .= (str_contains($url, '?') ? '&' : '?') . $query;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'status' => 0,
            'headers' => [],
            'body' => null,
            'error' => $error ?: 'request failed',
        ];
    }

    return [
        'success' => $status >= 200 && $status < 300,
        'status' => $status,
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
        'error' => null,
    ];
}
