<?php
require __DIR__ . '/../../resource/js/auth.php';

$token = trim($_GET['token'] ?? '');
$success = false;

if ($token !== '') {
    $success = auth_verify_email($token);
}

$pageTitle = $success ? '验证成功' : '验证失败';
require __DIR__ . '/../../resource/css/header.php';
?>

<main class="page-wrap flex min-h-[calc(100vh-120px)] items-center py-12">
    <section class="panel w-full max-w-xl">
        <h1 class="text-2xl font-semibold text-slate-900"><?= $success ? '邮箱验证成功' : '邮箱验证失败' ?></h1>
        <p class="mt-3 text-sm leading-7 text-slate-600">
            <?= $success ? '你的账号已经完成验证，现在可以返回登录。' : '链接无效或已过期，请重新注册或联系管理员。' ?>
        </p>
        <div class="mt-6">
            <a href="/user/login/" class="btn-primary">返回登录</a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../../resource/css/footer.php'; ?>
