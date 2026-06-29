<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Database Schema

<!-- AUTO-GENERATED:START - Do not edit manually -->

All table names use the PrestaShop prefix (`_DB_PREFIX_`, typically `ps_`). In the SQL files the placeholder `_PREFIX_` is used.

## Tables Overview

| Table | Purpose |
|-------|---------|
| `dpdshipment_label` | Stores generated shipping labels (PDF blob) |
| `parcelshop` | Records parcel shop selection per order |
| `dpd_product_attributes` | Customs data per product (HS code, origin, customs value, age check) |
| `dpd_batches` | Tracks async label generation batches |
| `dpd_jobs` | Tracks individual label generation jobs within a batch |
| `carrier_dpd_product` | Maps PrestaShop carrier IDs to DPD product codes |
| `product_shipping_information` | Stores DPD shipping product type per product |
| `product` (modified) | Adds `dpd_shipping_product` and `dpd_carrier_description` columns |

---

## `dpdshipment_label`

Stores generated PDF labels associated with orders.

| Column | Type | Description |
|--------|------|-------------|
| `id_dpdcarrier_label` | int(10) unsigned | Primary key |
| `mps_id` | varchar(255) | DPD MPS (Multi-Parcel Shipment) ID |
| `label_nummer` | text | DPD label number(s) |
| `order_id` | int(11) | PrestaShop order ID |
| `created_at` | datetime | When the label was generated |
| `shipped` | tinyint(4) | Whether the shipment has been shipped (0/1) |
| `label` | mediumblob | Raw PDF label binary data |
| `retour` | tinyint(1) | Whether this is a return label (0=outbound, 1=return) |

**Managed by**: `classes/Connect/LabelRepo.php`

---

## `parcelshop`

Records which parcel shop a customer selected at checkout.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int(11) | Primary key |
| `order_id` | int(11) | PrestaShop order ID |
| `parcelshop_id` | varchar(255) | DPD parcel shop identifier |
| `parcelshop_data` | longtext | Full parcel shop JSON data (added in v2.0) |

---

## `dpd_product_attributes`

Stores customs-related data per PrestaShop product, used for international shipping.

| Column | Type | Description |
|--------|------|-------------|
| `id_dpd_product_attributes` | int(11) | Primary key |
| `product_id` | int(11) | PrestaShop product ID (unique) |
| `hs_code` | varchar(255) | Harmonized System (HS/tariff) code |
| `country_of_origin` | varchar(255) | ISO country code of production |
| `customs_value` | int | Declared customs value |
| `age_check` | varchar(255) | Age verification requirement |

**Managed by**: `controllers/admin/AdminDpdProductAttributesController.php`

---

## `dpd_batches`

Tracks the overall status of a batch label generation request.

| Column | Type | Description |
|--------|------|-------------|
| `id_dpd_batches` | mediumint(9) | Primary key |
| `created_at` | datetime | When the batch was created |
| `shipment_count` | smallint(5) | Total number of shipments in this batch |
| `success_count` | smallint(5) | Number of successfully processed shipments |
| `failure_count` | smallint(5) | Number of failed shipments |
| `status` | varchar(255) | Batch lifecycle status (see below) |

**Indexes**: `created_at`

**Status values** (from `BatchStatus`):

| Value | Meaning |
|-------|---------|
| `status_request` | Batch created, not yet queued |
| `status_queued` | Batch queued for processing |
| `status_processing` | DPD is processing the batch |
| `status_success` | All jobs succeeded |
| `status_failed` | All jobs failed |
| `status_partially_failed` | Some jobs succeeded, some failed |

**Managed by**: `classes/BatchRepo.php`

---

## `dpd_jobs`

Individual shipment job within a batch.

| Column | Type | Description |
|--------|------|-------------|
| `id_dpd_jobs` | mediumint(9) | Primary key |
| `created_at` | datetime | When the job was created |
| `external_id` | varchar(255) | DPD external job/shipment ID |
| `batch_id` | varchar(255) | Reference to `dpd_batches.id_dpd_batches` |
| `order_id` | varchar(255) | PrestaShop order ID |
| `status` | varchar(255) | Job lifecycle status (see below) |
| `type` | varchar(255) | Job type (regular/return/saturday) |
| `error` | text | Error message if the job failed |
| `state_message` | text | Additional status information from DPD |
| `label_id` | int | Reference to `dpdshipment_label.id_dpdcarrier_label` |

**Indexes**: `created_at`, `batch_id`

**Status values** (from `JobStatus`):

| Value | Meaning |
|-------|---------|
| `status_request` | Job created |
| `status_queued` | Job queued at DPD |
| `status_processing` | DPD is generating the label |
| `status_success` | Label generated successfully |
| `status_failed` | Label generation failed |

**Managed by**: `classes/JobRepo.php`

---

## `carrier_dpd_product`

Maps PrestaShop carrier IDs to DPD product codes.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int(11) | Primary key |
| `carrier_id` | int(11) | PrestaShop carrier ID (id_reference) |
| `dpd_product_code` | varchar(255) | DPD product code (e.g. `CL`, `E12`, `PREDICT`) |

**Managed by**: `classes/DpdProductHelper.php`

---

## `product_shipping_information`

Stores the DPD shipping product type assigned to a PrestaShop product (used for Fresh/Freeze).

| Column | Type | Description |
|--------|------|-------------|
| `id` | int | Primary key |
| `product_id` | int | PrestaShop product ID |
| `dpd_shipping_product` | varchar(255) | DPD shipping type (`default`, `fresh`, `freeze`) |
| `dpd_carrier_description` | varchar(255) | Carrier description override |

**Managed by**: `src/Entity/ProductShippingInformation.php` (Doctrine entity)

---

## `ps_product` (Modified Columns)

Two columns are added to the core `ps_product` table:

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `dpd_shipping_product` | varchar(255) | `default` | DPD shipping type for this product |
| `dpd_carrier_description` | text | NULL | DPD carrier description |

These columns are added during the v2.0 migration (`install/upgrade_200.sql`).

---

## Migration History

| Migration File | Changes |
|----------------|---------|
| `install.sql` | Creates all core tables |
| `upgrade_120.sql` | (Minor upgrade) |
| `upgrade_200.sql` | Adds `parcelshop_data` to parcelshop table; creates `product_shipping_information`; adds `dpd_shipping_product` and `dpd_carrier_description` to `ps_product` |
| `upgrade_201.sql` | Additional column/index adjustments |

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
