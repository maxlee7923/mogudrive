[English](#english) | [中文](#chinese)

<a id="english"></a>

# Security Policy

## Reporting a Vulnerability

Do not disclose secrets, credentials, database dumps, or private keys in public issues.

If you find a security issue:

1. Report it through the repository's private vulnerability reporting channel if available.
2. Otherwise contact the maintainer privately.
3. Include the affected file, endpoint, and reproduction steps.

## What to Include

- A short summary of the issue
- Impact and likely abuse path
- Exact request or sequence needed to reproduce
- Environment details if the problem is deployment-specific

## What to Avoid

- Publicly posting access tokens, database passwords, or S3 credentials
- Uploading production data
- Sharing screenshots that expose private information

## Scope

This project stores runtime configuration, upload state, and share data on disk and in MySQL. Treat both as sensitive.

If you are rotating credentials after an incident, update:

- `storage/config.php`
- Database user credentials
- Any S3 access keys or temporary tokens

---

<a id="chinese"></a>

# 安全策略

## 漏洞报告

不要在公开 issue 中披露密钥、凭据、数据库导出或私钥。

如果你发现安全问题：

1. 如果仓库提供了私有漏洞报告通道，请通过该通道提交。
2. 否则请私下联系维护者。
3. 请附上受影响文件、接口和复现步骤。

## 报告中应包含什么

- 问题简述
- 影响范围和可能的滥用路径
- 复现所需的准确请求或操作顺序
- 如果是部署相关问题，请附上环境信息

## 不要包含什么

- 公开发布访问令牌、数据库密码或 S3 凭据
- 上传生产数据
- 分享会暴露私密信息的截图

## 适用范围

本项目会把运行时配置、上传状态和分享数据保存在磁盘和 MySQL 中，请把这两部分都视为敏感数据。

如果在事故后需要轮换凭据，请同步更新：

- `storage/config.php`
- 数据库用户凭据
- 任何 S3 Access Key 或临时令牌
