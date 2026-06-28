<?php
session_start();

if (empty($_SESSION['admin_id'])) {
    header('Location: /admin/login/');
    exit;
}

require __DIR__ . '/../../resource/js/auth.php';

function admin_dashboard_menu(): array
{
    return [
        ['label' => '概览', 'path' => '/admin/dashboard/', 'key' => 'home'],
        ['label' => '主域名', 'path' => '/admin/dashboard/root-domains/', 'key' => 'root-domains'],
        ['label' => '域名池', 'path' => '/admin/dashboard/domains/', 'key' => 'domains'],
        ['label' => '申请审核', 'path' => '/admin/dashboard/requests/', 'key' => 'requests'],
        ['label' => '用户管理', 'path' => '/admin/dashboard/users/', 'key' => 'users'],
        ['label' => '公告管理', 'path' => '/admin/dashboard/announcements/', 'key' => 'announcements'],
        ['label' => '系统设置', 'path' => '/admin/dashboard/settings/', 'key' => 'settings'],
        ['label' => '邮件服务', 'path' => '/admin/dashboard/smtp/', 'key' => 'smtp'],
        ['label' => 'DNS 接口', 'path' => '/admin/dashboard/dns/', 'key' => 'dns'],
        ['label' => '第三方登录', 'path' => '/admin/dashboard/oauth/', 'key' => 'oauth'],
    ];
}

function admin_dashboard_render(string $title, string $active, callable $contentRenderer): void
{
    $pageTitle = $title;
    require __DIR__ . '/../../resource/css/header.php';
    $menu = admin_dashboard_menu();
    ?>
    <div class="flex min-h-[calc(100vh-120px)] bg-slate-50">
        <aside class="hidden w-64 border-r border-slate-200 bg-white px-4 py-6 lg:block">
            <div class="text-lg font-semibold text-slate-900">后台管理</div>
            <nav class="mt-6 space-y-1 text-sm">
                <?php foreach ($menu as $item): ?>
                    <a href="<?= htmlspecialchars($item['path']) ?>" class="block rounded-2xl px-4 py-3 <?= $active === $item['key'] ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="flex-1">
            <header class="border-b border-slate-200 bg-white px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-slate-500">管理员</div>
                        <div class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($_SESSION['admin_name'] ?? '管理员') ?></div>
                    </div>
                    <a href="/admin/logout.php" class="text-sm font-medium text-brand-700 hover:text-brand-800">退出登录</a>
                </div>
            </header>

            <main class="p-6">
                <?php $contentRenderer(); ?>
            </main>
        </div>
    </div>
    <?php
    require __DIR__ . '/../../resource/css/footer.php';
}
