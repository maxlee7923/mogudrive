# 蘑菇网盘 / Megaish Share

蘑菇网盘是一个基于 `PHP + MySQL` 的轻量文件上传、管理、分享和下载系统。项目面向普通虚拟主机、宝塔面板、独立服务器等常见 PHP 部署环境，支持本地磁盘和 S3 兼容对象存储。

项目特点是部署门槛低：首次访问安装向导即可初始化数据库、管理员账号、站点名称和默认本地存储位置。安装完成后系统会自动写入锁文件，避免安装入口被重复使用。

## 功能特性

- 管理后台
  - 单管理员账号登录
  - 上传文件、创建文件夹、移动、复制、重命名、删除
  - 查看系统文件统计和服务器磁盘信息
  - 自定义分享页按钮
- 上传能力
  - 默认 `30MB` 分块上传
  - 前端并发上传，默认 4 线程
  - 支持断点续传和上传状态查询
  - 可选 MD5 校验，上传完成后由服务端计算实际 MD5
- 分享能力
  - 支持单文件、多文件和文件夹路径组合分享
  - 支持提取码
  - 支持分享过期时间
  - 分享数据会生成独立快照，避免文件记录变动后影响已生成链接
- 下载能力
  - 浏览器端并发拉取分块
  - 前端本地拼接并保存完整文件
  - 文件分块响应带长缓存头，方便接入 CDN
- 存储驱动
  - `local`：服务器本机目录、挂载盘、NAS 挂载目录
  - `s3`：AWS S3 及兼容协议服务，例如 MinIO、Cloudflare R2、腾讯云 COS、阿里云 OSS S3 兼容端点

## 技术栈

- PHP `8.0+`
- MySQL `5.7+` 或 `8.0+`
- Composer
- PDO MySQL
- Bootstrap 前端组件
- AWS SDK for PHP，用于 S3 兼容存储

## 目录结构

```text
.
├── app/                    # 应用核心、控制器、视图和存储适配器
├── database/               # 数据库结构和升级脚本
├── public/                 # 推荐 Web 根目录
│   ├── assets/             # 前端静态资源
│   ├── index.php           # Web 入口
│   ├── install.php         # 安装入口代理
│   └── setup.php           # 环境检查入口代理
├── storage/                # 运行时配置、缓存和本地上传目录
├── vendor/                 # Composer 依赖，本地安装生成，不建议提交
├── composer.json
├── index.php               # 兼容入口
├── install.php             # 首次安装入口
└── setup.php               # 部署环境检查入口
```

`storage/` 目录必须存在且可写。公开仓库中只需要保留空目录占位，不要提交安装配置、上传文件或缓存。

## 环境要求

必需：

- PHP `8.0` 或更高版本
- MySQL `5.7` / `8.0` 或兼容数据库
- PHP 扩展：`pdo_mysql`、`json`
- Composer
- `storage/` 目录可写

建议：

- PHP 扩展：`fileinfo`、`opcache`
- Web 服务器开启 HTTPS
- 上传大文件时调高 PHP 和 Web 服务器限制

常用 PHP 配置参考：

```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 300
memory_limit = 256M
```

实际可上传大小还会受到 Nginx `client_max_body_size`、Apache 配置、CDN 限制和服务器磁盘空间影响。

## 快速部署

### 1. 获取代码

```bash
git clone https://github.com/your-name/moguwangpan.git
cd moguwangpan
composer install --no-dev
```

如果服务器无法执行 Composer，可以先在本地运行 `composer install --no-dev`，再把完整项目上传到服务器。正式开源仓库通常不提交 `vendor/`。

### 2. 配置站点目录

推荐把网站运行目录设置为：

```text
项目目录/public
```

如果服务器不方便设置运行目录，也可以直接指向项目根目录，项目根目录下的 `index.php`、`install.php`、`setup.php` 会代理到 `public/` 内部入口。

### 3. 创建数据库

在 MySQL 中创建一个空数据库，并准备好数据库名、用户名和密码。安装向导会自动导入 `database/schema.sql`。

### 4. 检查环境

访问：

```text
https://你的域名/setup.php
```

环境检查通过后，继续访问安装页：

```text
https://你的域名/install.php
```

填写站点名称、站点地址、数据库连接信息和管理员账号。安装完成后会生成：

```text
storage/config.php
storage/runtime/installed.lock
```

这两个文件属于运行时文件，不应提交到 GitHub。

## 宝塔部署说明

1. 上传项目到站点目录，例如 `/www/wwwroot/moguwangpan`
2. 在宝塔中创建 MySQL 数据库
3. 进入项目目录执行：

```bash
composer install --no-dev
```

4. 设置运行目录为 `public`
5. 确保 `storage/` 可写：

```bash
chown -R www:www /www/wwwroot/moguwangpan/storage
chmod -R 775 /www/wwwroot/moguwangpan/storage
```

6. 打开 `https://你的域名/setup.php` 检查环境
7. 打开 `https://你的域名/install.php` 完成安装

## Nginx 伪静态

运行目录设置为 `public` 时，可使用：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

如果不配置伪静态，也可以使用兼容路由：

```text
/index.php
/index.php?r=%2Fadmin%2Ffiles
/index.php?r=%2Fshare&code=xxxx
/index.php?r=%2Fapi%2Ffiles%2Flist
```

## 使用说明

1. 登录后台：`/index.php`
2. 在“存储位置”中新增本地存储或 S3 兼容存储
3. 在“上传文件”中选择存储位置并上传文件
4. 在“文件列表”中选择文件或文件夹
5. 创建分享链接，可选提取码和过期时间
6. 将分享链接发送给用户下载

分享链接示例：

```text
/share?code=xxxx
/share?code=xxxx&pwd=1234
```

## S3 兼容存储配置

新增 S3 存储时需要准备：

- `region`：区域，例如 `auto`、`ap-shanghai`、`us-east-1`
- `endpoint`：兼容服务端点，例如 MinIO 或 R2 的 S3 API 地址
- `bucket`：桶名称
- `access_key`：访问密钥 ID
- `secret_key`：访问密钥 Secret
- `path_style`：是否使用路径风格地址，MinIO 通常需要开启

建议为生产环境单独创建最小权限 Access Key，只授予对应 Bucket 的对象读写和删除权限。

## CDN 建议

下载分块接口适合接入 CDN：

```text
/api/file/chunk
```

缓存键需要包含完整 query，例如：

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

### 从旧版本升级 MD5 字段

如果旧数据库缺少 MD5 字段，执行：

```sql
SOURCE /www/wwwroot/moguwangpan/database/migration_md5.sql;
```

### 从旧版本升级文件夹表

如果旧数据库缺少 `file_folders` 表，执行：

```sql
SOURCE /www/wwwroot/moguwangpan/database/migration_file_folders.sql;
```

升级前建议先备份数据库和 `storage/` 目录。

## 重置为未安装状态

如果需要重新运行安装向导，删除以下文件：

```text
storage/config.php
storage/runtime/installed.lock
```

如果要同时清空上传文件、分享快照和运行期缓存，可删除：

```text
storage/uploads/
storage/chunks/
storage/runtime/share-manifests/
storage/runtime/shares.json
```

如果要清空数据库中的业务数据，可在确认备份后执行：

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
- 数据库导出文件、备份包、服务器日志
- 任何真实域名密钥、数据库密码、S3 Access Key 或 Secret

建议提交：

- `app/`
- `database/`
- `public/`
- `composer.json`
- `composer.lock`
- `README.md`
- `.gitignore`
- `storage/.gitkeep`

首次提交前可以检查：

```bash
git status --short
git add .
git status --short
```

确认没有敏感文件后再提交。

## 安全建议

- 生产环境务必启用 HTTPS
- 安装完成后确认 `install.php` 已被 `installed.lock` 锁定
- 后台管理员密码至少 8 位，建议使用高强度随机密码
- 限制后台入口访问频率，避免暴力破解
- 上传目录不要允许直接执行 PHP
- S3 密钥使用最小权限策略
- 定期备份数据库和本地存储目录

## 已知边界

- 当前为单管理员模型，没有多用户权限体系
- 下载断点恢复主要面向浏览器会话内重试，跨刷新持久化可后续接入 IndexedDB 或 OPFS
- 分享链接使用快照数据，创建分享后文件移动或重命名不一定同步到旧分享
- 大文件上传受 PHP、Web 服务器、代理、CDN 和对象存储限制共同影响

## 开源协议

本仓库采用 [MIT License](LICENSE)。

协作规范和安全报告说明见 [CONTRIBUTING.md](CONTRIBUTING.md) 与 [SECURITY.md](SECURITY.md)。
