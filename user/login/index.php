<?php
session_start();
if (!file_exists(__DIR__ . '/../../install/install.lock')) {
    header('Location: /install/install.php');
    exit;
}

require __DIR__ . '/../../resource/css/header.php';
require __DIR__ . '/../../resource/js/auth.php';

$pageTitle = '用户登录';
$error = '';
$oauthConfig = auth_config()['oauth'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = '请输入账号和密码。';
    } elseif (auth_login_user($username, $password)) {
        auth_redirect('/user/dashboard/');
    } else {
        $error = '登录失败，请确认账号、密码和邮箱验证状态。';
    }
}
?>
<main class="page-wrap flex min-h-[calc(100vh-120px)] items-center py-12">
    <div class="grid w-full gap-8 lg:grid-cols-2">
        <section class="flex flex-col justify-center">
            <span class="badge-brand w-fit">用户入口</span>
            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-slate-900">登录你的分发账户</h1>
            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-600">
                进入后可查看申请记录、当前绑定域名和通知信息。
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <?php if (!empty($oauthConfig['github']['enabled'])): ?>
                    <a href="/module/oauth/login.php?provider=github&return=/user/dashboard/" class="btn-secondary">GitHub 登录</a>
                <?php endif; ?>
                <?php if (!empty($oauthConfig['google']['enabled'])): ?>
                    <a href="/module/oauth/login.php?provider=google&return=/user/dashboard/" class="btn-secondary">Google 登录</a>
                <?php endif; ?>
                <?php if (!empty($oauthConfig['nodeloc']['enabled'])): ?>
                    <a href="/module/oauth/login.php?provider=nodeloc&return=/user/dashboard/" class="btn-secondary">NodeLoc 登录</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <?php if ($error): ?>
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="" method="post" class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">账号</label>
                    <input type="text" name="username" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="请输入用户名或邮箱">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">密码</label>
                    <input type="password" name="password" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100" placeholder="请输入密码">
                </div>
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-slate-600">
                        <input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-100">
                        记住登录
                    </label>
                    <a href="/user/register/" class="text-brand-700 hover:text-brand-800">没有账号</a>
                </div>
                <button type="submit" class="btn-primary w-full justify-center">登录</button>
            </form>
        </section>
    </div>
</main>
<?php require __DIR__ . '/../../resource/css/footer.php'; ?>
