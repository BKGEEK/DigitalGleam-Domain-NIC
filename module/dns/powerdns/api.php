<?php

require_once __DIR__ . '/../provider.php';

function powerdns_config(): array
{
    return dns_provider_base_config('powerdns');
}

function powerdns_enabled(): bool
{
    return dns_provider_is_enabled('powerdns');
}

function powerdns_api_base(): string
{
    $config = powerdns_config();
    return rtrim((string) ($config['server_url'] ?? ''), '/');
}

function powerdns_headers(): array
{
    $config = powerdns_config();
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if (!empty($config['api_key'])) {
        $headers[] = 'X-API-Key: ' . $config['api_key'];
    }

    return $headers;
}

function powerdns_request(string $method, string $path, array $payload = []): array
{
    return dns_provider_http_request($method, powerdns_api_base() . $path, $payload, powerdns_headers());
}

function powerdns_json(array $response): array
{
    $body = json_decode($response['body'] ?? '', true);
    return is_array($body) ? $body : [];
}

function powerdns_zone_name(string $rootDomain): string
{
    return rtrim($rootDomain, '.') . '.';
}

function powerdns_list_records(string $rootDomain): array
{
    $zone = powerdns_zone_name($rootDomain);
    $response = powerdns_request('GET', '/api/v1/servers/localhost/zones/' . rawurlencode($zone));
    $json = powerdns_json($response);

    return [
        'success' => $response['success'],
        'provider' => 'powerdns',
        'items' => $json['rrsets'] ?? [],
        'raw' => $json,
    ];
}

function powerdns_create_record(array $payload): array
{
    $zone = powerdns_zone_name((string) ($payload['domain'] ?? ''));
    if ($zone === '.') {
        return ['success' => false, 'provider' => 'powerdns', 'message' => 'domain missing'];
    }

    $rrset = [
        'name' => rtrim((string) ($payload['name'] ?? ''), '.') . '.',
        'type' => $payload['type'] ?? 'A',
        'ttl' => $payload['ttl'] ?? 3600,
        'changetype' => 'REPLACE',
        'records' => [
            [
                'content' => $payload['value'] ?? '',
                'disabled' => false,
            ],
        ],
    ];

    $response = powerdns_request('PATCH', '/api/v1/servers/localhost/zones/' . rawurlencode($zone), [
        'rrsets' => [$rrset],
    ]);
    $json = powerdns_json($response);

    return [
        'success' => $response['success'],
        'provider' => 'powerdns',
        'raw' => $json,
    ];
}

function powerdns_update_record(array $payload): array
{
    return powerdns_create_record($payload);
}

function powerdns_delete_record(array $payload): array
{
    $zone = powerdns_zone_name((string) ($payload['domain'] ?? ''));
    if ($zone === '.') {
        return ['success' => false, 'provider' => 'powerdns', 'message' => 'domain missing'];
    }

    $rrset = [
        'name' => rtrim((string) ($payload['name'] ?? ''), '.') . '.',
        'type' => $payload['type'] ?? 'A',
        'changetype' => 'DELETE',
    ];

    $response = powerdns_request('PATCH', '/api/v1/servers/localhost/zones/' . rawurlencode($zone), [
        'rrsets' => [$rrset],
    ]);
    $json = powerdns_json($response);

    return [
        'success' => $response['success'],
        'provider' => 'powerdns',
        'raw' => $json,
    ];
}

function powerdns_sync_root_domain(int $rootDomainId): array
{
    $root = dns_root_domain_by_id($rootDomainId);
    if (!$root) {
        return ['success' => false, 'provider' => 'powerdns', 'message' => 'root domain not found'];
    }

    return powerdns_list_records($root['root_domain']);
}
