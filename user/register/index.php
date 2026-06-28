<?php
session_start();

$pageTitle = '用户注册';
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
        $error = '请完整填写注册信息。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确。';
    } elseif ($password !== $passwordConfirm) {
        $error = '两次输入的密码不一致。';
    } elseif (auth_user_exists($username, $email)) {
        $error = '用户名或邮箱已存在。';
    } else {
        $result = auth_register_user($username, $email, $password);
        $verifyLink = rtrim((auth_config()['app']['base_url'] ?? ''), '/') . '/user/register/verify.php?token=' . urlencode($result['token']);
        $subject = '数星二级域名分发邮箱验证';
        $html = mail_template(
            '邮箱验证',
            "你的注册验证码链接如下：\n{$verifyLink}\n\n该链接 24 小时内有效。"
        );
        mail_send($email, $subject, $html);
        $message = '注册成功，请前往邮箱完成验证后再登录。';
    }
}
?>

<main class="page-wrap flex min-h-[calc(100vh-120px)] items-center py-12">
    <div class="grid w-full gap-8 lg:grid-cols-2">
        <section class="flex flex-col justify-center">
            <span class="badge-brand w-fit">创建账户</span>
            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-slate-900">注册分发系统账号</h1>
            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-600">
                注册后可提交二级域名申请、查看审核状态和接收系统通知。
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
                    <label class="mb-2 block text-sm font-medium text-slate-700">用户名</label>
                    <input type="text" name="username" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="请输入用户名">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">邮箱</label>
                    <input type="email" name="email" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="请输入邮箱">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">密码</label>
                    <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="请输入密码">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">确认密码</label>
                    <input type="password" name="password_confirm" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="再次输入密码">
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">已有账号</span>
                    <a href="/user/login/" class="text-brand-700 hover:text-brand-800">去登录</a>
                </div>
                <button type="submit" class="btn-primary w-full justify-center">注册</button>
            </form>
        </section>
    </div>
</main>

<?php require __DIR__ . '/../../resource/css/footer.php'; ?>
