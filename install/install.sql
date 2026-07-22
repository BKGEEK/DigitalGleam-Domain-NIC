CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nickname` VARCHAR(80) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_username` (`username`),
  UNIQUE KEY `uk_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员账户表';

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nickname` VARCHAR(80) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `whois_public` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1公开 0不公开',
  `whois_name` VARCHAR(80) DEFAULT NULL,
  `whois_phone` VARCHAR(30) DEFAULT NULL,
  `whois_email` VARCHAR(120) DEFAULT NULL,
  `whois_company` VARCHAR(120) DEFAULT NULL,
  `whois_address` VARCHAR(255) DEFAULT NULL,
  `whois_id_number` VARCHAR(50) DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  `email_verified_at` DATETIME DEFAULT NULL,
  `email_verify_token` VARCHAR(64) DEFAULT NULL,
  `email_verify_expires_at` DATETIME DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_username` (`username`),
  UNIQUE KEY `uk_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='普通用户表';

CREATE TABLE IF NOT EXISTS `oauth_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(50) NOT NULL,
  `provider_user_id` VARCHAR(191) NOT NULL,
  `provider_email` VARCHAR(120) DEFAULT NULL,
  `provider_name` VARCHAR(120) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_oauth_provider_user` (`provider`, `provider_user_id`),
  KEY `idx_oauth_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 账号绑定表';

CREATE TABLE IF NOT EXISTS `root_domains` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `root_domain` VARCHAR(191) NOT NULL COMMENT '主域名，例如 example.com',
  `provider` VARCHAR(50) NOT NULL DEFAULT 'manual' COMMENT 'alidns/cloudflare/dnsla/dnspod/powerdns/manual',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1正常 0停用',
  `remark` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_root_domain` (`root_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主域名表';

CREATE TABLE IF NOT EXISTS `domains` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `root_domain_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 root_domains.id',
  `subdomain` VARCHAR(100) NOT NULL COMMENT '子域名前缀，例如 api',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1空闲 2使用中 3审核中 0停用',
  `assigned_to` BIGINT UNSIGNED DEFAULT NULL COMMENT '分配给 users.id',
  `expires_at` DATETIME DEFAULT NULL COMMENT '过期时间，NULL 表示永久有效',
  `remark` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain_name` (`root_domain_id`, `subdomain`),
  KEY `idx_domain_root_domain_id` (`root_domain_id`),
  KEY `idx_domain_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='域名池表';

CREATE TABLE IF NOT EXISTS `domain_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `domain_id` BIGINT UNSIGNED DEFAULT NULL,
  `requested_domain` VARCHAR(191) NOT NULL,
  `purpose` VARCHAR(255) DEFAULT NULL,
  `remark` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1待审核 2已通过 3已拒绝 4已撤销',
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核管理员ID',
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_user_id` (`user_id`),
  KEY `idx_request_domain_id` (`domain_id`),
  KEY `idx_request_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='域名申请表';

CREATE TABLE IF NOT EXISTS `ns_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 domains.id',
  `nameserver` VARCHAR(255) NOT NULL COMMENT 'NS 记录值',
  `sort_order` INT NOT NULL DEFAULT 0,
  `provider_record_id` VARCHAR(255) DEFAULT NULL COMMENT 'DNS 服务商记录 ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ns_domain_id` (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NS 记录表';

CREATE TABLE IF NOT EXISTS `txt_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 domains.id',
  `value` VARCHAR(255) NOT NULL COMMENT 'TXT 记录值',
  `provider_record_id` VARCHAR(255) DEFAULT NULL COMMENT 'DNS 服务商记录 ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_txt_domain_id` (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TXT 记录表';

CREATE TABLE IF NOT EXISTS `dns_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain_id` BIGINT UNSIGNED NOT NULL COMMENT '关联 domains.id',
  `type` VARCHAR(10) NOT NULL COMMENT 'A / AAAA / CNAME',
  `name` VARCHAR(255) NOT NULL DEFAULT '@' COMMENT '记录名称',
  `value` VARCHAR(255) NOT NULL COMMENT '记录值',
  `proxied` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Cloudflare CDN 代理开关',
  `provider_record_id` VARCHAR(255) DEFAULT NULL COMMENT 'DNS 服务商记录 ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dns_record_domain_id` (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DNS 记录表（A / AAAA / CNAME）';

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(120) NOT NULL,
  `content` TEXT NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1显示 0隐藏',
  `published_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '发布管理员ID',
  `published_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_announcement_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统公告表';

CREATE TABLE IF NOT EXISTS `settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `remark` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

INSERT INTO `settings` (`setting_key`, `setting_value`, `remark`) VALUES
('site_name', '数星二级域名分发', '站点名称'),
('site_status', '1', '站点开关'),
('site_notice', '欢迎使用数星二级域名分发系统。', '首页公告')
ON DUPLICATE KEY UPDATE
  `setting_value` = VALUES(`setting_value`),
  `remark` = VALUES(`remark`);

INSERT INTO `announcements` (`title`, `content`, `status`, `published_at`) VALUES
('欢迎使用', '系统已完成基础初始化，可以开始接入登录、申请和域名管理功能。', 1, NOW());
