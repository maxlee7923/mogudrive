# Security Policy

## Reporting a vulnerability

Do not disclose secrets, credentials, database dumps, or private keys in public issues.

If you find a security issue:

1. Report it through the repository's private vulnerability reporting channel if available.
2. Otherwise contact the maintainer privately.
3. Include the affected file, endpoint, and reproduction steps.

## What to include

- A short summary of the issue
- Impact and likely abuse path
- Exact request or sequence needed to reproduce
- Environment details if the problem is deployment-specific

## What to avoid

- Publicly posting access tokens, database passwords, or S3 credentials
- Uploading production data
- Sharing screenshots that expose private information

## Scope

This project stores runtime configuration, upload state, and share data on disk and in MySQL. Treat both as sensitive.

If you are rotating credentials after an incident, update:

- `storage/config.php`
- Database user credentials
- Any S3 access keys or temporary tokens

