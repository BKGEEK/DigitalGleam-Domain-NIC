<?php

function mail_template(string $title, string $content): string
{
    return '
        <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #1f2937;">
            <h2 style="margin: 0 0 16px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>
            <div>' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) . '</div>
        </div>
    ';
}

function mail_template_config(): array
{
    $config = require __DIR__ . '/../config/config.php';
    return $config['email_templates'] ?? [];
}

function mail_render_template(string $type, array $variables = []): array
{
    $templates = mail_template_config();
    $subject = $templates[$type . '_subject'] ?? '';
    $body = $templates[$type . '_body'] ?? '';

    $config = require __DIR__ . '/../config/config.php';
    $siteName = $config['app']['name'] ?? '';

    $allowed = [
        '{site_name}' => $siteName,
        '{username}' => $variables['username'] ?? '',
    ];

    $protected = ['{verification_link}'];
    $link = $variables['verification_link'] ?? '';

    foreach ($allowed as $placeholder => $value) {
        $subject = str_replace($placeholder, $value, $subject);
        $body = str_replace($placeholder, $value, $body);
    }

    $body = str_replace('{verification_link}', $link, $body);

    return [
        'subject' => $subject,
        'html' => $body,
    ];
}