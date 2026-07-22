<?php

require_once __DIR__ . '/../../resource/js/auth.php';

function whois_api_lookup(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return ['success' => false, 'message' => '查询参数不能为空', 'data' => null];
    }

    $pdo = auth_db();

    $rootDomains = $pdo->query('SELECT id, root_domain FROM root_domains WHERE status = 1')->fetchAll();

    $matchedRoot = null;
    $matchedSub = null;

    foreach ($rootDomains as $rd) {
        $root = $rd['root_domain'];

        if ($query === $root) {
            $matchedRoot = $rd;
            $matchedSub = '@';
            break;
        }

        $dotRoot = '.' . $root;
        if (substr($query, -strlen($dotRoot)) === $dotRoot) {
            $matchedRoot = $rd;
            $matchedSub = substr($query, 0, -strlen($dotRoot));
            break;
        }
    }

    if ($matchedRoot === null || $matchedSub === null) {
        return ['success' => false, 'message' => '未识别的域名，请输入完整域名（如 api.example.com）', 'data' => null];
    }

    $stmt = $pdo->prepare('
        SELECT d.*, r.root_domain,
               u.id AS user_id, u.username, u.nickname,
               u.whois_public, u.whois_name, u.whois_phone, u.whois_email,
               u.whois_company, u.whois_address
        FROM domains d
        INNER JOIN root_domains r ON r.id = d.root_domain_id
        LEFT JOIN users u ON u.id = d.assigned_to
        WHERE r.root_domain = :root AND d.subdomain = :sub
        LIMIT 1
    ');
    $stmt->execute([':root' => $matchedRoot['root_domain'], ':sub' => $matchedSub]);
    $row = $stmt->fetch();

    if (!$row || empty($row['user_id']) || empty($row['whois_public'])) {
        return ['success' => false, 'message' => '未找到该域名的公开 WHOIS 信息', 'data' => null];
    }

    $domainName = $row['root_domain'];
    if ($row['subdomain'] !== '@') {
        $domainName = $row['subdomain'] . '.' . $domainName;
    }

    return [
        'success' => true,
        'message' => 'ok',
        'data' => [
            'domain' => $domainName,
            'root_domain' => $row['root_domain'],
            'subdomain' => $row['subdomain'],
            'owner' => [
                'username' => $row['username'] ?? '',
                'nickname' => $row['nickname'] ?? '',
                'whois_name' => $row['whois_name'] ?? '',
                'whois_phone' => $row['whois_phone'] ?? '',
                'whois_email' => $row['whois_email'] ?? '',
                'whois_company' => $row['whois_company'] ?? '',
                'whois_address' => $row['whois_address'] ?? '',
            ],
        ],
    ];
}

function whois_api_json(bool $success, string $message, array $data = [], int $statusCode = 200): void
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