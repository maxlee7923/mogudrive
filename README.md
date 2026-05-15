[English](#english) | [中文](#chinese)

<a id="english"></a>

# Megaish Share

Megaish Share is a PHP + MySQL file upload, management, sharing, and download system for common PHP hosting environments, Baota panels, and standalone servers. It supports local disk storage and S3-compatible object storage.

The project is designed to bootstrap quickly: the first visit opens an install wizard that creates the schema, admin account, site name, and default local storage. After installation, the app writes a lock file to prevent accidental reuse of the installer.

## Features

- Admin backend
  - Single-admin login
  - Upload, create folders, move, copy, rename, and delete files
  - View file statistics and server disk information
  - Customize share-page buttons
- Upload workflow
  - Default `30MB` chunk upload
  - Parallel uploads, with 4 threads on the frontend by default
  - Resume uploads by upload token and uploaded chunk status
  - Optional MD5 validation, with server-side MD5 calculation after merge
- Sharing workflow
  - Share single files, multiple files, or folder-scoped file sets
  - Password-protected shares
  - Expiration timestamps
  - Share snapshots so later file changes do not break existing links
- Download workflow
  - Parallel chunk fetch in the browser
  - Local assembly and save to a complete file
  - Stable chunk URLs that work well behind a CDN
- Storage drivers
  - `local`: local disk, mounted volume, NAS path
  - `s3`: AWS S3 and S3-compatible services such as MinIO, R2, COS, and OSS-compatible endpoints

## Tech Stack

- PHP `8.0+`
- MySQL `5.7+` or `8.0+`
- Composer
- PDO MySQL
- Bootstrap-based frontend assets
- AWS SDK for PHP for S3-compatible storage

## Repository Layout

```text
.
├── app/                    # Application core, controllers, views, and storage adapters
├── database/               # Schema and migration scripts
├── public/                 # Recommended web root
│   ├── assets/             # Static frontend assets
│   ├── index.php           # Web entry point
│   ├── install.php         # Install proxy entry
│   └── setup.php           # Environment check proxy entry
├── storage/                # Runtime config, cache, and local upload data
├── vendor/                 # Composer dependencies, generated locally
├── composer.json
├── index.php               # Compatibility entry
├── install.php             # First-time install entry
└── setup.php               # Deployment check entry
```

`storage/` must exist and be writable. Public repositories should keep only an empty placeholder there; do not commit runtime config, uploads, or caches.

## Requirements

Required:

- PHP `8.0` or later
- MySQL `5.7` / `8.0` or compatible
- PHP extensions: `pdo_mysql`, `json`
- Composer
- Writable `storage/` directory

Recommended:

- PHP extensions: `fileinfo`, `opcache`
- HTTPS in production
- Higher PHP and web-server limits for large uploads

Example PHP limits:

```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 300
memory_limit = 256M
```

Actual upload limits also depend on Nginx `client_max_body_size`, Apache settings, CDN limits, and available disk space.

## Quick Start

### 1. Clone and install dependencies

```bash
git clone https://github.com/maxlee7923/mogudrive.git
cd mogudrive
composer install --no-dev
```

If the server cannot run Composer directly, install dependencies locally first and then upload the full project. Public repositories usually should not commit `vendor/`.

### 2. Choose a web root

Recommended web root:

```text
project-directory/public
```

If setting the web root is inconvenient, the project root also works because the top-level `index.php`, `install.php`, and `setup.php` proxy to the `public/` entry points.

### 3. Create the database

Create an empty MySQL database and prepare the database name, username, and password. The installer will import `database/schema.sql` automatically.

### 4. Run setup and install

Open:

```text
https://your-domain/setup.php
```

Use the environment checker first. If shell execution is allowed, it can also attempt to install Composer dependencies automatically.

Then open:

```text
https://your-domain/install.php
```

Fill in the site name, site URL, database connection, and admin account. After installation, the app creates:

```text
storage/config.php
storage/runtime/installed.lock
```

These are runtime files and should not be committed to GitHub.

## Baota Deployment

1. Upload the project to the site directory, for example `/www/wwwroot/mogudrive`
2. Create a MySQL database in Baota
3. Run:

```bash
composer install --no-dev
```

4. Set the web root to `public`
5. Make sure `storage/` is writable:

```bash
chown -R www:www /www/wwwroot/mogudrive/storage
chmod -R 775 /www/wwwroot/mogudrive/storage
```

6. Open `https://your-domain/setup.php` to verify the environment
7. Open `https://your-domain/install.php` to complete installation

## Nginx Rewrite

When the web root is `public`, this works well:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Without rewrite rules, you can still use the compatibility routes:

```text
/index.php
/index.php?r=%2Fadmin%2Ffiles
/index.php?r=%2Fshare&code=xxxx
/index.php?r=%2Fapi%2Ffiles%2Flist
```

## Usage

1. Log in at `/index.php`
2. Add a local or S3-compatible storage location
3. Upload files into the selected storage location
4. Select files or folders in the file list
5. Create a share link, optionally with a password and expiration
6. Send the share link to users for download

Example share links:

```text
/share?code=xxxx
/share?code=xxxx&pwd=1234
```

## S3-Compatible Storage

To add an S3 storage location, prepare:

- `region`: for example `auto`, `ap-shanghai`, or `us-east-1`
- `endpoint`: the compatible service endpoint, such as a MinIO or R2 S3 API endpoint
- `bucket`: bucket name
- `access_key`: access key ID
- `secret_key`: secret key
- `path_style`: whether to use path-style URLs, which is often required for MinIO

For production, create a least-privilege access key that only has the object read/write/delete permissions needed for that bucket.

## CDN Notes

The chunk download endpoint is suitable for CDN caching:

```text
/api/file/chunk
```

Make sure the cache key includes the full query string, especially:

```text
file_id
chunk
code
exp
sig
```

The response already uses:

```http
Cache-Control: public, max-age=31536000, immutable
```

## Upgrade Notes

### Add MD5 support to an old database

If an older database is missing the MD5 fields, run:

```sql
SOURCE /www/wwwroot/mogudrive/database/migration_md5.sql;
```

### Add the folder table to an old database

If an older database is missing `file_folders`, run:

```sql
SOURCE /www/wwwroot/mogudrive/database/migration_file_folders.sql;
```

Back up the database and `storage/` directory before upgrading.

## Reset to Uninstalled State

To rerun the installer, delete:

```text
storage/config.php
storage/runtime/installed.lock
```

To also clear uploaded files, share snapshots, and runtime cache, remove:

```text
storage/uploads/
storage/chunks/
storage/runtime/share-manifests/
storage/runtime/shares.json
```

To clear all business data from the database, run this after you have a backup:

```sql
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE share_items;
TRUNCATE TABLE shares;
TRUNCATE TABLE upload_chunks;
TRUNCATE TABLE upload_sessions;
TRUNCATE TABLE files;
TRUNCATE TABLE file_folders;
TRUNCATE TABLE storage_locations;
TRUNCATE TABLE settings;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS=1;
```

This removes the admin account, system settings, storage locations, upload records, file records, and share records. The app must be installed again afterward.

## GitHub Submission Notes

Do not commit:

- `vendor/`
- `storage/config.php`
- `storage/runtime/`
- `storage/uploads/`
- `storage/chunks/`
- database dumps, backups, or server logs
- real domain names, passwords, S3 access keys, or secret keys

Commit these:

- `app/`
- `database/`
- `public/`
- `composer.json`
- `composer.lock`
- `README.md`
- `.gitignore`
- `storage/.gitkeep`

Before the first push, run:

```bash
git status --short
git add .
git status --short
```

Confirm that no sensitive files are included.

## Security Notes

- Use HTTPS in production
- Confirm that `install.php` is locked after installation
- Use a strong admin password
- Rate-limit the admin entry point to reduce brute-force attempts
- Do not allow PHP execution inside the upload directory
- Use least-privilege S3 credentials
- Back up the database and local storage regularly

## Known Limitations

- The current model is single-admin only
- Download resume is mainly designed for retry within the same browser session; cross-refresh persistence can be added later with IndexedDB or OPFS
- Share links use snapshots, so later file moves or renames do not automatically update existing shares
- Large uploads are constrained by PHP, the web server, proxies, CDN behavior, and available storage

## License

This repository is licensed under the MIT License. See [LICENSE](LICENSE).

---

<a id="chinese"></a>

# 蘑菇网盘 / Megaish Share

蘑菇网盘是一个基于 `PHP + MySQL` 的文件上传、管理、分享和下载系统，适用于普通 PHP 虚拟主机、宝塔面板和独立服务器。它支持本地磁盘和 S3 兼容对象存储。

项目的初始化流程比较直接：首次访问会打开安装向导，用来创建数据库结构、管理员账号、站点名称和默认本地存储。安装完成后，程序会写入锁文件，避免安装入口被重复使用。

## 功能特性

- 管理后台
  - 单管理员登录
  - 上传文件、创建文件夹、移动、复制、重命名、删除
  - 查看文件统计和服务器磁盘信息
  - 自定义分享页按钮
- 上传流程
  - 默认 `30MB` 分块上传
  - 前端并发上传，默认 4 线程
  - 支持按上传 token 和已上传分块状态续传
  - 可选 MD5 校验，合并后由服务端计算实际 MD5
- 分享流程
  - 支持单文件、多文件和按文件夹范围分享
  - 支持提取码
  - 支持过期时间
  - 分享时会生成快照，后续文件变化不会破坏旧链接
- 下载流程
  - 浏览器端并发拉取分块
  - 本地拼接后保存完整文件
  - 分块 URL 稳定，适合接入 CDN
- 存储驱动
  - `local`：本地磁盘、挂载盘、NAS 路径
  - `s3`：AWS S3 以及 MinIO、R2、COS、OSS 等 S3 兼容端点

## 技术栈

- PHP `8.0+`
- MySQL `5.7+` 或 `8.0+`
- Composer
- PDO MySQL
- Bootstrap 前端资源
- AWS SDK for PHP，用于 S3 兼容存储

## 目录结构

```text
.
├── app/                    # 应用核心、控制器、视图和存储适配器
├── database/               # 数据库结构和迁移脚本
├── public/                 # 推荐网站根目录
│   ├── assets/             # 前端静态资源
│   ├── index.php           # Web 入口
│   ├── install.php         # 安装入口代理
│   └── setup.php           # 环境检查入口代理
├── storage/                # 运行时配置、缓存和本地上传数据
├── vendor/                 # Composer 依赖，本地安装生成
├── composer.json
├── index.php               # 兼容入口
├── install.php             # 首次安装入口
└── setup.php               # 部署检查入口
```

`storage/` 必须存在并且可写。公开仓库只保留空目录占位，不要提交运行时配置、上传文件或缓存。

## 环境要求

必需：

- PHP `8.0` 或更高版本
- MySQL `5.7` / `8.0` 或兼容数据库
- PHP 扩展：`pdo_mysql`、`json`
- Composer
- `storage/` 目录可写

建议：

- PHP 扩展：`fileinfo`、`opcache`
- 生产环境使用 HTTPS
- 大文件上传时提高 PHP 和 Web 服务器限制

常用 PHP 配置示例：

```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 300
memory_limit = 256M
```

实际可上传大小还会受到 Nginx `client_max_body_size`、Apache 配置、CDN 限制和磁盘空间影响。

## 快速开始

### 1. 克隆并安装依赖

```bash
git clone https://github.com/maxlee7923/mogudrive.git
cd mogudrive
composer install --no-dev
```

如果服务器不能直接运行 Composer，可以先在本地安装依赖，再把完整项目上传。公开仓库通常不要提交 `vendor/`。

### 2. 选择网站根目录

推荐运行目录：

```text
项目目录/public
```

如果不方便修改运行目录，也可以直接使用项目根目录，因为根目录下的 `index.php`、`install.php`、`setup.php` 会代理到 `public/` 内部入口。

### 3. 创建数据库

先创建一个空的 MySQL 数据库，并准备好数据库名、用户名和密码。安装器会自动导入 `database/schema.sql`。

### 4. 运行检查和安装

先访问：

```text
https://你的域名/setup.php
```

这一步会检查环境。如果服务器允许执行 shell 命令，它也可以尝试自动安装 Composer 依赖。

然后访问：

```text
https://你的域名/install.php
```

填写站点名称、站点地址、数据库连接和管理员账号。安装后会生成：

```text
storage/config.php
storage/runtime/installed.lock
```

这些都是运行时文件，不应提交到 GitHub。

## 宝塔部署

1. 上传项目到站点目录，例如 `/www/wwwroot/mogudrive`
2. 在宝塔中创建 MySQL 数据库
3. 执行：

```bash
composer install --no-dev
```

4. 将运行目录设置为 `public`
5. 确保 `storage/` 可写：

```bash
chown -R www:www /www/wwwroot/mogudrive/storage
chmod -R 775 /www/wwwroot/mogudrive/storage
```

6. 打开 `https://你的域名/setup.php` 检查环境
7. 打开 `https://你的域名/install.php` 完成安装

## Nginx 重写

当运行目录设置为 `public` 时，可以使用：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

如果不配置重写，也可以使用兼容路由：

```text
/index.php
/index.php?r=%2Fadmin%2Ffiles
/index.php?r=%2Fshare&code=xxxx
/index.php?r=%2Fapi%2Ffiles%2Flist
```

## 使用说明

1. 登录后台：`/index.php`
2. 新增本地存储或 S3 兼容存储
3. 在所选存储位置中上传文件
4. 在文件列表中选择文件或文件夹
5. 创建分享链接，可选提取码和过期时间
6. 将分享链接发送给用户下载

分享链接示例：

```text
/share?code=xxxx
/share?code=xxxx&pwd=1234
```

## S3 兼容存储

新增 S3 存储时需要准备：

- `region`：例如 `auto`、`ap-shanghai`、`us-east-1`
- `endpoint`：兼容服务端点，例如 MinIO 或 R2 的 S3 API 地址
- `bucket`：桶名称
- `access_key`：访问密钥 ID
- `secret_key`：访问密钥 Secret
- `path_style`：是否使用路径风格地址，MinIO 通常需要开启

生产环境建议单独创建最小权限 Access Key，只授予该 Bucket 所需的对象读写和删除权限。

## CDN 建议

分块下载接口适合接入 CDN：

```text
/api/file/chunk
```

缓存键应包含完整 query，尤其是：

```text
file_id
chunk
code
exp
sig
```

程序响应中已经设置：

```http
Cache-Control: public, max-age=31536000, immutable
```

## 升级说明

### 给旧数据库增加 MD5 支持

如果旧数据库缺少 MD5 字段，执行：

```sql
SOURCE /www/wwwroot/mogudrive/database/migration_md5.sql;
```

### 给旧数据库增加文件夹表

如果旧数据库缺少 `file_folders`，执行：

```sql
SOURCE /www/wwwroot/mogudrive/database/migration_file_folders.sql;
```

升级前请先备份数据库和 `storage/` 目录。

## 重置为未安装状态

如果要重新运行安装向导，删除：

```text
storage/config.php
storage/runtime/installed.lock
```

如果还要清空上传文件、分享快照和运行缓存，删除：

```text
storage/uploads/
storage/chunks/
storage/runtime/share-manifests/
storage/runtime/shares.json
```

如果要清空数据库中的业务数据，请在备份后执行：

```sql
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE share_items;
TRUNCATE TABLE shares;
TRUNCATE TABLE upload_chunks;
TRUNCATE TABLE upload_sessions;
TRUNCATE TABLE files;
TRUNCATE TABLE file_folders;
TRUNCATE TABLE storage_locations;
TRUNCATE TABLE settings;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS=1;
```

这会删除管理员账号、系统设置、存储位置、上传记录、文件记录和分享记录。执行后需要重新安装。

## GitHub 提交注意事项

不要提交以下内容：

- `vendor/`
- `storage/config.php`
- `storage/runtime/`
- `storage/uploads/`
- `storage/chunks/`
- 数据库导出、备份或服务器日志
- 真实域名、密码、S3 Access Key 或 Secret

建议提交：

- `app/`
- `database/`
- `public/`
- `composer.json`
- `composer.lock`
- `README.md`
- `.gitignore`
- `storage/.gitkeep`

首次推送前可以执行：

```bash
git status --short
git add .
git status --short
```

确认没有包含敏感文件后再提交。

## 安全建议

- 生产环境务必使用 HTTPS
- 安装完成后确认 `install.php` 已被锁定
- 使用高强度管理员密码
- 对后台入口做限流，减少暴力破解风险
- 上传目录不要允许 PHP 执行
- S3 密钥使用最小权限策略
- 定期备份数据库和本地存储目录

## 已知边界

- 当前是单管理员模型，没有多用户权限体系
- 下载断点恢复主要面向同一浏览器会话内重试，跨刷新持久化可以后续接入 IndexedDB 或 OPFS
- 分享链接使用快照数据，因此文件后续移动或重命名不会自动同步到旧分享
- 大文件上传会受到 PHP、Web 服务器、代理、CDN 行为和可用磁盘空间的共同限制

## 开源协议

本仓库采用 MIT License，见 [LICENSE](LICENSE)。
