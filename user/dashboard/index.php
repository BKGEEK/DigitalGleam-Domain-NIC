<?php
require __DIR__ . '/../layout.php';

$user = auth_user_by_id((int) $_SESSION['user_id']);
$requestsCount = auth_user_requests_count((int) $_SESSION['user_id']);
$domainsCount = auth_user_domains_count((int) $_SESSION['user_id']);
$hasWhois = $user ? auth_user_has_whois($user) : false;
$announcements = auth_db()->query('SELECT title, content, published_at FROM announcements WHERE status = 1 ORDER BY id DESC LIMIT 5')->fetchAll();

$config = require __DIR__ . '/../../config/config.php';
$renewalGraceMonths = (int) ($config['domain']['renewal_grace_months'] ?? 3);
$renewalMonths = (int) ($config['domain']['renewal_months'] ?? 12);

$expiringDomains = [];
$pdo = auth_db();
$stmt = $pdo->prepare("SELECT d.*, r.root_domain FROM domains d INNER JOIN root_domains r ON r.id = d.root_domain_id WHERE d.assigned_to = :user_id AND d.expires_at IS NOT NULL AND d.expires_at > NOW() AND d.expires_at <= DATE_ADD(NOW(), INTERVAL :grace MONTH) ORDER BY d.expires_at ASC");
$stmt->execute([':user_id' => (int) $_SESSION['user_id'], ':grace' => $renewalGraceMonths]);
$expiringDomains = $stmt->fetchAll();

user_render(__('user.dashboard.title'), 'dashboard', function () use ($user, $requestsCount, $domainsCount, $hasWhois, $announcements, $expiringDomains, $renewalMonths): void {
    ?>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="panel">
            <div class="text-sm text-slate-500"><?= __('user.dashboard.requests_count') ?></div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $requestsCount ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500"><?= __('user.dashboard.domains_count') ?></div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $domainsCount ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500"><?= __('user.dashboard.whois_status') ?></div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= $hasWhois ? __('user.dashboard.whois_filled') : __('user.dashboard.whois_empty') ?></div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h2 class="text-xl font-semibold text-slate-900"><?= __('user.dashboard.announcements') ?></h2>
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
            <h2 class="text-xl font-semibold text-slate-900"><?= __('user.dashboard.profile_status') ?></h2>
            <div class="mt-4 text-sm text-slate-600">
                <div><?= __('user.dashboard.email') ?><?= htmlspecialchars($user['email'] ?? '') ?></div>
                <div class="mt-2"><?= __('user.dashboard.phone') ?><?= htmlspecialchars($user['phone'] ?? '') ?></div>
                <div class="mt-2"><?= __('user.dashboard.whois_name') ?><?= htmlspecialchars($user['whois_name'] ?? '') ?></div>
                <div class="mt-2"><?= __('user.dashboard.whois_email') ?><?= htmlspecialchars($user['whois_email'] ?? '') ?></div>
                <div class="mt-2"><?= __('user.dashboard.whois_phone') ?><?= htmlspecialchars($user['whois_phone'] ?? '') ?></div>
            </div>
            <a href="/user/profile/" class="btn-primary mt-6"><?= __('user.dashboard.complete_profile') ?></a>
        </section>
    </div>

    <?php if (!empty($expiringDomains)): ?>
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
            <div class="text-sm font-medium text-amber-800"><?= __('user.dashboard.expiring_domains') ?></div>
            <ul class="mt-2 space-y-1">
                <?php foreach ($expiringDomains as $ed): ?>
                    <li class="text-sm text-amber-700">
                        <?= htmlspecialchars(dns_domain_display_name($ed)) ?>
                        - <?= __('user.dashboard.expires') ?><?= htmlspecialchars(date('Y-m-d', strtotime($ed['expires_at']))) ?>
                        <?php if ($renewalMonths > 0): ?>
                            <a href="/user/domains/" class="ml-2 font-medium text-amber-800 underline hover:text-amber-900"><?= __('user.dashboard.go_renew') ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php
});
