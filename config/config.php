<?php
return array (
  'app' => 
  array (
    'name' => '数星集团荣誉制作',
    'timezone' => 'Asia/Shanghai',
    'debug' => false,
    'base_url' => '',
  ),
  'db' => 
  array (
    'driver' => 'mysql',
    'host' => '1.1.1.1',
    'port' => 3306,
    'database' => 'DigitalGleam',
    'username' => 'DigitalGleam',
    'password' => 'DigitalGleam',
    'charset' => 'utf8mb4',
    'prefix' => '',
  ),
  'site' => 
  array (
    'status' => 1,
    'notice' => '欢迎使用数星二级域名分发系统。',
    'email_verify' => false,
  ),
  'smtp' => 
  array (
    'enabled' => true,
    'host' => 'digitalgleam.orgv.eu',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => 'no-reply@digitalgleam.orgv.eu',
    'password' => '',
    'from_email' => 'no-reply@digitalgleam.orgv.eu',
    'from_name' => '测试-域名NIC',
    'reply_to' => 'no-reply2@digitalgleam.orgv.eu',
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
      'enabled' => true,
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
      'enabled' => true,
      'client_id' => 'digitalgleam',
      'client_secret' => 'orgveu',
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
      'enabled' => true,
      'client_id' => 'digitalgleam',
      'client_secret' => 'orgveu',
      'redirect_uri' => '',
      'scope' => 'openid email profile',
      'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
      'token_url' => 'https://oauth2.googleapis.com/token',
      'user_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
      'user_agent' => 'DomainDistributionOAuth',
    ),
  ),
);
