<?php
require __DIR__ . '/../layout.php';

$user = auth_user_by_id((int) $_SESSION['user_id']);
$requestsCount = auth_user_requests_count((int) $_SESSION['user_id']);
$domainsCount = auth_user_domains_count((int) $_SESSION['user_id']);
$hasWhois = $user ? auth_user_has_whois($user) : false;
$announcements = auth_db()->query('SELECT title, content, published_at FROM announcements WHERE status = 1 ORDER BY id DESC LIMIT 5')->fetchAll();

user_render('用户概览', 'dashboard', function () use ($user, $requestsCount, $domainsCount, $hasWhois, $announcements): void {
    ?>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="panel">
            <div class="text-sm text-slate-500">申请数量</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $requestsCount ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500">域名数量</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $domainsCount ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500">whois 状态</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= $hasWhois ? '已填写' : '未填写' ?></div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900">公告</h2>
            <div class="mt-4 space-y-4">
                <?php foreach ($announcements as $row): ?>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-900"><?= htmlspecialchars($row['title']) ?></div>
                        <div class="mt-2 text-sm text-slate-600"><?= htmlspecialchars($row['content']) ?></div>
                        <div class="mt-2 text-xs text-slate-400"><?= htmlspecialchars($row['published_at'] ?? '') ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900">资料状态</h2>
            <div class="mt-4 text-sm text-slate-600">
                <div>邮箱：<?= htmlspecialchars($user['email'] ?? '') ?></div>
                <div class="mt-2">手机号：<?= htmlspecialchars($user['phone'] ?? '') ?></div>
                <div class="mt-2">whois 联系人：<?= htmlspecialchars($user['whois_name'] ?? '') ?></div>
                <div class="mt-2">whois 邮箱：<?= htmlspecialchars($user['whois_email'] ?? '') ?></div>
                <div class="mt-2">whois 电话：<?= htmlspecialchars($user['whois_phone'] ?? '') ?></div>
            </div>
            <a href="/user/profile/" class="btn-primary mt-6">完善资料</a>
        </section>
    </div>
    <?php
});
