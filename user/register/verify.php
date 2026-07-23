<?php
require __DIR__ . '/../../lang/helper.php';
require __DIR__ . '/../../resource/js/auth.php';

$token = trim($_GET['token'] ?? '');
$success = false;

if ($token !== '') {
    $success = auth_verify_email($token);
}

$pageTitle = $success ? __('user.verify.success_title') : __('user.verify.failed_title');
require __DIR__ . '/../../resource/css/header.php';
?>

<main class="page-wrap flex min-h-[calc(100vh-120px)] items-center py-12">
    <section class="panel w-full max-w-xl">
        <h1 class="text-2xl font-semibold text-slate-900"><?= $success ? __('user.verify.success_heading') : __('user.verify.failed_heading') ?></h1>
        <p class="mt-3 text-sm leading-7 text-slate-600">
            <?= $success ? __('user.verify.success_desc') : __('user.verify.failed_desc') ?>
        </p>
        <div class="mt-6">
            <a href="/user/login/" class="btn-primary"><?= __('user.verify.back_login') ?></a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../../resource/css/footer.php'; ?>
