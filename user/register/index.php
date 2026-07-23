<?php
session_start();

$pageTitle = __('user.register.title');
require __DIR__ . '/../../resource/css/header.php';
require __DIR__ . '/../../resource/js/auth.php';
require __DIR__ . '/../../mail/mailer.php';
require __DIR__ . '/../../mail/template.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($username === '' || $email === '' || $password === '') {
        $error = __('user.register.error_empty');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('user.register.error_email');
    } elseif (!auth_validate_email_domain($email)) {
        $error = __('user.register.error_email_domain');
    } elseif ($password !== $passwordConfirm) {
        $error = __('user.register.error_password_mismatch');
    } elseif (auth_user_exists($username, $email)) {
        $error = __('user.register.error_exists');
    } else {
        $result = auth_register_user($username, $email, $password);
        $baseUrl = auth_config()['app']['base_url'] ?? '';
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        $verifyLink = rtrim($baseUrl, '/') . '/user/register/verify.php?token=' . urlencode($result['token']);
        $rendered = mail_render_template('register', [
            'username' => $username,
            'verification_link' => $verifyLink,
        ]);
        $html = $rendered['html'];
        $subject = $rendered['subject'];
        mail_send($email, $subject, $html);
        $message = __('user.register.success');
    }
}
?>

<main class="page-wrap flex min-h-[calc(100vh-120px)] items-center py-12">
    <div class="grid w-full gap-8 lg:grid-cols-2">
        <section class="flex flex-col justify-center">
            <span class="badge-brand w-fit"><?= __('user.register.badge') ?></span>
            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-slate-900"><?= __('user.register.heading') ?></h1>
            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-600">
                <?= __('user.register.desc') ?>
            </p>
        </section>

        <section class="panel">
            <?php if ($error): ?>
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mb-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form action="" method="post" class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('user.register.username') ?></label>
                    <input type="text" name="username" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('user.register.username_placeholder') ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('user.register.email') ?></label>
                    <input type="email" name="email" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('user.register.email_placeholder') ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('user.register.password') ?></label>
                    <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('user.register.password_placeholder') ?>">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700"><?= __('user.register.confirm_password') ?></label>
                    <input type="password" name="password_confirm" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="<?= __('user.register.confirm_placeholder') ?>">
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500"><?= __('user.register.has_account') ?></span>
                    <a href="/user/login/" class="text-brand-700 hover:text-brand-800"><?= __('user.register.go_login') ?></a>
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><?= __('user.register.submit') ?></button>
            </form>
        </section>
    </div>
</main>

<?php require __DIR__ . '/../../resource/css/footer.php'; ?>
