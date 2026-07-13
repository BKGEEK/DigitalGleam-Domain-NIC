# 更新日志

## 2026-07-13

### 新增功能
- **NodeLoc OAuth 登录**：新增 NodeLoc 第三方登录支持，可在后台配置启用
- **邮件模板管理**：后台新增邮件模板管理页面，支持自定义注册验证邮件的主题和正文（`{verification_link}` 为系统保护字段，不可自定义内容）
- **SMTP 测试邮件**：保存 SMTP 配置后可发送测试邮件验证配置是否正确
- **TXT 记录管理**：用户可为每个子域名添加最多 3 条 TXT 记录，自动同步到 DNS 服务商
- **注册自动审核**：域名申请提交后自动通过，无需管理员手动审核

### 功能优化
- 邮箱注册限制：仅允许 `gmail.com`、`qq.com`、`163.com`、`outlook.com` 域名，不支持 `+` 和 `.` 别名邮箱
- `index.php` 和 `whois/index.php` 检索区域居中显示

### Bug 修复
- **验证链接不完整**：`base_url` 为空时自动从服务器检测当前地址，避免链接缺少域名
- **多级子域名错误申请**：（如`test1.test2.example.com`）的错误申请，改为只支持二级域名申请
- **Apache规则文件修复**：`.htaccess` 改为允许 `module/oauth/` 路径访问，解决 OAuth 回调 403 问题

### 数据库变更
```sql
-- txt_records 表（如不存在需手动创建）
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
```