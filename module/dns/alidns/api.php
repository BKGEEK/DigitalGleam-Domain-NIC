<?php

require_once __DIR__ . '/../provider.php';

function alidns_config(): array
{
    return dns_provider_base_config('alidns');
}

function alidns_enabled(): bool
{
    return dns_provider_is_enabled('alidns');
}

function alidns_endpoint(): string
{
    $config = alidns_config();
    return $config['endpoint'] ?? 'alidns.cn-hangzhou.aliyuncs.com';
}

function alidns_common_params(string $action, array $extra = []): array
{
    $config = alidns_config();

    return array_merge([
        'Action' => $action,
        'Format' => 'JSON',
        'Version' => '2015-01-09',
        'AccessKeyId' => $config['access_key_id'] ?? '',
        'SignatureMethod' => 'HMAC-SHA1',
        'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'SignatureVersion' => '1.0',
        'SignatureNonce' => bin2hex(random_bytes(16)),
    ], $extra);
}

function alidns_sign(array $params, string $method = 'GET'): string
{
    $config = alidns_config();
    $secret = $config['access_key_secret'] ?? '';

    ksort($params);

    $canonical = [];
    foreach ($params as $key => $value) {
        $canonical[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }

    $stringToSign = strtoupper($method) . '&' . rawurlencode('/') . '&' . rawurlencode(implode('&', $canonical));
    return base64_encode(hash_hmac('sha1', $stringToSign, $secret . '&', true));
}

function alidns_request(string $action, array $params = [], string $method = 'GET'): array
{
    $query = alidns_common_params($action, $params);
    $query['Signature'] = alidns_sign($query, $method);
    $url = 'https://' . alidns_endpoint() . '/?' . http_build_query($query);

    return dns_provider_http_request($method, $url, []);
}

function alidns_extract_records(array $response): array
{
    $body = json_decode($response['body'] ?? '', true);
    if (!is_array($body)) {
        return [];
    }

    return $body['DomainRecords']['Record'] ?? [];
}

function alidns_list_records(string $rootDomain): array
{
    $response = alidns_request('DescribeDomainRecords', [
        'DomainName' => $rootDomain,
    ]);

    if (!$response['success']) {
        return $response;
    }

    return [
        'success' => true,
        'provider' => 'alidns',
        'items' => alidns_extract_records($response),
        'raw' => $response,
    ];
}

function alidns_create_record(array $payload): array
{
    $response = alidns_request('AddDomainRecord', [
        'DomainName' => $payload['domain'] ?? '',
        'RR' => $payload['rr'] ?? '',
        'Type' => $payload['type'] ?? 'A',
        'Value' => $payload['value'] ?? '',
        'TTL' => $payload['ttl'] ?? 600,
    ]);

    return $response['success']
        ? ['success' => true, 'provider' => 'alidns', 'raw' => $response]
        : ['success' => false, 'provider' => 'alidns', 'raw' => $response];
}

function alidns_update_record(array $payload): array
{
    $response = alidns_request('UpdateDomainRecord', [
        'RecordId' => $payload['record_id'] ?? '',
        'RR' => $payload['rr'] ?? '',
        'Type' => $payload['type'] ?? 'A',
        'Value' => $payload['value'] ?? '',
        'TTL' => $payload['ttl'] ?? 600,
    ]);

    return $response['success']
        ? ['success' => true, 'provider' => 'alidns', 'raw' => $response]
        : ['success' => false, 'provider' => 'alidns', 'raw' => $response];
}

function alidns_delete_record(array $payload): array
{
    $response = alidns_request('DeleteDomainRecord', [
        'RecordId' => $payload['record_id'] ?? '',
    ]);

    return $response['success']
        ? ['success' => true, 'provider' => 'alidns', 'raw' => $response]
        : ['success' => false, 'provider' => 'alidns', 'raw' => $response];
}

function alidns_sync_root_domain(int $rootDomainId): array
{
    $root = dns_root_domain_by_id($rootDomainId);
    if (!$root) {
        return ['success' => false, 'provider' => 'alidns', 'message' => 'root domain not found'];
    }

    return alidns_list_records($root['root_domain']);
}
