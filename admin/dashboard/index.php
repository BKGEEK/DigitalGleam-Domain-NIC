<?php
require __DIR__ . '/layout.php';
require_once __DIR__ . '/../../resource/js/auth.php';

$pdo = auth_db();
$stats = [
    'requests' => (int) $pdo->query('SELECT COUNT(*) FROM domain_requests WHERE status = 1')->fetchColumn(),
    'root_domains' => (int) $pdo->query('SELECT COUNT(*) FROM root_domains')->fetchColumn(),
    'domains' => (int) $pdo->query('SELECT COUNT(*) FROM domains WHERE status IN (1,2,3)')->fetchColumn(),
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE status = 1')->fetchColumn(),
];

admin_dashboard_render(__('admin.dashboard.title'), 'home', function () use ($stats): void {
    ?>
    <div class="grid gap-4 md:grid-cols-4">
        <div class="panel">
            <div class="text-sm text-slate-500"><?= __('admin.dashboard.pending_requests') ?></div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $stats['requests'] ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500"><?= __('admin.dashboard.root_domains') ?></div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $stats['root_domains'] ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500"><?= __('admin.dashboard.available_domains') ?></div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $stats['domains'] ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500"><?= __('admin.dashboard.active_users') ?></div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $stats['users'] ?></div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="panel">
            <h2 class="text-lg font-semibold text-slate-900"><?= __('admin.dashboard.system_status') ?></h2>
            <p class="mt-3 text-sm text-slate-600"><?= __('admin.dashboard.status_desc') ?></p>
        </div>
        <div class="panel">
            <h2 class="text-lg font-semibold text-slate-900"><?= __('admin.dashboard.quick_links') ?></h2>
            <p class="mt-3 text-sm text-slate-600"><?= __('admin.dashboard.quick_links_desc') ?></p>
        </div>
    </div>
    <?php
});
