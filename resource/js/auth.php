<?php

function auth_config(): array
{
    $config = require __DIR__ . '/../../config/config.php';
    return $config;
}

function auth_db(): PDO
{
    if (!file_exists(__DIR__ . '/../../install/install.lock')) {
        header('Location: /install/install.php');
        exit;
    }
    return require __DIR__ . '/../../config/sql/connection.php';
}

function auth_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function auth_token(int $length = 32): string
{
    return bin2hex(random_bytes((int) ceil($length / 2)));
}

function auth_user_exists(string $username, string $email): bool
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
    ]);

    return (bool) $stmt->fetchColumn();
}

function auth_user_by_username(string $username): ?array
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function auth_user_by_id(int $id): ?array
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function auth_admin_by_username(string $username): ?array
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}

function auth_user_by_token(string $token): ?array
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email_verify_token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function auth_user_has_whois(array $user): bool
{
    return !empty($user['whois_name']) && !empty($user['whois_phone']) && !empty($user['whois_email']);
}

function auth_allowed_email_domains(): array
{
    return ['gmail.com', 'qq.com', '163.com', 'outlook.com'];
}

function auth_validate_email_domain(string $email): bool
{
    $local = strstr($email, '@', true);
    if ($local !== false && (str_contains($local, '+') || str_contains($local, '.'))) {
        return false;
    }
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    return in_array($domain, auth_allowed_email_domains(), true);
}

function auth_user_whois_public(array $user): bool
{
    return !empty($user['whois_public']);
}

function auth_update_user_profile(int $userId, array $data): bool
{
    $fields = [];
    $params = [':id' => $userId];

    foreach (['nickname', 'email', 'phone', 'whois_public', 'whois_name', 'whois_phone', 'whois_email', 'whois_company', 'whois_address', 'whois_id_number'] as $key) {
        if (array_key_exists($key, $data)) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $data[$key];
        }
    }

    if (!$fields) {
        return false;
    }

    $pdo = auth_db();
    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
    $stmt = $pdo->prepare($sql);

    return $stmt->execute($params);
}

function auth_reset_email_verification(int $userId, string $email): array
{
    $pdo = auth_db();
    $token = auth_token(64);
    $expiresAt = date('Y-m-d H:i:s', time() + 24 * 3600);

    $stmt = $pdo->prepare(
        'UPDATE users
         SET email = :email,
             email_verified_at = NULL,
             email_verify_token = :token,
             email_verify_expires_at = :expires_at,
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $userId,
        ':email' => $email,
        ':token' => $token,
        ':expires_at' => $expiresAt,
    ]);

    return [
        'token' => $token,
        'expires_at' => $expiresAt,
    ];
}

function auth_user_requests_count(int $userId): int
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM domain_requests WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}

function auth_user_domains_count(int $userId): int
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM domains WHERE assigned_to = :user_id');
    $stmt->execute([':user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}

function auth_register_user(string $username, string $email, string $password): array
{
    $pdo = auth_db();
    $token = auth_token(64);
    $expiresAt = date('Y-m-d H:i:s', time() + 24 * 3600);

    $stmt = $pdo->prepare('INSERT INTO users (username, password, email, status, email_verify_token, email_verify_expires_at) VALUES (:username, :password, :email, 1, :token, :expires_at)');
    $stmt->execute([
        ':username' => $username,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':email' => $email,
        ':token' => $token,
        ':expires_at' => $expiresAt,
    ]);

    return [
        'user_id' => (int) $pdo->lastInsertId(),
        'token' => $token,
        'expires_at' => $expiresAt,
    ];
}

function auth_verify_email(string $token): bool
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT id, email_verify_expires_at FROM users WHERE email_verify_token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    if (!empty($user['email_verify_expires_at']) && strtotime($user['email_verify_expires_at']) < time()) {
        return false;
    }

    $update = $pdo->prepare('UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL, email_verify_expires_at = NULL WHERE id = :id');
    return $update->execute([':id' => $user['id']]);
}

function auth_login_user(string $username, string $password): bool
{
    $user = auth_user_by_username($username);
    if (!$user) {
        return false;
    }

    if ((int) $user['status'] !== 1) {
        return false;
    }

    if (empty($user['email_verified_at'])) {
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        return false;
    }

    $pdo = auth_db();
    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['username'];

    return true;
}

function auth_login_admin(string $username, string $password): bool
{
    $admin = auth_admin_by_username($username);
    if (!$admin) {
        return false;
    }

    if ((int) $admin['status'] !== 1) {
        return false;
    }

    if (!password_verify($password, $admin['password'])) {
        return false;
    }

    $pdo = auth_db();
    $stmt = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $admin['id']]);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_name'] = $admin['username'];

    return true;
}
