<?php
require __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../../resource/js/auth.php';
require_once __DIR__ . '/../../../module/dns/service.php';

$configPath = __DIR__ . '/../../../config/config.php';
$config = require $configPath;
$dns = $config['dns'] ?? [];
$providers = dns_provider_map();

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['dns'] ?? [];
    $newDns = [];

    foreach ($providers as $key => $label) {
        $existing = $dns[$key] ?? [];
        $providerInput = $input[$key] ?? [];

        $newDns[$key] = array_merge($existing, $providerInput);
        $newDns[$key]['enabled'] = !empty($providerInput['enabled']);

        $secretFields = ['access_key_secret', 'api_token', 'api_secret', 'secret_key', 'api_key', 'password'];
        foreach ($secretFields as $secretField) {
            if (isset($newDns[$key][$secretField]) && $newDns[$key][$secretField] === '') {
                $newDns[$key][$secretField] = $existing[$secretField] ?? '';
            }
        }
    }

    $newConfig = $config;
    $newConfig['dns'] = $newDns;

    $export = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    if (file_put_contents($configPath, $export) === false) {
        $error = '配置文件写入失败。';
    } else {
        $config = $newConfig;
        $dns = $newDns;
        $message = 'DNS 接口配置已保存。';
    }
}

function dns_config_field(string $provider, string $field, string $label, mixed $value, string $type = 'text'): void
{
    $name = 'dns[' . $provider . '][' . $field . ']';
    $id = 'dns-' . $provider . '-' . $field;
    ?>
    <div>
        <label for="<?= $id ?>" class="mb-2 block text-sm font-medium text-slate-700"><?= htmlspecialchars($label) ?></label>
        <?php if ($type === 'password'): ?>
            <input type="password" id="<?= $id ?>" name="<?= $name ?>" value="<?= htmlspecialchars((string) $value) ?>" placeholder="留空则保持原值" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
        <?php else: ?>
            <input type="<?= htmlspecialchars($type) ?>" id="<?= $id ?>" name="<?= $name ?>" value="<?= htmlspecialchars((string) $value) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
        <?php endif; ?>
    </div>
    <?php
}

admin_dashboard_render('DNS 接口', 'dns', function () use ($dns, $providers, $error, $message): void {
    $providerFields = [
        'manual' => [],
        'alidns' => [
            ['access_key_id', 'AccessKey ID', 'text'],
            ['access_key_secret', 'AccessKey Secret', 'password'],
            ['endpoint', 'Endpoint', 'text'],
        ],
        'cloudflare' => [
            ['api_token', 'API Token', 'password'],
            ['account_id', 'Account ID', 'text'],
        ],
        'dnspod' => [
            ['secret_id', 'Secret ID', 'text'],
            ['secret_key', 'Secret Key', 'password'],
        ],
        'powerdns' => [
            ['api_key', 'API Key', 'password'],
            ['server_url', 'Server URL', 'text'],
        ],
    ];
    ?>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="panel">
            <h1 class="text-2xl font-semibold text-slate-900">DNS 接口配置</h1>
            <p class="mt-3 text-sm text-slate-600">配置各 DNS 供应商的凭据和启用状态。密码/密钥字段留空表示保留原值。</p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 space-y-8">
                <?php foreach ($providers as $key => $label):
                    $providerConfig = $dns[$key] ?? [];
                    $enabled = !empty($providerConfig['enabled']);
                    $fields = $providerFields[$key] ?? [];
                ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900"><?= htmlspecialchars($label) ?></h2>
                            <p class="mt-0.5 text-xs text-slate-500"><?= htmlspecialchars($key) ?></p>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="hidden" name="dns[<?= $key ?>][enabled]" value="0">
                            <input type="checkbox" name="dns[<?= $key ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?> class="peer sr-only">
                            <div class="h-6 w-11 rounded-full bg-slate-300 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-brand-500 peer-checked:after:translate-x-full"></div>
                            <span class="ml-3 text-sm text-slate-600"><?= $enabled ? '已启用' : '已停用' ?></span>
                        </label>
                    </div>

                    <?php if (!empty($fields)): ?>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <?php foreach ($fields as [$field, $fieldLabel, $fieldType]):
                            $value = $providerConfig[$field] ?? '';
                            dns_config_field($key, $field, $fieldLabel, $value, $fieldType);
                        endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-primary">保存配置</button>
            </form>
        </section>

        <section class="panel self-start">
            <h2 class="text-xl font-semibold text-slate-900">说明</h2>
            <ul class="mt-4 space-y-4 text-sm text-slate-600">
                    <strong class="text-slate-800">手动管理</strong><br>
                    不需要 API 凭据，由管理员手动添加解析记录。
                </li>
                <li>
                    <strong class="text-slate-800">阿里云 DNS</strong><br>
                    RAM 用户需授予 <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">AliyunDNSFullAccess</code> 权限。
                </li>
                <li>
                    <strong class="text-slate-800">Cloudflare</strong><br>
                    使用 API Token（推荐）或 Global API Key，需 <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">Zone:DNS:Edit</code> 权限。
                </li>
                <li>
                    <strong class="text-slate-800">DNSPod</strong><br>
                    在 DNSPod 控制台创建 API 密钥（SecretId / SecretKey）。
                </li>
                <li>
                    <strong class="text-slate-800">PowerDNS</strong><br>
                    需启用 API 并配置 API Key，Server URL 为 API 端点地址。
                </li>
                <li>
                    <strong class="text-slate-800">密码字段</strong><br>
                    密钥/密码类字段留空会自动保留原有值，避免每次保存时覆盖。
                </li>
            </ul>
        </section>
    </div>
    <?php
});
