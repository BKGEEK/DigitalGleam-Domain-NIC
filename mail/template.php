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
