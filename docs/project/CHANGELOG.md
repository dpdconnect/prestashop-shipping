<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Module Changelog

<!-- AUTO-GENERATED:START - Do not edit manually -->

Derived from git history. Most recent changes are listed first.

## [2.1] — Current

### Fixed
- Tracking: Carrier tracking functionality improvements (branch `fix/tracking`)
- Cookie: Fix for parcelshop data being stored in cookie being too large
- Authentication: Validate credentials based on JWT token

### Changed
- Updated `DpdCarrier.php`
- Data validation on settings values — only valid values are saved

### Added
- Volume field for API label generation call
- Dropdown selection for DPD carrier by configuration (`dpdconnect_default_package_type`)

---

## [2.0.1]

### Database (`install/upgrade_201.sql`)
- Additional column/index adjustments

---

## [2.0]

### Added
- `parcelshop_data` LONGTEXT column to `parcelshop` table (stores full parcel shop JSON)
- `product_shipping_information` table (Doctrine entity for Fresh/Freeze product types)
- `dpd_shipping_product` and `dpd_carrier_description` columns to `ps_product`
- Fresh & Freeze shipping support
- Async batch processing (batch/job queue)
- `AdminDpdBatchesController` and `AdminDpdJobsController`
- Symfony routes and services (`config/routes.yml`, `config/services.yml`)
- PrestaShop 8 compatibility (PHP 8.1, Doctrine entities, Symfony forms)
- `ProductFormModifier` — DPD fields on product admin page
- `DpdType` Symfony form type
- `DpdEncryptionManager` — AES-128-CBC credential encryption

---

## [1.2]

### Database (`install/upgrade_120.sql`)
- Minor schema adjustments

---

## [1.0] — Initial Release

### Added
- Basic DPD Connect API integration
- Label generation (regular and return)
- Parcel shop locator at checkout
- Carrier management (create/delete)
- Module configuration page
- `dpdshipment_label`, `parcelshop`, `dpd_product_attributes`, `carrier_dpd_product` tables
- Dutch translations (`nl.php`, `translations/nl-NL/`)
- GitLab CI build pipeline
- PHP 7.1 / PrestaShop 1.7 support

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
