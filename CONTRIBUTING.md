# Contributing

Thanks for helping improve this project.

## Local setup

```bash
composer install --no-dev
```

Then open:

- `setup.php` for environment checks
- `install.php` for first-time installation

Make sure `storage/` is writable and do not commit generated runtime files.

## What to include in a change

- A clear problem statement or feature goal
- The smallest code change that solves it
- Documentation updates when behavior or setup changes
- A short verification note if you tested the change

## What not to commit

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

## Testing checklist

Before opening a pull request, verify the affected flow manually:

- Environment check still opens
- Install flow still works on a fresh database
- Login and upload flows still load
- Share links still resolve
- No runtime data was accidentally added to the diff

## Pull requests

Describe:

- What changed
- Why it changed
- How you verified it
- Any setup or migration steps

