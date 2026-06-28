<?php

require_once __DIR__ . '/service.php';
require_once __DIR__ . '/../../resource/js/auth.php';

function dns_api_boot_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function dns_api_is_admin(): bool
{
    dns_api_boot_session();
    return !empty($_SESSION['admin_id']);
}

function dns_api_require_admin(): void
{
    if (!dns_api_is_admin()) {
        dns_api_json(false, '未授权访问', [], 401);
    }
}

function dns_api_json(bool $success, string $message, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dns_api_input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function dns_api_root_domain_list(): array
{
    return dns_root_domains(false);
}

function dns_api_root_domain_create(string $rootDomain, string $provider = 'manual', string $remark = ''): int
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('INSERT INTO root_domains (root_domain, provider, status, remark) VALUES (:root_domain, :provider, 1, :remark)');
    $stmt->execute([
        ':root_domain' => trim($rootDomain),
        ':provider' => trim($provider) ?: 'manual',
        ':remark' => trim($remark),
    ]);

    return (int) $pdo->lastInsertId();
}

function dns_api_root_domain_update(int $id, array $data): bool
{
    $fields = [];
    $params = [':id' => $id];

    foreach (['root_domain', 'provider', 'status', 'remark'] as $key) {
        if (array_key_exists($key, $data)) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $data[$key];
        }
    }

    if (!$fields) {
        return false;
    }

    $pdo = dns_db();
    $sql = 'UPDATE root_domains SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
    $stmt = $pdo->prepare($sql);

    return $stmt->execute($params);
}

function dns_api_root_domain_delete(int $id): bool
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('DELETE FROM root_domains WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

function dns_api_domain_list(?int $rootDomainId = null): array
{
    if ($rootDomainId) {
        return dns_domains_by_root_id($rootDomainId, false);
    }

    return dns_domains_all(false);
}

function dns_api_domain_create(int $rootDomainId, string $subdomain, int $status = 1, ?int $assignedTo = null, string $remark = ''): int
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('INSERT INTO domains (root_domain_id, subdomain, status, assigned_to, remark) VALUES (:root_domain_id, :subdomain, :status, :assigned_to, :remark)');
    $stmt->execute([
        ':root_domain_id' => $rootDomainId,
        ':subdomain' => trim($subdomain),
        ':status' => $status,
        ':assigned_to' => $assignedTo,
        ':remark' => trim($remark),
    ]);

    return (int) $pdo->lastInsertId();
}

function dns_api_domain_update(int $id, array $data): bool
{
    $fields = [];
    $params = [':id' => $id];

    foreach (['root_domain_id', 'subdomain', 'status', 'assigned_to', 'remark'] as $key) {
        if (array_key_exists($key, $data)) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $data[$key];
        }
    }

    if (!$fields) {
        return false;
    }

    $pdo = dns_db();
    $sql = 'UPDATE domains SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
    $stmt = $pdo->prepare($sql);

    return $stmt->execute($params);
}

function dns_api_domain_delete(int $id): bool
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('DELETE FROM domains WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

function dns_api_domain_toggle_status(int $id, int $status): bool
{
    return dns_api_domain_update($id, ['status' => $status]);
}

function dns_api_domain_touch_assignment(int $id, ?int $userId): bool
{
    return dns_api_domain_update($id, ['assigned_to' => $userId]);
}
