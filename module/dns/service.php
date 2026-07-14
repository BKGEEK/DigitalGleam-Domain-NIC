<?php

function dns_config(): array
{
    $config = require __DIR__ . '/../../config/config.php';
    return $config;
}

function dns_db(): PDO
{
    if (!file_exists(__DIR__ . '/../../install/install.lock')) {
        header('Location: /install/install.php');
        exit;
    }
    return require __DIR__ . '/../../config/sql/connection.php';
}

function dns_provider_map(): array
{
    return [
        'manual' => '手动管理',
        'alidns' => '阿里云 DNS',
        'cloudflare' => 'Cloudflare',
        'dnspod' => 'DNSPod',
        'powerdns' => 'PowerDNS',
    ];
}

function dns_provider_label(string $provider): string
{
    $map = dns_provider_map();
    return $map[$provider] ?? ucfirst($provider);
}

function dns_full_domain(string $rootDomain, string $subdomain): string
{
    $subdomain = trim($subdomain);
    $rootDomain = trim($rootDomain);

    if ($subdomain === '' || $subdomain === '@') {
        return $rootDomain;
    }

    return $subdomain . '.' . $rootDomain;
}

function dns_root_domains(bool $onlyActive = true): array
{
    $pdo = dns_db();
    $sql = 'SELECT * FROM root_domains';
    if ($onlyActive) {
        $sql .= ' WHERE status = 1';
    }
    $sql .= ' ORDER BY id DESC';

    return $pdo->query($sql)->fetchAll();
}

function dns_root_domain_by_id(int $id): ?array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT * FROM root_domains WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function dns_domains_by_root_id(int $rootDomainId, bool $onlyActive = false): array
{
    $pdo = dns_db();
    $sql = 'SELECT d.*, r.root_domain, r.provider AS root_provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.root_domain_id = :root_domain_id';
    if ($onlyActive) {
        $sql .= ' AND d.status = 1 AND r.status = 1';
    }
    $sql .= ' ORDER BY d.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':root_domain_id' => $rootDomainId]);

    return $stmt->fetchAll();
}

function dns_domains_all(bool $onlyActive = false): array
{
    $pdo = dns_db();
    $sql = 'SELECT d.*, r.root_domain, r.provider AS root_provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id';
    if ($onlyActive) {
        $sql .= ' WHERE d.status = 1 AND r.status = 1';
    }
    $sql .= ' ORDER BY d.id DESC';

    return $pdo->query($sql)->fetchAll();
}

function dns_domain_display_name(array $row): string
{
    $root = $row['root_domain'] ?? '';
    $sub = $row['subdomain'] ?? '';
    return dns_full_domain($root, $sub);
}

function dns_root_domain_summary(array $rootDomain): array
{
    $domains = dns_domains_by_root_id((int) $rootDomain['id']);
    $free = 0;
    $used = 0;
    $review = 0;

    foreach ($domains as $domain) {
        $status = (int) $domain['status'];
        if ($status === 1) {
            $free++;
        } elseif ($status === 2) {
            $used++;
        } elseif ($status === 3) {
            $review++;
        }
    }

    return [
        'total' => count($domains),
        'free' => $free,
        'used' => $used,
        'review' => $review,
    ];
}

function dns_provider_config(string $provider): array
{
    $settings = dns_config();
    return $settings['dns'][$provider] ?? [];
}

function dns_domain_record_config(): array
{
    $config = dns_config();
    return $config['domain'] ?? [];
}

function dns_ns_records_by_domain_id(int $domainId): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT * FROM ns_records WHERE domain_id = :domain_id ORDER BY sort_order ASC');
    $stmt->execute([':domain_id' => $domainId]);
    return $stmt->fetchAll();
}

function dns_ns_record_add(int $domainId, string $nameserver): array
{
    $nameserver = trim($nameserver);
    if ($nameserver === '') {
        return ['success' => false, 'message' => 'NS 记录值不能为空'];
    }

    if (empty(dns_domain_record_config()['enable_ns_records'])) {
        return ['success' => false, 'message' => 'NS 记录类型已禁用'];
    }

    $pdo = dns_db();

    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM ns_records WHERE domain_id = :domain_id');
    $stmt->execute([':domain_id' => $domainId]);
    $count = (int) $stmt->fetch()['cnt'];

    $maxNs = (int) (dns_domain_record_config()['max_ns_records'] ?? 5);
    if ($maxNs > 0 && $count >= $maxNs) {
        return ['success' => false, 'message' => "最多只能添加 {$maxNs} 条 NS 记录"];
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM ns_records WHERE domain_id = :domain_id AND nameserver = :nameserver');
    $stmt->execute([':domain_id' => $domainId, ':nameserver' => $nameserver]);
    if ((int) $stmt->fetch()['cnt'] > 0) {
        return ['success' => false, 'message' => '该 NS 记录已存在'];
    }

    $stmt = $pdo->prepare('INSERT INTO ns_records (domain_id, nameserver, sort_order) VALUES (:domain_id, :nameserver, :sort_order)');
    $success = $stmt->execute([
        ':domain_id' => $domainId,
        ':nameserver' => $nameserver,
        ':sort_order' => $count,
    ]);

    if (!$success) {
        return ['success' => false, 'message' => '添加失败，请重试'];
    }

    $recordId = (int) $pdo->lastInsertId();

    $provider = dns_domain_provider($domainId);
    if ($provider === 'powerdns') {
        $syncResult = dns_ns_sync_domain_to_provider($domainId);
    } else {
        $syncResult = dns_ns_sync_to_provider($domainId, $nameserver);
        if ($syncResult['success'] && !empty($syncResult['provider_record_id'])) {
            $stmt = $pdo->prepare('UPDATE ns_records SET provider_record_id = :provider_record_id WHERE id = :id');
            $stmt->execute([':provider_record_id' => $syncResult['provider_record_id'], ':id' => $recordId]);
        }
    }

    return ['success' => true, 'message' => '添加成功', 'id' => $recordId, 'sync' => $syncResult];
}

function dns_ns_record_delete(int $id): array
{
    $pdo = dns_db();

    $stmt = $pdo->prepare('SELECT nr.*, d.id AS domain_id FROM ns_records nr WHERE nr.id = :id');
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch();

    if (!$record) {
        return ['success' => false, 'message' => '记录不存在'];
    }

    $domainId = (int) $record['domain_id'];
    $provider = dns_domain_provider($domainId);

    $stmt = $pdo->prepare('DELETE FROM ns_records WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($provider === 'powerdns') {
        $remaining = dns_ns_records_by_domain_id($domainId);
        if (empty($remaining)) {
            $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
            $stmt->execute([':id' => $domainId]);
            $domain = $stmt->fetch();
            if ($domain) {
                $fullDomain = dns_full_domain($domain['root_domain'], $domain['subdomain']);
                require_once __DIR__ . '/powerdns/api.php';
                powerdns_delete_record([
                    'domain' => $domain['root_domain'],
                    'name' => $fullDomain,
                    'type' => 'NS',
                ]);
            }
        } else {
            dns_ns_sync_domain_to_provider($domainId);
        }
    } elseif (!empty($record['provider_record_id'])) {
        dns_ns_delete_from_provider($domainId, $record['nameserver'], $record['provider_record_id']);
    }

    return ['success' => true, 'message' => '删除成功'];
}

function dns_domain_provider(int $domainId): string
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
    $stmt->execute([':id' => $domainId]);
    $row = $stmt->fetch();
    return $row['provider'] ?? 'manual';
}

function dns_ns_sync_domain_to_provider(int $domainId): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
    $stmt->execute([':id' => $domainId]);
    $domain = $stmt->fetch();

    if (!$domain) {
        return ['success' => false, 'message' => '域名不存在'];
    }

    $records = dns_ns_records_by_domain_id($domainId);
    if (empty($records)) {
        return ['success' => false, 'message' => '暂无 NS 记录可同步'];
    }

    $provider = $domain['provider'];
    $subdomain = $domain['subdomain'];
    $rootDomain = $domain['root_domain'];
    $fullDomain = dns_full_domain($rootDomain, $subdomain);

    if ($provider === 'powerdns') {
        require_once __DIR__ . '/powerdns/api.php';
        if (!powerdns_enabled()) {
            return ['success' => false, 'message' => 'PowerDNS 未启用'];
        }
        $zone = powerdns_zone_name($rootDomain);
        $powerdnsResponse = powerdns_request('GET', '/api/v1/servers/localhost/zones/' . rawurlencode($zone));
        $zoneData = powerdns_json($powerdnsResponse);

        $existingRrsets = $zoneData['rrsets'] ?? [];
        $otherRrsets = [];
        $nsName = rtrim($fullDomain, '.') . '.';
        foreach ($existingRrsets as $rrset) {
            if (!($rrset['name'] === $nsName && $rrset['type'] === 'NS')) {
                $otherRrsets[] = $rrset;
            }
        }

        $recordsData = [];
        foreach ($records as $rec) {
            $recordsData[] = [
                'content' => $rec['nameserver'],
                'disabled' => false,
            ];
        }

        $nsRrset = [
            'name' => $nsName,
            'type' => 'NS',
            'ttl' => 3600,
            'changetype' => 'REPLACE',
            'records' => $recordsData,
        ];

        $allRrsets = $otherRrsets;
        $allRrsets[] = $nsRrset;

        $response = powerdns_request('PATCH', '/api/v1/servers/localhost/zones/' . rawurlencode($zone), [
            'rrsets' => $allRrsets,
        ]);

        if ($response['success']) {
            return ['success' => true, 'provider_record_id' => '', 'records_count' => count($records)];
        }
        return ['success' => false, 'message' => 'PowerDNS 同步失败'];
    }

    return ['success' => false, 'message' => "服务商 {$provider} 暂不支持批量同步"];
}

function dns_ns_sync_to_provider(int $domainId, string $nameserver): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
    $stmt->execute([':id' => $domainId]);
    $domain = $stmt->fetch();

    if (!$domain) {
        return ['success' => false, 'message' => '域名不存在'];
    }

    $provider = $domain['provider'];
    $subdomain = $domain['subdomain'];
    $rootDomain = $domain['root_domain'];
    $fullDomain = dns_full_domain($rootDomain, $subdomain);

    if ($provider === 'cloudflare') {
        require_once __DIR__ . '/cloudflare/api.php';
        if (!cloudflare_enabled()) {
            return ['success' => false, 'message' => 'Cloudflare 未启用'];
        }
        $result = cloudflare_create_record([
            'type' => 'NS',
            'name' => $fullDomain,
            'value' => $nameserver,
            'domain' => $rootDomain,
            'ttl' => 1,
        ]);
        if ($result['success'] && !empty($result['raw']['result']['id'])) {
            return ['success' => true, 'provider_record_id' => $result['raw']['result']['id']];
        }
        $errorMsg = $result['raw']['errors'][0]['message'] ?? 'Cloudflare 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'alidns') {
        require_once __DIR__ . '/alidns/api.php';
        if (!alidns_enabled()) {
            return ['success' => false, 'message' => '阿里云 DNS 未启用'];
        }
        $result = alidns_create_record([
            'domain' => $rootDomain,
            'rr' => $subdomain,
            'type' => 'NS',
            'value' => $nameserver,
            'ttl' => 600,
        ]);
        if ($result['success']) {
            $body = json_decode($result['raw']['body'] ?? '', true);
            $recordId = $body['RecordId'] ?? '';
            return ['success' => true, 'provider_record_id' => $recordId];
        }
        $body = json_decode($result['raw']['body'] ?? '', true);
        $errorMsg = $body['Message'] ?? '阿里云 DNS 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'dnspod') {
        require_once __DIR__ . '/dnspod/api.php';
        if (!dnspod_enabled()) {
            return ['success' => false, 'message' => 'DNSPod 未启用'];
        }
        $result = dnspod_create_record([
            'domain' => $rootDomain,
            'rr' => $subdomain,
            'type' => 'NS',
            'value' => $nameserver,
            'ttl' => 600,
        ]);
        if ($result['success'] && !empty($result['raw']['Response']['RecordId'])) {
            return ['success' => true, 'provider_record_id' => (string) $result['raw']['Response']['RecordId']];
        }
        $errorMsg = $result['raw']['Response']['Error']['Message'] ?? 'DNSPod 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'powerdns') {
        require_once __DIR__ . '/powerdns/api.php';
        if (!powerdns_enabled()) {
            return ['success' => false, 'message' => 'PowerDNS 未启用'];
        }
        $result = powerdns_create_record([
            'domain' => $rootDomain,
            'name' => $fullDomain,
            'type' => 'NS',
            'value' => $nameserver,
            'ttl' => 3600,
        ]);
        if ($result['success']) {
            return ['success' => true, 'provider_record_id' => ''];
        }
        $errorMsg = 'PowerDNS 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    return ['success' => false, 'message' => "服务商 {$provider} 暂不支持自动同步"];
}

function dns_ns_delete_from_provider(int $domainId, string $nameserver, string $providerRecordId): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
    $stmt->execute([':id' => $domainId]);
    $domain = $stmt->fetch();

    if (!$domain) {
        return ['success' => false, 'message' => '域名不存在'];
    }

    $provider = $domain['provider'];
    $subdomain = $domain['subdomain'];
    $rootDomain = $domain['root_domain'];
    $fullDomain = dns_full_domain($rootDomain, $subdomain);

    if ($provider === 'cloudflare') {
        require_once __DIR__ . '/cloudflare/api.php';
        if (!cloudflare_enabled()) {
            return ['success' => false, 'message' => 'Cloudflare 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少 Cloudflare 记录 ID'];
        }
        $zoneId = cloudflare_find_zone_id($rootDomain);
        if (!$zoneId) {
            return ['success' => false, 'message' => '未找到 Cloudflare Zone'];
        }
        return cloudflare_delete_record([
            'zone_id' => $zoneId,
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'alidns') {
        require_once __DIR__ . '/alidns/api.php';
        if (!alidns_enabled()) {
            return ['success' => false, 'message' => '阿里云 DNS 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少阿里云记录 ID'];
        }
        return alidns_delete_record([
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'dnspod') {
        require_once __DIR__ . '/dnspod/api.php';
        if (!dnspod_enabled()) {
            return ['success' => false, 'message' => 'DNSPod 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少 DNSPod 记录 ID'];
        }
        return dnspod_delete_record([
            'domain' => $rootDomain,
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'powerdns') {
        require_once __DIR__ . '/powerdns/api.php';
        if (!powerdns_enabled()) {
            return ['success' => false, 'message' => 'PowerDNS 未启用'];
        }
        return powerdns_delete_record([
            'domain' => $rootDomain,
            'name' => $fullDomain,
            'type' => 'NS',
        ]);
    }

    return ['success' => false, 'message' => "服务商 {$provider} 暂不支持自动同步"];
}

function dns_txt_records_by_domain_id(int $domainId): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT * FROM txt_records WHERE domain_id = :domain_id ORDER BY id ASC');
    $stmt->execute([':domain_id' => $domainId]);
    return $stmt->fetchAll();
}

function dns_txt_record_add(int $domainId, string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['success' => false, 'message' => 'TXT 记录值不能为空'];
    }

    if (empty(dns_domain_record_config()['enable_txt_records'])) {
        return ['success' => false, 'message' => 'TXT 记录类型已禁用'];
    }

    $pdo = dns_db();

    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM txt_records WHERE domain_id = :domain_id');
    $stmt->execute([':domain_id' => $domainId]);
    $count = (int) $stmt->fetch()['cnt'];

    $maxTxt = (int) (dns_domain_record_config()['max_txt_records'] ?? 3);
    if ($maxTxt > 0 && $count >= $maxTxt) {
        return ['success' => false, 'message' => "最多只能添加 {$maxTxt} 条 TXT 记录"];
    }

    $stmt = $pdo->prepare('INSERT INTO txt_records (domain_id, value) VALUES (:domain_id, :value)');
    $success = $stmt->execute([
        ':domain_id' => $domainId,
        ':value' => $value,
    ]);

    if (!$success) {
        return ['success' => false, 'message' => '添加失败，请重试'];
    }

    $recordId = (int) $pdo->lastInsertId();

    $provider = dns_domain_provider($domainId);
    if ($provider !== 'manual' && $provider !== 'powerdns') {
        $syncResult = dns_txt_sync_to_provider($domainId, $value);
        if ($syncResult['success'] && !empty($syncResult['provider_record_id'])) {
            $stmt = $pdo->prepare('UPDATE txt_records SET provider_record_id = :provider_record_id WHERE id = :id');
            $stmt->execute([':provider_record_id' => $syncResult['provider_record_id'], ':id' => $recordId]);
        }
    } elseif ($provider === 'powerdns') {
        $syncResult = dns_txt_sync_domain_to_provider($domainId);
    } else {
        $syncResult = ['success' => true, 'provider_record_id' => ''];
    }

    return ['success' => true, 'message' => '添加成功', 'id' => $recordId, 'sync' => $syncResult];
}

function dns_txt_record_delete(int $id): array
{
    $pdo = dns_db();

    $stmt = $pdo->prepare('SELECT tr.*, d.id AS domain_id FROM txt_records tr WHERE tr.id = :id');
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch();

    if (!$record) {
        return ['success' => false, 'message' => '记录不存在'];
    }

    $domainId = (int) $record['domain_id'];
    $provider = dns_domain_provider($domainId);

    $stmt = $pdo->prepare('DELETE FROM txt_records WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if (!empty($record['provider_record_id'])) {
        dns_txt_delete_from_provider($domainId, $record['value'], $record['provider_record_id']);
    }

    return ['success' => true, 'message' => '删除成功'];
}

function dns_txt_sync_to_provider(int $domainId, string $value): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
    $stmt->execute([':id' => $domainId]);
    $domain = $stmt->fetch();

    if (!$domain) {
        return ['success' => false, 'message' => '域名不存在'];
    }

    $provider = $domain['provider'];
    $subdomain = $domain['subdomain'];
    $rootDomain = $domain['root_domain'];
    $fullDomain = dns_full_domain($rootDomain, $subdomain);

    if ($provider === 'cloudflare') {
        require_once __DIR__ . '/cloudflare/api.php';
        if (!cloudflare_enabled()) {
            return ['success' => false, 'message' => 'Cloudflare 未启用'];
        }
        $result = cloudflare_create_record([
            'type' => 'TXT',
            'name' => $fullDomain,
            'value' => $value,
            'domain' => $rootDomain,
            'ttl' => 1,
        ]);
        if ($result['success'] && !empty($result['raw']['result']['id'])) {
            return ['success' => true, 'provider_record_id' => $result['raw']['result']['id']];
        }
        $errorMsg = $result['raw']['errors'][0]['message'] ?? 'Cloudflare 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'alidns') {
        require_once __DIR__ . '/alidns/api.php';
        if (!alidns_enabled()) {
            return ['success' => false, 'message' => '阿里云 DNS 未启用'];
        }
        $result = alidns_create_record([
            'domain' => $rootDomain,
            'rr' => $subdomain,
            'type' => 'TXT',
            'value' => $value,
            'ttl' => 600,
        ]);
        if ($result['success']) {
            $body = json_decode($result['raw']['body'] ?? '', true);
            $recordId = $body['RecordId'] ?? '';
            return ['success' => true, 'provider_record_id' => $recordId];
        }
        $body = json_decode($result['raw']['body'] ?? '', true);
        $errorMsg = $body['Message'] ?? '阿里云 DNS 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'dnspod') {
        require_once __DIR__ . '/dnspod/api.php';
        if (!dnspod_enabled()) {
            return ['success' => false, 'message' => 'DNSPod 未启用'];
        }
        $result = dnspod_create_record([
            'domain' => $rootDomain,
            'rr' => $subdomain,
            'type' => 'TXT',
            'value' => $value,
            'ttl' => 600,
        ]);
        if ($result['success'] && !empty($result['raw']['Response']['RecordId'])) {
            return ['success' => true, 'provider_record_id' => (string) $result['raw']['Response']['RecordId']];
        }
        $errorMsg = $result['raw']['Response']['Error']['Message'] ?? 'DNSPod 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'powerdns') {
        require_once __DIR__ . '/powerdns/api.php';
        if (!powerdns_enabled()) {
            return ['success' => false, 'message' => 'PowerDNS 未启用'];
        }
        $result = powerdns_create_record([
            'domain' => $rootDomain,
            'name' => $fullDomain,
            'type' => 'TXT',
            'value' => $value,
            'ttl' => 3600,
        ]);
        if ($result['success']) {
            return ['success' => true, 'provider_record_id' => ''];
        }
        $errorMsg = 'PowerDNS 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    return ['success' => false, 'message' => "服务商 {$provider} 暂不支持自动同步"];
}

function dns_txt_delete_from_provider(int $domainId, string $value, string $providerRecordId): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
    $stmt->execute([':id' => $domainId]);
    $domain = $stmt->fetch();

    if (!$domain) {
        return ['success' => false, 'message' => '域名不存在'];
    }

    $provider = $domain['provider'];
    $rootDomain = $domain['root_domain'];
    $subdomain = $domain['subdomain'];
    $fullDomain = dns_full_domain($rootDomain, $subdomain);

    if ($provider === 'cloudflare') {
        require_once __DIR__ . '/cloudflare/api.php';
        if (!cloudflare_enabled()) {
            return ['success' => false, 'message' => 'Cloudflare 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少 Cloudflare 记录 ID'];
        }
        $zoneId = cloudflare_find_zone_id($rootDomain);
        if (!$zoneId) {
            return ['success' => false, 'message' => '未找到 Cloudflare Zone'];
        }
        return cloudflare_delete_record([
            'zone_id' => $zoneId,
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'alidns') {
        require_once __DIR__ . '/alidns/api.php';
        if (!alidns_enabled()) {
            return ['success' => false, 'message' => '阿里云 DNS 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少阿里云记录 ID'];
        }
        return alidns_delete_record([
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'dnspod') {
        require_once __DIR__ . '/dnspod/api.php';
        if (!dnspod_enabled()) {
            return ['success' => false, 'message' => 'DNSPod 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少 DNSPod 记录 ID'];
        }
        return dnspod_delete_record([
            'domain' => $rootDomain,
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'powerdns') {
        require_once __DIR__ . '/powerdns/api.php';
        if (!powerdns_enabled()) {
            return ['success' => false, 'message' => 'PowerDNS 未启用'];
        }
        return powerdns_delete_record([
            'domain' => $rootDomain,
            'name' => $fullDomain,
            'type' => 'TXT',
        ]);
    }

    return ['success' => false, 'message' => "服务商 {$provider} 暂不支持自动同步"];
}

function dns_records_by_domain_id(int $domainId): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT * FROM dns_records WHERE domain_id = :domain_id ORDER BY id ASC');
    $stmt->execute([':domain_id' => $domainId]);
    return $stmt->fetchAll();
}

function dns_record_add(int $domainId, string $type, string $name, string $value, bool $proxied = false): array
{
    $type = strtoupper(trim($type));
    $name = trim($name);
    $value = trim($value);

    if (!in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
        return ['success' => false, 'message' => '不支持的记录类型'];
    }

    $config = dns_domain_record_config();
    $enableKey = 'enable_' . strtolower($type) . '_records';
    $config = dns_domain_record_config();
    if (empty($config[$enableKey])) {
        return ['success' => false, 'message' => "{$type} 记录类型已禁用"];
    }

    if ($name === '') {
        return ['success' => false, 'message' => '记录名称不能为空'];
    }
    if ($value === '') {
        return ['success' => false, 'message' => '记录值不能为空'];
    }

    $pdo = dns_db();

    $typeLimitKey = 'max_' . strtolower($type) . '_records';
    $maxType = (int) ($config[$typeLimitKey] ?? 10);

    if ($maxType > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM dns_records WHERE domain_id = :domain_id AND type = :type');
        $stmt->execute([':domain_id' => $domainId, ':type' => $type]);
        $count = (int) $stmt->fetch()['cnt'];
        if ($count >= $maxType) {
            return ['success' => false, 'message' => "最多只能添加 {$maxType} 条 {$type} 记录"];
        }
    }

    $stmt = $pdo->prepare('INSERT INTO dns_records (domain_id, type, name, value, proxied) VALUES (:domain_id, :type, :name, :value, :proxied)');
    $success = $stmt->execute([
        ':domain_id' => $domainId,
        ':type' => $type,
        ':name' => $name,
        ':value' => $value,
        ':proxied' => $proxied ? 1 : 0,
    ]);

    if (!$success) {
        return ['success' => false, 'message' => '添加失败，请重试'];
    }

    $recordId = (int) $pdo->lastInsertId();

    $syncResult = dns_record_sync_to_provider($domainId, $recordId, $type, $name, $value, $proxied);
    if ($syncResult['success'] && !empty($syncResult['provider_record_id'])) {
        $stmt = $pdo->prepare('UPDATE dns_records SET provider_record_id = :provider_record_id WHERE id = :id');
        $stmt->execute([':provider_record_id' => $syncResult['provider_record_id'], ':id' => $recordId]);
    }

    return ['success' => true, 'message' => '添加成功', 'id' => $recordId, 'sync' => $syncResult];
}

function dns_record_delete(int $id): array
{
    $pdo = dns_db();

    $stmt = $pdo->prepare('SELECT dr.*, d.id AS domain_id FROM dns_records dr WHERE dr.id = :id');
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch();

    if (!$record) {
        return ['success' => false, 'message' => '记录不存在'];
    }

    $domainId = (int) $record['domain_id'];

    $stmt = $pdo->prepare('DELETE FROM dns_records WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if (!empty($record['provider_record_id'])) {
        dns_record_delete_from_provider($domainId, $record['type'], $record['provider_record_id']);
    }

    return ['success' => true, 'message' => '删除成功'];
}

function dns_record_sync_to_provider(int $domainId, int $recordId, string $type, string $name, string $value, bool $proxied = false): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
    $stmt->execute([':id' => $domainId]);
    $domain = $stmt->fetch();

    if (!$domain) {
        return ['success' => false, 'message' => '域名不存在'];
    }

    $provider = $domain['provider'];
    $subdomain = $domain['subdomain'];
    $rootDomain = $domain['root_domain'];
    $fullDomain = $name === '@' ? $rootDomain : $name . '.' . $rootDomain;

    if ($provider === 'cloudflare') {
        require_once __DIR__ . '/cloudflare/api.php';
        if (!cloudflare_enabled()) {
            return ['success' => false, 'message' => 'Cloudflare 未启用'];
        }
        $result = cloudflare_create_record([
            'type' => $type,
            'name' => $fullDomain,
            'value' => $value,
            'domain' => $rootDomain,
            'ttl' => 1,
            'proxied' => $proxied,
        ]);
        if ($result['success'] && !empty($result['raw']['result']['id'])) {
            return ['success' => true, 'provider_record_id' => $result['raw']['result']['id']];
        }
        $errorMsg = $result['raw']['errors'][0]['message'] ?? 'Cloudflare 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'alidns') {
        require_once __DIR__ . '/alidns/api.php';
        if (!alidns_enabled()) {
            return ['success' => false, 'message' => '阿里云 DNS 未启用'];
        }
        $result = alidns_create_record([
            'domain' => $rootDomain,
            'rr' => $name === '@' ? '@' : $name,
            'type' => $type,
            'value' => $value,
            'ttl' => 600,
        ]);
        if ($result['success']) {
            $body = json_decode($result['raw']['body'] ?? '', true);
            $recordId = $body['RecordId'] ?? '';
            return ['success' => true, 'provider_record_id' => $recordId];
        }
        $body = json_decode($result['raw']['body'] ?? '', true);
        $errorMsg = $body['Message'] ?? '阿里云 DNS 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'dnspod') {
        require_once __DIR__ . '/dnspod/api.php';
        if (!dnspod_enabled()) {
            return ['success' => false, 'message' => 'DNSPod 未启用'];
        }
        $result = dnspod_create_record([
            'domain' => $rootDomain,
            'rr' => $name === '@' ? '@' : $name,
            'type' => $type,
            'value' => $value,
            'ttl' => 600,
        ]);
        if ($result['success'] && !empty($result['raw']['Response']['RecordId'])) {
            return ['success' => true, 'provider_record_id' => (string) $result['raw']['Response']['RecordId']];
        }
        $errorMsg = $result['raw']['Response']['Error']['Message'] ?? 'DNSPod 同步失败';
        return ['success' => false, 'message' => $errorMsg];
    }

    if ($provider === 'powerdns') {
        require_once __DIR__ . '/powerdns/api.php';
        if (!powerdns_enabled()) {
            return ['success' => false, 'message' => 'PowerDNS 未启用'];
        }
        $result = powerdns_create_record([
            'domain' => $rootDomain,
            'name' => $fullDomain,
            'type' => $type,
            'value' => $value,
            'ttl' => 3600,
        ]);
        if ($result['success']) {
            return ['success' => true, 'provider_record_id' => ''];
        }
        return ['success' => false, 'message' => 'PowerDNS 同步失败'];
    }

    return ['success' => true, 'provider_record_id' => ''];
}

function dns_record_delete_from_provider(int $domainId, string $type, string $providerRecordId): array
{
    $pdo = dns_db();
    $stmt = $pdo->prepare('SELECT d.*, r.root_domain, r.provider FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.id = :id');
    $stmt->execute([':id' => $domainId]);
    $domain = $stmt->fetch();

    if (!$domain) {
        return ['success' => false, 'message' => '域名不存在'];
    }

    $provider = $domain['provider'];
    $rootDomain = $domain['root_domain'];

    if ($provider === 'cloudflare') {
        require_once __DIR__ . '/cloudflare/api.php';
        if (!cloudflare_enabled()) {
            return ['success' => false, 'message' => 'Cloudflare 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少 Cloudflare 记录 ID'];
        }
        $zoneId = cloudflare_find_zone_id($rootDomain);
        if (!$zoneId) {
            return ['success' => false, 'message' => '未找到 Cloudflare Zone'];
        }
        return cloudflare_delete_record([
            'zone_id' => $zoneId,
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'alidns') {
        require_once __DIR__ . '/alidns/api.php';
        if (!alidns_enabled()) {
            return ['success' => false, 'message' => '阿里云 DNS 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少阿里云记录 ID'];
        }
        return alidns_delete_record([
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'dnspod') {
        require_once __DIR__ . '/dnspod/api.php';
        if (!dnspod_enabled()) {
            return ['success' => false, 'message' => 'DNSPod 未启用'];
        }
        if (empty($providerRecordId)) {
            return ['success' => false, 'message' => '缺少 DNSPod 记录 ID'];
        }
        return dnspod_delete_record([
            'domain' => $rootDomain,
            'record_id' => $providerRecordId,
        ]);
    }

    if ($provider === 'powerdns') {
        require_once __DIR__ . '/powerdns/api.php';
        if (!powerdns_enabled()) {
            return ['success' => false, 'message' => 'PowerDNS 未启用'];
        }
        return powerdns_delete_record([
            'domain' => $rootDomain,
            'name' => $fullDomain,
            'type' => $type,
        ]);
    }

    return ['success' => true, 'message' => '已从本地删除'];
}
