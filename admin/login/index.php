<?php
session_start();
if (!file_exists(__DIR__ . '/../../install/install.lock')) {
    header('Location: /install/install.php');
    exit;
}

$pageTitle = __('admin.login.title');
require __DIR__ . '/../../resource/css/header.php';
require __DIR__ . '/../../resource/js/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = __('admin.login.error_empty');
    } elseif (auth_login_admin($username, $password)) {
        auth_redirect('/admin/dashboard/');
    } else {
        $error = __('admin.login.error_failed');
    }
}
?>

<main class="page-wrap flex min-h-[calc(100vh-120px)] items-center py-12">
    <div class="grid w-full gap-8 lg:grid-cols-2">
        <section class="flex flex-col justify-center">
            <span class="badge-brand w-fit"><?= __('admin.login.badge') ?></span>
            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-slate-900"><?= __('admin.login.heading') ?></h1>
            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-600">
                <?= __('admin.login.desc') ?>
            </p>
        </section>

        <section class="panel">
            <?php if ($error): ?>
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="" method="post" class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.login.account') ?></label>
                    <input type="text" name="username" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('admin.login.account_placeholder') ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('admin.login.password') ?></label>
                    <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('admin.login.password_placeholder') ?>">
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><?= __('admin.login.submit') ?></button>
            </form>
        </section>
    </div>
</main>

<?php require __DIR__ . '/../../resource/css/footer.php'; ?>
