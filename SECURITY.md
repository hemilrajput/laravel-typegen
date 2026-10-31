# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.3.x   | :white_check_mark: |
| 2.2.x   | :x:                |
| < 2.2.0 | :x:                |

Versions prior to 2.2.0 contain known vulnerabilities (command injection and path traversal in code generation paths) addressed in v2.2.0. Users must upgrade to 2.2.0 or higher (recommended: latest 2.3.x).

## Reporting a Vulnerability

Please do not disclose security vulnerabilities publicly via GitHub issues.

To report a vulnerability:
- Use [GitHub Private Vulnerability Reporting](https://github.com/hemilrajput/laravel-typegen/security/advisories/new) on the repository.
- Alternatively, email `rajputhemil04@gmail.com` with the subject line `[SECURITY] laravel-typegen`.

Please include:
1. Description of the vulnerability.
2. Steps or proof-of-concept reproducing the issue.
3. Impact assessment.

Vulnerabilities will be acknowledged within 48 hours, and patches will be released in a timely security update.
