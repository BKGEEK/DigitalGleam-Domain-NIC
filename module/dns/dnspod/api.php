<?php

require_once __DIR__ . '/../provider.php';

function dnspod_config(): array
{
    return dns_provider_base_config('dnspod');
}

function dnspod_enabled(): bool
{
    return dns_provider_is_enabled('dnspod');
}

function dnspod_secret_id(): string
{
    $config = dnspod_config();
    return (string) ($config['secret_id'] ?? '');
}

function dnspod_secret_key(): string
{
    $config = dnspod_config();
    return (string) ($config['secret_key'] ?? '');
}

function dnspod_api_base(): string
{
    return 'https://dnspod.tencentcloudapi.com';
}

function dnspod_headers(string $action, string $payloadJson): array
{
    $config = dnspod_config();
    $secretId = $config['secret_id'] ?? '';
    $host = 'dnspod.tencentcloudapi.com';
    $timestamp = time();
    $date = gmdate('Y-m-d', $timestamp);
    $service = 'dnspod';
    $algorithm = 'TC3-HMAC-SHA256';

    $hashedRequestPayload = hash('sha256', $payloadJson);
    $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\n";
    $signedHeaders = 'content-type;host';
    $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedRequestPayload}";
    $credentialScope = $date . '/' . $service . '/tc3_request';
    $stringToSign = $algorithm . "\n" . $timestamp . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);

    $secretDate = hash_hmac('sha256', $date, 'TC3' . $config['secret_key'], true);
    $secretService = hash_hmac('sha256', $service, $secretDate, true);
    $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
    $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

    $authorization = sprintf(
        '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
        $algorithm,
        $secretId,
        $credentialScope,
        $signedHeaders,
        $signature
    );

    return [
        'Authorization: ' . $authorization,
        'Content-Type: application/json; charset=utf-8',
        'Host: ' . $host,
        'X-TC-Action: ' . $action,
        'X-TC-Version: 2021-03-23',
        'X-TC-Region: ap-guangzhou',
        'X-TC-Timestamp: ' . $timestamp,
    ];
}

function dnspod_request(string $action, array $payload = []): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = dnspod_headers($action, $body);
    return dns_provider_http_request('POST', dnspod_api_base(), $payload, $headers);
}

function dnspod_json(array $response): array
{
    $body = json_decode($response['body'] ?? '', true);
    return is_array($body) ? $body : [];
}

function dnspod_list_records(string $rootDomain): array
{
    $response = dnspod_request('DescribeRecordList', [
        'Domain' => $rootDomain,
    ]);
    $json = dnspod_json($response);

    return [
        'success' => $response['success'],
        'provider' => 'dnspod',
        'items' => $json['Response']['RecordList'] ?? [],
        'raw' => $json,
    ];
}

function dnspod_create_record(array $payload): array
{
    $response = dnspod_request('CreateRecord', [
        'Domain' => $payload['domain'] ?? '',
        'SubDomain' => $payload['rr'] ?? '',
        'RecordType' => $payload['type'] ?? 'A',
        'RecordLine' => $payload['line'] ?? '默认',
        'Value' => $payload['value'] ?? '',
        'TTL' => $payload['ttl'] ?? 600,
    ]);
    $json = dnspod_json($response);

    return [
        'success' => $response['success'],
        'provider' => 'dnspod',
        'raw' => $json,
    ];
}

function dnspod_update_record(array $payload): array
{
    $response = dnspod_request('ModifyRecord', [
        'Domain' => $payload['domain'] ?? '',
        'RecordId' => $payload['record_id'] ?? '',
        'SubDomain' => $payload['rr'] ?? '',
        'RecordType' => $payload['type'] ?? 'A',
        'RecordLine' => $payload['line'] ?? '默认',
        'Value' => $payload['value'] ?? '',
        'TTL' => $payload['ttl'] ?? 600,
    ]);
    $json = dnspod_json($response);

    return [
        'success' => $response['success'],
        'provider' => 'dnspod',
        'raw' => $json,
    ];
}

function dnspod_delete_record(array $payload): array
{
    $response = dnspod_request('DeleteRecord', [
        'Domain' => $payload['domain'] ?? '',
        'RecordId' => $payload['record_id'] ?? '',
    ]);
    $json = dnspod_json($response);

    return [
        'success' => $response['success'],
        'provider' => 'dnspod',
        'raw' => $json,
    ];
}

function dnspod_sync_root_domain(int $rootDomainId): array
{
    $root = dns_root_domain_by_id($rootDomainId);
    if (!$root) {
        return ['success' => false, 'provider' => 'dnspod', 'message' => 'root domain not found'];
    }

    return dnspod_list_records($root['root_domain']);
}
