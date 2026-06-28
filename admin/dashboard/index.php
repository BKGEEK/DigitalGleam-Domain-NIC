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

admin_dashboard_render('后台概览', 'home', function () use ($stats): void {
    ?>
    <div class="grid gap-4 md:grid-cols-4">
        <div class="panel">
            <div class="text-sm text-slate-500">待审核申请</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $stats['requests'] ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500">主域名数量</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $stats['root_domains'] ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500">可用域名</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $stats['domains'] ?></div>
        </div>
        <div class="panel">
            <div class="text-sm text-slate-500">在线用户</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900"><?= (int) $stats['users'] ?></div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="panel">
            <h2 class="text-lg font-semibold text-slate-900">系统状态</h2>
            <p class="mt-3 text-sm text-slate-600">这里后续接入站点开关、数据库连接状态、邮件发送状态和 DNS 接口状态。</p>
        </div>
        <div class="panel">
            <h2 class="text-lg font-semibold text-slate-900">快捷入口</h2>
            <p class="mt-3 text-sm text-slate-600">后续这里可以放公告、审核队列和最近操作记录。</p>
        </div>
    </div>
    <?php
});
