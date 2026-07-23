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
        ['label' => __('admin.menu.dashboard'), 'path' => '/admin/dashboard/', 'key' => 'home'],
        ['label' => __('admin.menu.root_domains'), 'path' => '/admin/dashboard/root-domains/', 'key' => 'root-domains'],
        ['label' => __('admin.menu.domains'), 'path' => '/admin/dashboard/domains/', 'key' => 'domains'],
        ['label' => __('admin.menu.requests'), 'path' => '/admin/dashboard/requests/', 'key' => 'requests'],
        ['label' => __('admin.menu.users'), 'path' => '/admin/dashboard/users/', 'key' => 'users'],
        ['label' => __('admin.menu.announcements'), 'path' => '/admin/dashboard/announcements/', 'key' => 'announcements'],
        ['label' => __('admin.menu.settings'), 'path' => '/admin/dashboard/settings/', 'key' => 'settings'],
        ['label' => __('admin.menu.smtp'), 'path' => '/admin/dashboard/smtp/', 'key' => 'smtp'],
        ['label' => __('admin.menu.email_templates'), 'path' => '/admin/dashboard/email-templates/', 'key' => 'email-templates'],
        ['label' => __('admin.menu.dns'), 'path' => '/admin/dashboard/dns/', 'key' => 'dns'],
        ['label' => __('admin.menu.oauth'), 'path' => '/admin/dashboard/oauth/', 'key' => 'oauth'],
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
            <div class="text-lg font-semibold text-slate-900"><?= __('admin.layout.title') ?></div>
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
                        <div class="text-sm text-slate-500"><?= __('admin.layout.admin') ?></div>
                        <div class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($_SESSION['admin_name'] ?? __('admin.layout.admin')) ?></div>
                    </div>
                    <a href="/admin/logout.php" class="text-sm font-medium text-brand-700 hover:text-brand-800"><?= __('admin.layout.logout') ?></a>
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
