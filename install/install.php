<?php
session_start();

$configPath = __DIR__ . '/../config/config.php';
$lockPath = __DIR__ . '/install.lock';
$sqlPath = __DIR__ . '/install.sql';

if (file_exists($lockPath)) {
    die('系统已安装，请删除 install 目录或锁文件后再操作。');
}

$message = '';
$error = '';

$defaults = [
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => 'root',
    'db_pass' => '',
    'admin_user' => 'admin',
    'admin_pass' => '',
    'admin_email' => 'admin@example.com',
];

$form = $defaults;

function install_parse_sql_file(string $path): array
{
    $sql = file_get_contents($path);
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    $result = [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $result[] = $statement;
        }
    }

    return $result;
}

function install_write_config(array $db): bool
{
    global $configPath;

    $config = [
        'app' => [
            'name' => '数星二级域名分发',
            'timezone' => 'Asia/Shanghai',
            'debug' => false,
            'base_url' => '',
        ],
        'db' => [
            'driver' => 'mysql',
            'host' => $db['host'],
            'port' => (int) $db['port'],
            'database' => $db['name'],
            'username' => $db['user'],
            'password' => $db['pass'],
            'charset' => 'utf8mb4',
            'prefix' => '',
        ],
    ];

    $export = "<?php\nreturn " . var_export($config, true) . ";\n";
    return file_put_contents($configPath, $export) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? $defaults['db_host']);
    $dbPort = (int) ($_POST['db_port'] ?? $defaults['db_port']);
    $dbName = trim($_POST['db_name'] ?? $defaults['db_name']);
    $dbUser = trim($_POST['db_user'] ?? $defaults['db_user']);
    $dbPass = (string) ($_POST['db_pass'] ?? $defaults['db_pass']);
    $adminUser = trim($_POST['admin_user'] ?? $defaults['admin_user']);
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? $defaults['admin_email']);

    $form = [
        'db_host' => $dbHost,
        'db_port' => (string) $dbPort,
        'db_name' => $dbName,
        'db_user' => $dbUser,
        'db_pass' => $dbPass,
        'admin_user' => $adminUser,
        'admin_pass' => $adminPass,
        'admin_email' => $adminEmail,
    ];

    if ($dbHost === '' || $dbName === '' || $dbUser === '' || $adminUser === '' || $adminPass === '' || $adminEmail === '') {
        $error = '请完整填写数据库和管理员信息。';
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = '管理员邮箱格式不正确。';
    } else {
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            foreach (install_parse_sql_file($sqlPath) as $statement) {
                $pdo->exec($statement);
            }

            $stmt = $pdo->prepare('INSERT INTO admin_users (username, password, email, status) VALUES (:username, :password, :email, 1) ON DUPLICATE KEY UPDATE password = VALUES(password), email = VALUES(email), status = VALUES(status)');
            $stmt->execute([
                ':username' => $adminUser,
                ':password' => password_hash($adminPass, PASSWORD_DEFAULT),
                ':email' => $adminEmail,
            ]);

            if (!install_write_config([
                'host' => $dbHost,
                'port' => $dbPort,
                'name' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass,
            ])) {
                throw new RuntimeException('配置文件写入失败。');
            }

            file_put_contents($lockPath, date('Y-m-d H:i:s'));
            $message = '安装完成，可以删除 install 目录或保留锁文件。';
        } catch (Throwable $e) {
            $error = '安装失败：' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eefbf7',
                            100: '#d7f6eb',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
    <div class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-12">
        <div class="w-full rounded-3xl border border-slate-200 bg-white p-8 shadow-lg">
            <h1 class="text-2xl font-semibold text-slate-900">数星二级域名分发安装</h1>
            <p class="mt-2 text-sm text-slate-600">填写数据库信息和管理员账号，点击后完成初始化。</p>

            <?php if ($error): ?>
                <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="mt-5 rounded-2xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">数据库主机</label>
                    <input name="db_host" value="<?= htmlspecialchars($form['db_host']) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">端口</label>
                    <input name="db_port" value="<?= htmlspecialchars($form['db_port']) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">数据库名</label>
                    <input name="db_name" value="<?= htmlspecialchars($form['db_name']) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">数据库用户</label>
                    <input name="db_user" value="<?= htmlspecialchars($form['db_user']) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-slate-700">数据库密码</label>
                    <input type="password" name="db_pass" value="<?= htmlspecialchars($form['db_pass']) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">管理员用户名</label>
                    <input name="admin_user" value="<?= htmlspecialchars($form['admin_user']) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">管理员邮箱</label>
                    <input type="email" name="admin_email" value="<?= htmlspecialchars($form['admin_email']) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-slate-700">管理员密码</label>
                    <input type="password" name="admin_pass" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex items-center rounded-full bg-brand-600 px-5 py-3 text-sm font-medium text-white hover:bg-brand-700">开始安装</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
