<?php
require_once __DIR__ . '/module/dns/service.php';
require_once __DIR__ . '/lang/helper.php';

$config = require __DIR__ . '/config/config.php';
$pageTitle = $config['app']['name'] ?? __('app.name');

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
<html lang="<?= lang_current() ?>">
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
                        <span class="text-xl font-semibold text-brand-600"><?= htmlspecialchars($config['app']['name'] ?? __('app.name')) ?></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <a href="/whois/index.php" class="text-slate-600 hover:text-brand-600 transition-colors"><?= __('nav.whois') ?></a>
                        <a href="#domain" class="text-slate-600 hover:text-brand-600 transition-colors"><?= __('nav.domain_search') ?></a>
                        <a href="#notice" class="text-slate-600 hover:text-brand-600 transition-colors"><?= __('nav.announcement') ?></a>
                        <a href="/user/login/" class="rounded-full bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors"><?= __('nav.user_login') ?></a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 域名检索区域 -->
        <section id="domain" class="border-b border-slate-200 bg-white">
            <div class="page-wrap py-12">
                <div class="mx-auto max-w-3xl">
                    <div class="text-sm font-medium text-brand-600"><?= htmlspecialchars($config['app']['name'] ?? __('app.name')) ?></div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900"><?= __('index.title') ?></h1>
                    <p class="mt-4 text-sm leading-6 text-slate-600"><?= __('index.subtitle') ?></p>

                    <form method="get" class="mt-8 grid gap-4 lg:grid-cols-[1fr_260px_140px]">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('index.prefix') ?></label>
                            <input name="prefix" value="<?= htmlspecialchars($prefix) ?>" placeholder="api / cdn / img" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition-all">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('index.root_domain') ?></label>
                            <select name="root_domain_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition-all">
                                <option value=""><?= __('index.select_placeholder') ?></option>
                                <?php foreach ($availableRoots as $root): ?>
                                    <option value="<?= (int) $root['id'] ?>" <?= $selectedRootId === (int) $root['id'] ? 'selected' : '' ?>><?= htmlspecialchars($root['root_domain']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn-primary w-full justify-center"><?= __('index.search') ?></button>
                        </div>
                    </form>

                    <?php if ($searchResult): ?>
                        <div class="mt-8 rounded-3xl border border-slate-200 bg-slate-50 p-6">
                            <div class="text-sm text-slate-500"><?= __('index.result') ?></div>
                            <div class="mt-2 text-2xl font-semibold text-slate-900"><?= htmlspecialchars($searchResult['candidate']) ?></div>
                            <div class="mt-3 text-sm text-slate-600">
                                <?= __('index.root_domain_label') ?><?= htmlspecialchars($searchResult['root_domain']) ?>，<?= __('index.provider_label') ?><?= htmlspecialchars($searchResult['provider']) ?>
                            </div>
                            <div class="mt-4">
                                <span class="rounded-full px-3 py-1 text-xs font-medium <?= $searchResult['status'] === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                    <?= match ($searchResult['status']) {
                                        'has_dot' => __('index.status.has_dot'),
                                        'invalid_chars' => __('index.status.invalid_chars'),
                                        'too_short' => __('index.status.too_short', ['min' => $minLength]),
                                        'too_long' => __('index.status.too_long', ['max' => $maxLength]),
                                        'occupied' => __('index.status.occupied'),
                                        'available' => __('index.status.available'),
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
                    <h2 class="text-lg font-semibold text-slate-900"><?= __('index.card1_title') ?></h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600"><?= __('index.card1_desc') ?></p>
                </div>
                <div class="panel">
                    <h2 class="text-lg font-semibold text-slate-900"><?= __('index.card2_title') ?></h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600"><?= __('index.card2_desc') ?></p>
                </div>
                <div class="panel">
                    <h2 class="text-lg font-semibold text-slate-900"><?= __('index.card3_title') ?></h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600"><?= __('index.card3_desc') ?></p>
                </div>
            </div>
        </section>

        <!-- 公告 + 项目说明区域 -->
        <section class="page-wrap py-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="panel">
                    <div class="text-sm font-medium text-brand-600"><?= __('nav.announcement') ?></div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900"><?= __('index.notice_title') ?></h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600"><?= htmlspecialchars($announcement !== '' ? $announcement : __('index.notice_empty')) ?></p>
                </div>
                <div class="panel">
                    <div class="text-sm font-medium text-brand-600"><?= __('index.intro_label') ?></div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900"><?= __('index.intro_title') ?></h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600"><?= __('index.intro_desc') ?></p>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white mt-8">
        <div class="page-wrap py-6 text-sm text-slate-500">
            <div>© <?= date('Y') ?> <?= htmlspecialchars($config['app']['name'] ?? __('app.name')) ?></div>
            <div class="mt-1"><?= __('footer.motto') ?></div>
        </div>
    </footer>
</body>
</html>
