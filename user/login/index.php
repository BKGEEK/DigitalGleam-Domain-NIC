<?php
session_start();
if (!file_exists(__DIR__ . '/../../install/install.lock')) {
    header('Location: /install/install.php');
    exit;
}

require __DIR__ . '/../../resource/css/header.php';
require __DIR__ . '/../../resource/js/auth.php';

$pageTitle = __('user.login.title');
$error = '';
$oauthConfig = auth_config()['oauth'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = __('user.login.error_empty');
    } elseif (auth_login_user($username, $password)) {
        auth_redirect('/user/dashboard/');
    } else {
        $error = __('user.login.error_failed');
    }
}
?>
<main class="page-wrap flex min-h-[calc(100vh-120px)] items-center py-12">
    <div class="grid w-full gap-8 lg:grid-cols-2">
        <section class="flex flex-col justify-center">
            <span class="badge-brand w-fit"><?= __('user.login.badge') ?></span>
            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-slate-900"><?= __('user.login.heading') ?></h1>
            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-600">
                <?= __('user.login.desc') ?>
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <?php if (!empty($oauthConfig['github']['enabled'])): ?>
                    <a href="/module/oauth/login.php?provider=github&return=/user/dashboard/" class="btn-secondary"><?= __('user.login.github') ?></a>
                <?php endif; ?>
                <?php if (!empty($oauthConfig['google']['enabled'])): ?>
                    <a href="/module/oauth/login.php?provider=google&return=/user/dashboard/" class="btn-secondary"><?= __('user.login.google') ?></a>
                <?php endif; ?>
                <?php if (!empty($oauthConfig['nodeloc']['enabled'])): ?>
                    <a href="/module/oauth/login.php?provider=nodeloc&return=/user/dashboard/" class="btn-secondary"><?= __('user.login.nodeloc') ?></a>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <?php if ($error): ?>
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="" method="post" class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('user.login.account') ?></label>
                    <input type="text" name="username" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('user.login.account_placeholder') ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('user.login.password') ?></label>
                    <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('user.login.password_placeholder') ?>">
                </div>
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-slate-600">
                        <input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        <?= __('user.login.remember') ?>
                    </label>
                    <a href="/user/register/" class="text-brand-700 hover:text-brand-800"><?= __('user.login.no_account') ?></a>
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><?= __('user.login.submit') ?></button>
            </form>
        </section>
    </div>
</main>
<?php require __DIR__ . '/../../resource/css/footer.php'; ?>
