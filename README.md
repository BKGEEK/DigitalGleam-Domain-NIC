# 数星二级域名分发

基于 PHP + MySQL + Tailwind 的极为轻量化审核制二级域名分发管理系统，支持用户自助申请、管理员审核分配、DNS 服务商自动同步。

## 功能

- **用户端**：域名检索与申请、我的域名管理、NS 记录自定义（最多 5 条）、个人资料与 WHOIS
- **管理端**：根域名管理、子域名池管理、申请审核/撤销/回收、用户管理、公告管理、DNS 服务商配置
- **DNS 服务商**：支持 Cloudflare、阿里云 DNS、DNSPod、PowerDNS，添加 NS 记录时自动同步

## 目录结构

```
├── index.php                  # 首页：域名前缀检索
├── config/
│   ├── config.php             # 系统配置（安装后生成）
│   └── sql/connection.php     # PDO 连接
├── install/
│   ├── install.php            # 安装向导
│   ├── install.sql            # 数据库建表 SQL
│   └── install.lock           # 安装锁
├── admin/                     # 管理后台
│   └── dashboard/
│       ├── index.php          # 概览
│       ├── root-domains/      # 根域名管理
│       ├── domains/           # 子域名池
│       ├── requests/          # 申请审核
│       ├── users/             # 用户管理
│       ├── announcements/     # 公告管理
│       ├── settings/          # 站点设置
│       ├── smtp/              # SMTP 配置
│       ├── dns/               # DNS 凭据配置
│       └── oauth/             # OAuth 配置
├── user/                      # 用户中心
│   ├── login/                 # 登录
│   ├── register/              # 注册
│   ├── dashboard/             # 概览
│   ├── domains/               # 我的域名 + NS 记录管理
│   ├── requests/              # 申请记录
│   ├── profile/               # 个人资料
│   └── oauth/                 # OAuth 绑定
├── module/
│   ├── dns/                   # DNS 核心模块
│   │   ├── service.php        # 域名服务 + NS 记录 CRUD
│   │   ├── provider.php       # HTTP 请求工具
│   │   ├── cloudflare/api.php # Cloudflare API
│   │   ├── alidns/api.php     # 阿里云 DNS API
│   │   ├── dnspod/api.php     # DNSPod API
│   │   └── powerdns/api.php   # PowerDNS API
│   └── oauth/                 # OAuth 登录模块
├── resource/
│   ├── css/                   # 页面布局模板
│   └── js/auth.php            # 认证与辅助函数
├── mail/                      # 邮件发送
└── whois/                     # WHOIS 查询
```

## 安装

### 环境要求

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- PDO MySQL 扩展
- cURL 扩展
- Apache 1.20+
- 服务器需 URL 重写指向 `index.php`

### 步骤

1. 上传全部源码到服务器 Web 目录
2. 浏览器访问 `http://your-domain/install/install.php`
3. 填写数据库连接信息和管理员账号
4. 点击安装，完成

安装后建议删除或保护 `install/` 目录。

## NS 记录管理

已分配域名的用户可在「我的域名」中点击「NS 管理」添加自定义 NS 记录，最多 5 条。

根据根域名配置的 DNS 服务商，系统会自动同步：

| 服务商 | 同步方式 |
|---|---|
| Cloudflare | 单条记录 CRUD，存储记录 ID |
| 阿里云 DNS | 单条记录 CRUD，返回 RecordId |
| DNSPod | 单条记录 CRUD，返回 RecordId |
| PowerDNS | RRset 级别批量同步（增删时同步全部 NS 记录） |
| 手动管理 | 仅本地保存，不同步 |

## 配置

安装后访问 `http://your-domain/admin/login/index.php` 可调整站点名称、SMTP、DNS 凭据、OAuth 等参数。
