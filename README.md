# OWBN Board

Modular WordPress plugin for cross-site OWBN tools with role-based access via accessSchema.

**Version**: 0.9.0
**Requires PHP**: 7.4
**License**: GPL-2.0-or-later

## Installation

1. Copy `owbn-board/` into `/wp-content/plugins/`
2. Activate in WordPress admin
3. Configure tools at **OWBN Board > Config**

## Architecture

Tools live in `tools/` as self-contained directories (CPT, fields, hooks, rendering, webhooks). Enable/disable per tool in admin. `_template/` provides the scaffolding for new tools.

Includes an embedded accessSchema client for role-based access control.

## Changelog

### 0.9.0

- Stripped comment bloat, banner dividers, and AI scaffolding artifacts

### 0.8.0

- accessSchema client multi-tenancy fixes

## Contributing

[github.com/One-World-By-Night/owbn-board](https://github.com/One-World-By-Night/owbn-board)
