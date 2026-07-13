<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';
require_once __DIR__ . '/../../../module/oauth/service.php';

$configPath = __DIR__ . '/../../../config/config.php';
$config = require $configPath;
$oauth = $config['oauth'] ?? [];
$providers = oauth_provider_map();

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['oauth'] ?? [];
    $newOauth = [];

    foreach ($providers as $key => $label) {
        $existing = $oauth[$key] ?? [];
        $providerInput = $input[$key] ?? [];

        $newOauth[$key] = array_merge($existing, $providerInput);
        $newOauth[$key]['enabled'] = !empty($providerInput['enabled']);

        // Keep existing secret if left blank
        foreach (['client_secret'] as $secretField) {
            if (isset($newOauth[$key][$secretField]) && $newOauth[$key][$secretField] === '') {
                $newOauth[$key][$secretField] = $existing[$secretField] ?? '';
            }
        }
    }

    $newConfig = $config;
    $newConfig['oauth'] = $newOauth;

    $export = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    if (file_put_contents($configPath, $export) === false) {
        $error = '配置文件写入失败。';
    } else {
        $config = $newConfig;
        $oauth = $newOauth;
        $message = '第三方登录配置已保存。';
    }
}

admin_dashboard_render('第三方登录', 'oauth', function () use ($oauth, $providers, $error, $message): void {
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">第三方登录配置</h1>
            <p class="mt-3 text-sm text-slate-600">配置 GitHub 和 Google OAuth 凭据。密钥字段留空表示保留原值。</p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-8">
                <?php foreach ($providers as $key => $label):
                    $providerConfig = $oauth[$key] ?? [];
                    $enabled = !empty($providerConfig['enabled']);
                    $autoCallback = oauth_callback_url($key);
                ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900"><?= htmlspecialchars($label) ?></h2>
                            <p class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($key) ?></p>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="hidden" name="oauth[<?= $key ?>][enabled]" value="0">
                            <input type="checkbox" name="oauth[<?= $key ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?> class="peer sr-only">
                            <div class="h-6 w-11 rounded-full bg-slate-300 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-brand-500 peer-checked:after:translate-x-full"></div>
                            <span class="ml-3 text-sm text-slate-600"><?= $enabled ? '已启用' : '已停用' ?></span>
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="oauth-<?= $key ?>-client_id" class="mb-2 block text-sm font-medium text-slate-700">Client ID</label>
                            <input type="text" id="oauth-<?= $key ?>-client_id" name="oauth[<?= $key ?>][client_id]" value="<?= htmlspecialchars((string) ($providerConfig['client_id'] ?? '')) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div>
                            <label for="oauth-<?= $key ?>-client_secret" class="mb-2 block text-sm font-medium text-slate-700">Client Secret</label>
                            <input type="password" id="oauth-<?= $key ?>-client_secret" name="oauth[<?= $key ?>][client_secret]" value="<?= htmlspecialchars((string) ($providerConfig['client_secret'] ?? '')) ?>" placeholder="留空则保持原值" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </div>
                        <div class="md:col-span-2">
                            <label for="oauth-<?= $key ?>-redirect_uri" class="mb-2 block text-sm font-medium text-slate-700">回调地址（Redirect URI）</label>
                            <input type="url" id="oauth-<?= $key ?>-redirect_uri" name="oauth[<?= $key ?>][redirect_uri]" value="<?= htmlspecialchars((string) ($providerConfig['redirect_uri'] ?? '')) ?>" placeholder="留空则使用自动生成地址" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                            <p class="mt-1.5 text-xs text-slate-400">留空时系统自动使用：<code class="rounded bg-slate-50 px-1.5 py-0.5"><?= htmlspecialchars($autoCallback) ?></code></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-primary">保存配置</button>
            </form>
        </section>

        <section class="panel self-start">
            <h2 class="text-xl font-semibold text-slate-900">说明</h2>
            <ul class="mt-4 space-y-4 text-sm text-slate-600">
                <li>
                    <strong class="text-slate-800">GitHub OAuth App</strong><br>
                    在 <a href="https://github.com/settings/developers" target="_blank" class="text-brand-600 hover:text-brand-700">GitHub Developer Settings</a> 创建 OAuth App，Authorization callback URL 填写右侧的回调地址。
                </li>
                <li>
                    <strong class="text-slate-800">Google OAuth Client</strong><br>
                    在 <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-brand-600 hover:text-brand-700">Google Cloud Console</a> 创建 OAuth 2.0 客户端 ID，授权重定向 URI 填写右侧的回调地址。
                </li>
                <li>
                    <strong class="text-slate-800">NodeLoc OAuth App</strong><br>
                    在 <a href="https://www.nodeloc.com/oauth-provider/applications" target="_blank" class="text-brand-600 hover:text-brand-700">NodeLoc OAuth 应用管理</a> 创建应用，Redirect URI 填写右侧的回调地址。如需获取用户邮箱，需要在 NodeLoc 申请审核。
                </li>
                <li>
                    <strong class="text-slate-800">启用条件</strong><br>
                    需同时填写 Client ID 和 Client Secret 并启用开关，前端才会显示对应的第三方登录按钮。
                </li>
                <li>
                    <strong class="text-slate-800">密钥保护</strong><br>
                    Client Secret 留空会自动保留原有值，避免每次保存时覆盖。
                </li>
            </ul>
        </section>
    </div>
    <?php
});