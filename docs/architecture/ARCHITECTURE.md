<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Architecture

<!-- AUTO-GENERATED:START - Do not edit manually -->

## Module Type

This is a **PrestaShop module** (type `prestashop-module`). It extends the `Module` base class and registers itself into PrestaShop's hook system. The module uses a hybrid architecture:

- **Legacy-style** classes in `classes/` for core business logic (PrestaShop convention)
- **Symfony-style** code in `src/` for Doctrine entities and Symfony form types (introduced for PS8 compatibility)
- **Smarty templates** in `views/templates/` for rendering
- **Symfony routes** defined in `config/routes.yml`
- **Symfony services** defined in `config/services.yml`

## Directory Structure

```
dpdconnect/
├── dpdconnect.php              # Main module class (entry point)
├── composer.json               # Composer dependencies
├── config.xml                  # Module metadata
├── config_nl.xml               # Dutch locale metadata
├── nl.php                      # Dutch translations
│
├── classes/                    # Core business logic
│   ├── Connect/                # DPD API connectivity
│   │   ├── Connection.php      # Authenticated API client factory
│   │   ├── Label.php           # Label API resource
│   │   ├── Product.php         # Product/carrier API resource
│   │   ├── LabelRepo.php       # Label database repository
│   │   └── DpdConnectCache.php # API response cache adapter
│   ├── Database/               # Database helpers
│   ├── Service/
│   │   └── SettingsDataValidator.php  # Settings form validation
│   ├── Exceptions/
│   │   ├── InvalidRequestException.php
│   │   └── InvalidResponseException.php
│   ├── enums/
│   │   ├── JobStatus.php       # Job lifecycle states
│   │   ├── BatchStatus.php     # Batch lifecycle states
│   │   └── ParcelType.php      # Parcel types (regular/return/Saturday)
│   ├── pdf/
│   │   └── HTMLTemplateDPDShippingList.php  # PDF shipping list template
│   ├── BatchRepo.php           # Batch job CRUD
│   ├── JobRepo.php             # Individual job CRUD
│   ├── DpdCarrier.php          # Carrier create/delete/manage
│   ├── DpdDeliveryOptionsFinder.php  # Checkout carrier options
│   ├── DpdEncryptionManager.php      # AES-128-CBC credential encryption
│   ├── DpdError.php            # Error code mapper
│   ├── DpdHelper.php           # Configuration form builder
│   ├── DpdLabelGenerator.php   # Label generation orchestrator
│   ├── DpdParcelPredict.php    # Parcel prediction / checkout data
│   ├── DpdProductHelper.php    # Carrier ↔ DPD product mapping
│   ├── DpdShippingList.php     # Shipping list generator
│   ├── FreshFreezeHelper.php   # Fresh/Freeze product grouping
│   ├── OrderResponseTransformer.php  # API response → PS order mapping
│   └── Version.php             # Module/webshop version info
│
├── controllers/
│   ├── admin/                  # Admin back office controllers
│   │   ├── AdminDownloadLabelController.php
│   │   ├── AdminDpdBatchesController.php
│   │   ├── AdminDpdBulkActionsController.php
│   │   ├── AdminDpdFreshFreezeController.php
│   │   ├── AdminDpdJobsController.php
│   │   ├── AdminDpdLabelsController.php
│   │   ├── AdminDpdProductAttributesController.php
│   │   └── AdminDpdShippingListController.php
│   └── front/
│       ├── OneStepParcelshop.php  # Parcel shop AJAX endpoint
│       └── callback.php           # DPD callback handler
│
├── src/                        # Symfony-style components (PS8)
│   ├── Entity/
│   │   └── ProductShippingInformation.php  # Doctrine entity
│   ├── Form/
│   │   ├── Modifier/
│   │   │   └── ProductFormModifier.php  # Injects DPD fields into product form
│   │   └── Type/
│   │       └── DpdType.php     # Symfony Form type for DPD fields
│   └── Service/
│       └── FreshFreezeService.php
│
├── config/
│   ├── services.yml            # Symfony DI container definitions
│   └── routes.yml              # Symfony router definitions
│
├── install/
│   ├── install.sql             # Initial database schema
│   ├── upgrade_120.sql         # Migration: 1.x → 1.2
│   ├── upgrade_200.sql         # Migration: 1.x → 2.0
│   └── upgrade_201.sql         # Migration: 2.0 → 2.0.1
│
├── views/
│   ├── css/                    # Module CSS files
│   └── templates/
│       ├── admin/              # Admin Smarty templates
│       │   └── fresh_freeze/   # Fresh/Freeze-specific templates
│       └── 8/                  # PrestaShop 8-specific templates
│           └── fresh_freeze/
│
├── pdf/                        # PDF generation assets
├── img/                        # Module images
├── translations/               # Translation files
│   └── nl-NL/
└── tests/
    └── phpstan/                # Static analysis configuration
```

## Key Design Patterns

### 1. Hook-Based Integration

The module registers into PrestaShop's hook system to extend core functionality without modifying core files. All hooks are defined in `dpdconnect.php::$hooks`.

See [HOOKS.md](../hooks/HOOKS.md) for the full hook reference.

### 2. Lazy Service Instantiation

The main module class exposes factory methods that create service instances on demand:

```php
$this->dpdCarrier()             // → DpdCarrier
$this->dpdLabelGenerator()      // → DpdLabelGenerator
$this->dpdShippingList()        // → DpdShippingList
$this->dpdDeliveryOptionsFinder(...)  // → DpdDeliveryOptionsFinder
```

### 3. Async Batch Processing

Label generation for multiple orders uses a **batch/job queue pattern**:

```
User triggers bulk label generation
        ↓
BatchRepo::create()  →  creates dpd_batches record
        ↓
for each order:
  JobRepo::create()  →  creates dpd_jobs record (status: queued)
        ↓
DPD API call (async)
        ↓
callback / status update
  JobRepo::update()  →  status: success / failed
  BatchRepo::updateStatus()  →  recalculates batch status
```

### 4. Credential Security

API credentials are never stored in plain text. The module uses `DpdEncryptionManager` (AES-128-CBC) with PrestaShop's `_COOKIE_KEY_` as the encryption key. JWT tokens are cached in PS configuration and refreshed automatically via a callback.

### 5. Hybrid Legacy/Symfony Architecture

- PrestaShop 1.7-compatible code uses the legacy `Db`, `DbQuery`, `Configuration` classes
- PrestaShop 8 additions use Doctrine entities (`src/Entity/`) and Symfony forms (`src/Form/`)
- Symfony services are wired in `config/services.yml`
- Routes are defined in `config/routes.yml` and use modern Symfony controller syntax

## External Dependencies

| Package | Purpose |
|---------|---------|
| `dpdconnect/php-sdk` | Official DPD Connect PHP SDK (API client, resources, authentication) |
| `myokyawhtun/pdfmerger` | Merging multiple label PDFs into a single download |

## CI/CD

GitLab CI (`.gitlab-ci.yml`) defines two build jobs:

| Job | PHP Version | Target |
|-----|-------------|--------|
| `prestashop17` | PHP 7.1 | PrestaShop 1.7 |
| `prestashop8` | PHP 8.1 | PrestaShop 8 |

Both jobs run `composer install`, strip development files, and produce a `dpdconnect.zip` artifact.

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
