<?php

require_once __DIR__ . '/../provider.php';

function cloudflare_config(): array
{
    return dns_provider_base_config('cloudflare');
}

function cloudflare_enabled(): bool
{
    return dns_provider_is_enabled('cloudflare');
}

function cloudflare_api_base(): string
{
    return 'https://api.cloudflare.com/client/v4';
}

function cloudflare_headers(): array
{
    $config = cloudflare_config();
    $headers = ['Accept: application/json'];
    if (!empty($config['api_token'])) {
        $headers[] = 'Authorization: Bearer ' . $config['api_token'];
    }

    return $headers;
}

function cloudflare_request(string $method, string $path, array $payload = []): array
{
    $url = cloudflare_api_base() . $path;
    return dns_provider_http_request($method, $url, $payload, cloudflare_headers());
}

function cloudflare_json(array $response): array
{
    $body = json_decode($response['body'] ?? '', true);
    return is_array($body) ? $body : [];
}

function cloudflare_find_zone_id(string $rootDomain): ?string
{
    $response = cloudflare_request('GET', '/zones', ['name' => $rootDomain]);
    if (!$response['success']) {
        return null;
    }

    $json = cloudflare_json($response);
    return $json['result'][0]['id'] ?? null;
}

function cloudflare_list_records(string $rootDomain): array
{
    $zoneId = cloudflare_find_zone_id($rootDomain);
    if (!$zoneId) {
        return dns_provider_stub_list('cloudflare');
    }

    $response = cloudflare_request('GET', "/zones/{$zoneId}/dns_records");
    $json = cloudflare_json($response);

    return [
        'success' => (bool) ($json['success'] ?? false),
        'provider' => 'cloudflare',
        'items' => $json['result'] ?? [],
        'raw' => $json,
    ];
}

function cloudflare_create_record(array $payload): array
{
    $zoneId = $payload['zone_id'] ?? null;
    if (!$zoneId && !empty($payload['domain'])) {
        $zoneId = cloudflare_find_zone_id((string) $payload['domain']);
    }
    if (!$zoneId) {
        return ['success' => false, 'provider' => 'cloudflare', 'message' => 'zone not found'];
    }

    $body = [
        'type' => $payload['type'] ?? 'A',
        'name' => $payload['name'] ?? '',
        'content' => $payload['value'] ?? '',
        'ttl' => $payload['ttl'] ?? 1,
        'proxied' => (bool) ($payload['proxied'] ?? false),
    ];

    $response = cloudflare_request('POST', "/zones/{$zoneId}/dns_records", $body);
    $json = cloudflare_json($response);

    return [
        'success' => (bool) ($json['success'] ?? false),
        'provider' => 'cloudflare',
        'raw' => $json,
    ];
}

function cloudflare_update_record(array $payload): array
{
    $zoneId = $payload['zone_id'] ?? null;
    $recordId = $payload['record_id'] ?? null;
    if (!$zoneId || !$recordId) {
        return ['success' => false, 'provider' => 'cloudflare', 'message' => 'zone_id or record_id missing'];
    }

    $body = [
        'type' => $payload['type'] ?? 'A',
        'name' => $payload['name'] ?? '',
        'content' => $payload['value'] ?? '',
        'ttl' => $payload['ttl'] ?? 1,
        'proxied' => (bool) ($payload['proxied'] ?? false),
    ];

    $response = cloudflare_request('PUT', "/zones/{$zoneId}/dns_records/{$recordId}", $body);
    $json = cloudflare_json($response);

    return [
        'success' => (bool) ($json['success'] ?? false),
        'provider' => 'cloudflare',
        'raw' => $json,
    ];
}

function cloudflare_delete_record(array $payload): array
{
    $zoneId = $payload['zone_id'] ?? null;
    $recordId = $payload['record_id'] ?? null;
    if (!$zoneId || !$recordId) {
        return ['success' => false, 'provider' => 'cloudflare', 'message' => 'zone_id or record_id missing'];
    }

    $response = cloudflare_request('DELETE', "/zones/{$zoneId}/dns_records/{$recordId}");
    $json = cloudflare_json($response);

    return [
        'success' => (bool) ($json['success'] ?? false),
        'provider' => 'cloudflare',
        'raw' => $json,
    ];
}

function cloudflare_sync_root_domain(int $rootDomainId): array
{
    $root = dns_root_domain_by_id($rootDomainId);
    if (!$root) {
        return ['success' => false, 'provider' => 'cloudflare', 'message' => 'root domain not found'];
    }

    return cloudflare_list_records($root['root_domain']);
}
