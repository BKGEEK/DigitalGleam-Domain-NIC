<?php
if (!file_exists(__DIR__ . '/../install/install.lock')) {
    header('Location: /install/install.php');
    exit;
}

require_once __DIR__ . '/../resource/js/auth.php';

$pageTitle = 'WHOIS 查询';
$query = trim((string) ($_GET['query'] ?? ''));
$result = null;
$domainName = '';
$message = '';

if ($query !== '') {
    $pdo = auth_db();

    // Fetch all active root domains to match against
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

    if ($matchedRoot !== null && $matchedSub !== null) {
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

        if ($row && !empty($row['user_id']) && !empty($row['whois_public'])) {
            $result = $row;
            $domainName = $row['root_domain'];
            if ($row['subdomain'] !== '@') {
                $domainName = $row['subdomain'] . '.' . $domainName;
            }
        } else {
            $message = '未找到该域名的公开 WHOIS 信息。';
        }
    } else {
        $message = '未识别的域名，请输入完整域名（如 api.example.com）。';
    }
}

require __DIR__ . '/../resource/css/header.php';
?>
<main>
    <section class="page-wrap py-12">
        <div class="max-w-3xl">
            <div class="text-sm font-medium text-brand-600">WHOIS 查询</div>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">域名信息检索</h1>
            <p class="mt-4 text-sm leading-6 text-slate-600">输入完整域名查询已公开的域名持有者 WHOIS 信息。</p>

            <form method="get" class="mt-8 flex flex-col gap-3 sm:flex-row">
                <input name="query" value="<?= htmlspecialchars($query) ?>" class="flex-1 rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="完整域名，如 api.example.com">
                <button type="submit" class="btn-primary justify-center">查询</button>
            </form>

            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($result): ?>
                <div class="mt-6 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700">
                    查询域名：<strong><?= htmlspecialchars($domainName) ?></strong>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="panel">
                        <div class="text-sm text-slate-500">注册联系人</div>
                        <div class="mt-2 text-lg font-semibold text-slate-900"><?= htmlspecialchars($result['whois_name'] ?? '') ?></div>
                        <div class="mt-2 text-sm text-slate-600">公司：<?= htmlspecialchars($result['whois_company'] ?? '') ?></div>
                    </div>
                    <div class="panel">
                        <div class="text-sm text-slate-500">联系方式</div>
                        <div class="mt-2 text-sm text-slate-600">邮箱：<?= htmlspecialchars($result['whois_email'] ?? '') ?></div>
                        <div class="mt-2 text-sm text-slate-600">电话：<?= htmlspecialchars($result['whois_phone'] ?? '') ?></div>
                    </div>
                    <div class="panel md:col-span-2">
                        <div class="text-sm text-slate-500">地址</div>
                        <div class="mt-2 text-sm text-slate-600"><?= htmlspecialchars($result['whois_address'] ?? '') ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../resource/css/footer.php'; ?>
