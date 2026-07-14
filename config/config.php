<?php
return array (
  'app' => 
  array (
    'name' => '数星二级域名分发',
    'timezone' => 'Asia/Shanghai',
    'debug' => false,
    'base_url' => '',
  ),
  'db' => 
  array (
    'driver' => 'mysql',
    'host' => ' ',
    'port' => 3306,
    'database' => ' ',
    'username' => ' ',
    'password' => ' ',
    'charset' => 'utf8mb4',
    'prefix' => '',
  ),
  'site' => 
  array (
    'status' => 1,
    'notice' => '欢迎使用数星二级域名分发系统。',
    'email_verify' => false,
  ),
  'domain' => 
  array (
    'min_length' => 3,
    'max_length' => 24,
    'allow_unicode' => false,
    'auto_approve' => true,
    'max_domains_per_user' => 3,
    'max_ns_records' => 5,
    'max_txt_records' => 3,
    'enable_ns_records' => true,
    'enable_txt_records' => true,
    'max_a_records' => 10,
    'max_aaaa_records' => 10,
    'max_cname_records' => 10,
    'enable_a_records' => true,
    'enable_aaaa_records' => true,
    'enable_cname_records' => true,
  ),
  'email_templates' => 
  array (
    'register_subject' => '{site_name} - 邮箱验证',
    'register_body' => '<div style="font-family: Arial, sans-serif; line-height: 1.7; color: #1f2937; max-width: 600px; margin: 0 auto;">
  <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 32px; text-align: center; border-radius: 12px 12px 0 0;">
    <h1 style="margin: 0; font-size: 24px; color: #ffffff;">{site_name}</h1>
  </div>
  <div style="padding: 32px; background: #ffffff; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 12px 12px;">
    <p style="margin: 0 0 16px; font-size: 15px;">你好，</p>
    <p style="margin: 0 0 16px; font-size: 15px;">感谢你注册 {site_name}！请点击下方按钮完成邮箱验证：</p>
    <div style="text-align: center; margin: 24px 0;">
      <a href="{verification_link}" style="display: inline-block; padding: 12px 32px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600;">验证邮箱</a>
    </div>
    <p style="margin: 0 0 16px; font-size: 15px;">如果按钮无法点击，请复制以下链接到浏览器打开：</p>
    <p style="margin: 0 0 16px; padding: 12px; background: #f3f4f6; border-radius: 8px; font-size: 13px; word-break: break-all; font-family: monospace;">{verification_link}</p>
    <p style="margin: 0 0 16px; font-size: 13px; color: #6b7280;">该链接 24 小时内有效。如果你没有注册，请忽略此邮件。</p>
    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">
    <p style="margin: 0; font-size: 12px; color: #9ca3af;">此邮件由系统自动发送，请勿回复。</p>
  </div>
</div>',
  ),
  'smtp' => 
  array (
    'enabled' => true,
    'host' => ' ',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => ' ',
    'password' => ' ',
    'from_email' => ' ',
    'from_name' => ' ',
    'reply_to' => ' ',
  ),
  'dns' => 
  array (
    'manual' => 
    array (
      'enabled' => false,
    ),
    'alidns' => 
    array (
      'enabled' => false,
      'access_key_id' => '',
      'access_key_secret' => '',
      'endpoint' => 'alidns.cn-hangzhou.aliyuncs.com',
    ),
    'cloudflare' => 
    array (
      'enabled' => false,
      'api_token' => '',
      'account_id' => '',
    ),
    'dnspod' => 
    array (
      'enabled' => false,
      'secret_id' => '',
      'secret_key' => '',
    ),
    'powerdns' => 
    array (
      'enabled' => false,
      'api_key' => '',
      'server_url' => '',
    ),
  ),
  'oauth' => 
  array (
    'github' => 
    array (
      'enabled' => false,
      'client_id' => '',
      'client_secret' => ' ',
      'redirect_uri' => '',
      'scope' => 'read:user user:email',
      'authorize_url' => 'https://github.com/login/oauth/authorize',
      'token_url' => 'https://github.com/login/oauth/access_token',
      'user_url' => 'https://api.github.com/user',
      'email_url' => 'https://api.github.com/user/emails',
      'user_agent' => 'DomainDistributionOAuth',
    ),
    'google' => 
    array (
      'enabled' => false,
      'client_id' => '',
      'client_secret' => '',
      'redirect_uri' => '',
      'scope' => 'openid email profile',
      'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
      'token_url' => 'https://oauth2.googleapis.com/token',
      'user_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
      'user_agent' => 'DomainDistributionOAuth',
    ),
    'nodeloc' => 
    array (
      'enabled' => false,
      'client_id' => '',
      'client_secret' => ' ',
      'redirect_uri' => '',
      'scope' => 'openid profile email',
      'authorize_url' => 'https://www.nodeloc.com/oauth-provider/authorize',
      'token_url' => 'https://www.nodeloc.com/oauth-provider/token',
      'user_url' => 'https://www.nodeloc.com/oauth-provider/userinfo',
      'user_agent' => 'DomainDistributionOAuth',
    ),
  ),
);
