<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /user/login/');
    exit;
}

require_once __DIR__ . '/../resource/js/auth.php';
require_once __DIR__ . '/../module/dns/service.php';

function user_menu(): array
{
    return [
        ['label' => __('user.menu.dashboard'), 'path' => '/user/dashboard/', 'key' => 'dashboard'],
        ['label' => __('user.menu.requests'), 'path' => '/user/requests/', 'key' => 'requests'],
        ['label' => __('user.menu.domains'), 'path' => '/user/domains/', 'key' => 'domains'],
        ['label' => __('user.menu.profile'), 'path' => '/user/profile/', 'key' => 'profile'],
        ['label' => __('user.menu.oauth'), 'path' => '/user/oauth/', 'key' => 'oauth'],
        ['label' => __('user.menu.logout'), 'path' => '/user/logout.php', 'key' => 'logout'],
    ];
}

function user_render(string $title, string $active, callable $contentRenderer): void
{
    $pageTitle = $title;
    require __DIR__ . '/../resource/css/header.php';
    $menu = user_menu();
    ?>
    <div class="flex min-h-[calc(100vh-120px)] bg-slate-50">
        <aside class="hidden w-64 border-r border-slate-200 bg-white px-4 py-6 lg:block">
            <div class="text-lg font-semibold text-slate-900"><?= __('user.layout.title') ?></div>
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
                        <div class="text-sm text-slate-500"><?= __('user.layout.current_user') ?></div>
                        <div class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($_SESSION['user_name'] ?? __('user.layout.current_user')) ?></div>
                    </div>
                    <a href="/user/logout.php" class="text-sm font-medium text-brand-700 hover:text-brand-800"><?= __('user.layout.logout') ?></a>
                </div>
            </header>

            <main class="p-6">
                <?php $contentRenderer(); ?>
            </main>
        </div>
    </div>
    <?php
    require __DIR__ . '/../resource/css/footer.php';
}
