<?php
require_once __DIR__ . '/module/dns/service.php';

$config = require __DIR__ . '/config/config.php';
$pageTitle = $config['app']['name'] ?? '数星二级域名分发';

$domainConfig = $config['domain'] ?? [];
$minLength = max(1, (int) ($domainConfig['min_length'] ?? 3));
$maxLength = max(1, (int) ($domainConfig['max_length'] ?? 24));
$allowUnicode = !empty($domainConfig['allow_unicode']);

$prefix = trim((string) ($_GET['prefix'] ?? ''));
$selectedRootId = (int) ($_GET['root_domain_id'] ?? 0);
$availableRoots = dns_root_domains(true);
$searchResult = null;



if ($prefix !== '' && $selectedRootId > 0) {
    $root = dns_root_domain_by_id($selectedRootId);
    if ($root && (int) $root['status'] === 1) {
        $candidate = dns_full_domain((string) $root['root_domain'], $prefix);
        $domains = dns_domains_by_root_id($selectedRootId, true);
        $matched = null;
        foreach ($domains as $row) {
            if (strcasecmp((string) $row['subdomain'], $prefix) === 0) {
                $matched = $row;
                break;
            }
        }

        $hasDot = str_contains($prefix, '.');
        $invalidChars = !$allowUnicode && !preg_match('/^[a-zA-Z0-9]+$/', $prefix);
        $tooLong = mb_strlen($prefix) > $maxLength;
        $tooShort = mb_strlen($prefix) < $minLength;

        if ($hasDot) {
            $status = 'has_dot';
        } elseif ($invalidChars) {
            $status = 'invalid_chars';
        } elseif ($tooShort) {
            $status = 'too_short';
        } elseif ($tooLong) {
            $status = 'too_long';
        } elseif ($matched !== null) {
            $status = 'occupied';
        } else {
            $status = 'available';
        }

        $searchResult = [
            'candidate' => $candidate,
            'root_domain' => $root['root_domain'],
            'provider' => dns_provider_label((string) $root['provider']),
            'status' => $status,
        ];
    }
}
$announcement = '';
$pdo = dns_db();
$stmt = $pdo->query('SELECT title, content FROM announcements WHERE status = 1 ORDER BY id DESC LIMIT 1');
if ($stmt && ($row = $stmt->fetch())) {
    $announcement = trim((string) ($row['title'] . ' ' . $row['content']));
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eefbf7',
                            100: '#d7f6eb',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .page-wrap {
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .panel {
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            padding: 1.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            background-color: #16a34a;
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #ffffff;
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
        .btn-primary:hover {
            background-color: #15803d;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
    <main>
        <!-- 导航栏 -->
        <section class="border-b border-slate-200 bg-white">
            <div class="page-wrap py-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-semibold text-brand-600"><?= htmlspecialchars($config['app']['name'] ?? '数星二级域名分发') ?></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <a href="/whois/index.php" class="text-slate-600 hover:text-brand-600 transition-colors">WHOIS 查询</a>
                        <a href="#domain" class="text-slate-600 hover:text-brand-600 transition-colors">域名检索</a>
                        <a href="#notice" class="text-slate-600 hover:text-brand-600 transition-colors">公告</a>
                        <a href="/user/login/" class="rounded-full bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors">用户登录</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 域名检索区域 -->
        <section id="domain" class="border-b border-slate-200 bg-white">
            <div class="page-wrap py-12">
                <div class="mx-auto max-w-3xl">
                    <div class="text-sm font-medium text-brand-600"><?= htmlspecialchars($config['app']['name'] ?? '数星二级域名分发') ?></div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">域名前缀可用性检索</h1>
                    <p class="mt-4 text-sm leading-6 text-slate-600">输入前缀，选择已配置的主域名后缀，系统会检查该组合是否已在域名池中存在。</p>

                    <form method="get" class="mt-8 grid gap-4 lg:grid-cols-[1fr_260px_140px]">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">前缀</label>
                            <input name="prefix" value="<?= htmlspecialchars($prefix) ?>" placeholder="api / cdn / img" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition-all">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">主域名后缀</label>
                            <select name="root_domain_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition-all">
                                <option value="">请选择</option>
                                <?php foreach ($availableRoots as $root): ?>
                                    <option value="<?= (int) $root['id'] ?>" <?= $selectedRootId === (int) $root['id'] ? 'selected' : '' ?>><?= htmlspecialchars($root['root_domain']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn-primary w-full justify-center">检索</button>
                        </div>
                    </form>

                    <?php if ($searchResult): ?>
                        <div class="mt-8 rounded-3xl border border-slate-200 bg-slate-50 p-6">
                            <div class="text-sm text-slate-500">检索结果</div>
                            <div class="mt-2 text-2xl font-semibold text-slate-900"><?= htmlspecialchars($searchResult['candidate']) ?></div>
                            <div class="mt-3 text-sm text-slate-600">
                                主域名：<?= htmlspecialchars($searchResult['root_domain']) ?>，Provider：<?= htmlspecialchars($searchResult['provider']) ?>
                            </div>
                            <div class="mt-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium <?= $searchResult['status'] === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                    <?= match ($searchResult['status']) {
                                        'has_dot' => '不支持多级域名',
                                        'invalid_chars' => '前缀只能包含字母和数字',
                                        'too_short' => "前缀不能少于 {$minLength} 个字符",
                                        'too_long' => "前缀不能超过 {$maxLength} 个字符",
                                        'occupied' => '已占用',
                                        'available' => '可用',
                                    } ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- 特色卡片区域 -->
        <section id="notice" class="page-wrap py-8">
            <div class="grid gap-6 md:grid-cols-3">
                <div class="panel">
                    <h2 class="text-lg font-semibold text-slate-900">简约清新风</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">整体采用轻量后台式布局，信息清晰，适合长期管理和快速检索。</p>
                </div>
                <div class="panel">
                    <h2 class="text-lg font-semibold text-slate-900">主域名池</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">支持按主域名统一管理子域名前缀，后续可继续接入 DNS 真实解析逻辑。</p>
                </div>
                <div class="panel">
                    <h2 class="text-lg font-semibold text-slate-900">公告同步</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">首页公告直接读取后台发布内容，方便站点维护和临时通知。</p>
                </div>
            </div>
        </section>

        <!-- 公告 + 项目说明区域 -->
        <section class="page-wrap py-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="panel">
                    <div class="text-sm font-medium text-brand-600">公告</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">站点通知</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600"><?= htmlspecialchars($announcement !== '' ? $announcement : '暂无公告。') ?></p>
                </div>
                <div class="panel">
                    <div class="text-sm font-medium text-brand-600">介绍</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">项目说明</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600">这是一个 PHP + Tailwind + MySQL 的二级域名分发源码，当前已经具备首页检索、WHOIS 查询、后台管理和域名池基础结构。</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white mt-8">
        <div class="page-wrap py-6 text-sm text-slate-500">
            <div>© <?= date('Y') ?> <?= htmlspecialchars($config['app']['name'] ?? '数星二级域名分发') ?></div>
            <div class="mt-1">简约 · 清新 · 可扩展</div>
        </div>
    </footer>
</body>
</html>
