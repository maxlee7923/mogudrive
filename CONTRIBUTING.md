[English](#english) | [中文](#chinese)

<a id="english"></a>

# Contributing

Thanks for helping improve this project.

## Local Setup

```bash
composer install --no-dev
```

Then open:

- `setup.php` for environment checks
- `install.php` for first-time installation

Make sure `storage/` is writable and do not commit generated runtime files.

## What to Include in a Change

- A clear problem statement or feature goal
- The smallest code change that solves it
- Documentation updates when behavior or setup changes
- A short verification note if you tested the change

## What Not to Commit

- `vendor/`
- `storage/config.php`
- `storage/runtime/`
- `storage/uploads/`
- `storage/chunks/`
- Database dumps, logs, secrets, or access keys

## Style

- Keep changes focused and consistent with the existing codebase
- Prefer existing helpers and controller flow over introducing new patterns
- Use ASCII by default unless a file already uses non-ASCII content

## Testing Checklist

Before opening a pull request, verify the affected flow manually:

- Environment check still opens
- Install flow still works on a fresh database
- Login and upload flows still load
- Share links still resolve
- No runtime data was accidentally added to the diff

## Pull Requests

Describe:

- What changed
- Why it changed
- How you verified it
- Any setup or migration steps

---

<a id="chinese"></a>

# 贡献指南

感谢你帮助改进这个项目。

## 本地准备

```bash
composer install --no-dev
```

然后打开：

- `setup.php` 用于环境检查
- `install.php` 用于首次安装

确保 `storage/` 可写，并且不要提交运行时生成文件。

## 修改内容应包含什么

- 清晰的问题描述或功能目标
- 解决问题所需的最小代码改动
- 行为或安装方式变化时同步更新文档
- 如果做过测试，附上简短验证说明

## 不要提交什么

- `vendor/`
- `storage/config.php`
- `storage/runtime/`
- `storage/uploads/`
- `storage/chunks/`
- 数据库导出、日志、密钥或访问凭据

## 代码风格

- 修改范围尽量集中，并与现有代码风格保持一致
- 优先复用现有 helper 和控制器流程，不要随意引入新范式
- 默认使用 ASCII，除非文件本身已经包含非 ASCII 内容

## 测试清单

在发起 Pull Request 之前，手动确认相关流程：

- 环境检查页可以打开
- 新数据库上的安装流程可正常完成
- 登录和上传流程可以正常加载
- 分享链接可以正常访问
- diff 中没有混入运行时数据

## Pull Request

请说明：

- 改了什么
- 为什么要改
- 如何验证
- 是否需要额外的安装或迁移步骤
