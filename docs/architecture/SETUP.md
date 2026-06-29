<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Setup & Installation

<!-- AUTO-GENERATED:START - Do not edit manually -->

## Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 7.1 (PS 1.7) / 8.1 (PS 8) |
| PrestaShop | 1.7 or 8.x |
| Composer | 2.x |
| MySQL/MariaDB | As required by PrestaShop |

## Installation via Back Office (Production)

1. Download or build the module ZIP (see **Building a Release** below).
2. In the PrestaShop back office, go to **Modules → Module Manager**.
3. Click **Upload a module** and select `dpdconnect.zip`.
4. After installation, navigate to **Modules → Module Manager** and configure the module.

## Development Setup

### 1. Clone the repository

```bash
git clone <repository-url> dpdconnect
cd dpdconnect
```

> **Important**: The folder must be named `dpdconnect` to match the module name.

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Symlink or copy into PrestaShop

Place (or symlink) the module folder into your PrestaShop installation:

```bash
# Symlink (recommended for development)
ln -s /path/to/dpdconnect /path/to/prestashop/modules/dpdconnect

# Or copy
cp -r /path/to/dpdconnect /path/to/prestashop/modules/dpdconnect
```

### 4. Install the module

Install via PrestaShop back office **Module Manager**, or via CLI:

```bash
cd /path/to/prestashop
php bin/console prestashop:module install dpdconnect
```

### 5. Configure credentials

After installation, go to **Modules → Module Manager → DPD Connect → Configure**:

| Field | Value |
|-------|-------|
| API URL | DPD Connect API endpoint (provided by DPD) |
| Username | Your DPD Connect account username |
| Password | Your DPD Connect account password |
| Depot | Your DPD depot number |

Credentials are stored encrypted using AES-128-CBC with PrestaShop's `_COOKIE_KEY_`.

## Building a Release

GitLab CI builds releases automatically on push. To build locally:

```bash
composer install --no-dev
zip -r dpdconnect.zip . \
  --exclude ".git/*" \
  --exclude ".gitlab-ci.yml" \
  --exclude "tests/*" \
  --exclude "docs/*"
```

## Static Analysis

PHPStan is configured at level 2 using the PrestaShop PHP dev tools extension:

```bash
cd tests/phpstan
composer install
vendor/bin/phpstan analyse --configuration=phpstan.neon
```

## Module Upgrade

Database migrations are handled automatically by PrestaShop's upgrade system. SQL scripts are in `install/`:

| File | Migration |
|------|-----------|
| `install.sql` | Fresh install schema |
| `upgrade_120.sql` | Upgrade from 1.x to 1.2 |
| `upgrade_200.sql` | Upgrade from 1.x to 2.0 (adds parcelshop_data, product_shipping_information) |
| `upgrade_201.sql` | Upgrade from 2.0 to 2.0.1 |

PrestaShop detects and runs these automatically based on the version stored in the module configuration.

## Uninstallation

Uninstalling the module via the PrestaShop back office will:
- Soft-delete all DPD carriers (they are marked `deleted = 1`, not removed from DB)
- Remove registered hooks
- Remove configuration values

Database tables are **not** dropped on uninstall to preserve historical label data.

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
