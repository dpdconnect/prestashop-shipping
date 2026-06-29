<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Configuration Reference

<!-- AUTO-GENERATED:START - Do not edit manually -->

All settings are stored in PrestaShop's `ps_configuration` table via `Configuration::updateValue()` and retrieved via `Configuration::get()`.

## API Credentials

| Key | Description | Type | Notes |
|-----|-------------|------|-------|
| `dpdconnect_url` | DPD Connect API base URL | string | e.g. `https://api.dpdconnect.nl` |
| `dpdconnect_username` | DPD Connect account username | string | Plain text |
| `dpdconnect_password` | DPD Connect account password | string | Encrypted (AES-128-CBC) |
| `dpdconnect_jwt_token` | Cached JWT authentication token | string | Auto-refreshed on expiry |

## Sender Address

| Key | Description | Type |
|-----|-------------|------|
| `dpdconnect_company` | Sender company name | string |
| `dpdconnect_street` | Sender street address | string |
| `dpdconnect_postalcode` | Sender postal code | string |
| `dpdconnect_place` | Sender city | string |
| `dpdconnect_country` | Sender country code (ISO 2) | string |
| `dpdconnect_email` | Sender email address | string |
| `dpdconnect_depot` | DPD depot number | string |
| `dpdconnect_vatnumber` | Sender VAT number | string |

## Label & Shipping Settings

| Key | Description | Type | Default |
|-----|-------------|------|---------|
| `dpdconnect_labelformat` | Label output format | string | e.g. `PDF` |
| `dpdconnect_merge_pdf_files` | Merge multiple labels into one PDF | bool | |
| `dpdconnect_default_package_type` | Default package type for label generation | string | |
| `dpdconnect_parcel_limit` | Maximum parcels per shipment | int | `12` |
| `dpdconnect_spr` | Sender pays return (SPR) flag | bool | |
| `dpdconnect_eorinumber` | EORI number for customs | string | |

## Product & Customs Settings

| Key | Description | Type |
|-----|-------------|------|
| `dpdconnect_default_product_weight` | Default product weight when none set | float |
| `dpdconnect_default_product_country_of_origin` | Default country of origin (ISO 2) | string |
| `dpdconnect_default_product_hcs` | Default HS code | string |
| `dpdconnect_age_check_attribute` | Product attribute ID used for age check | int |
| `dpdconnect_customs_value_feature` | Product feature ID for customs value | int |
| `dpdconnect_hs_code_feature` | Product feature ID for HS code | int |

## Parcel Shop / Maps

| Key | Description | Type |
|-----|-------------|------|
| `dpdconnect_maps_key` | Google Maps API key (for parcel shop locator) | string |
| `dpdconnect_use_dpd_key` | Use DPD's built-in map key instead of custom | bool |

## Async Processing

| Key | Description | Type |
|-----|-------------|------|
| `dpdconnect_async_treshold` | Number of orders above which async batch processing is used | int |
| `dpdconnect_callback_url` | Webhook URL for DPD to call back with job results | string |

## Order Status

| Key | Description | Type |
|-----|-------------|------|
| `dpdconnect_mark_status` | Order status ID to apply when label is generated | int |

## Saturday Delivery

| Key | Description | Type |
|-----|-------------|------|
| `dpdconnect_saturday_showfromday` | Day of week from which Saturday delivery option appears | string |
| `dpdconnect_saturday_showfromtime` | Time (HH:MM) from which Saturday delivery option appears | string |
| `dpdconnect_saturday_showtillday` | Day of week until which Saturday delivery option appears | string |
| `dpdconnect_saturday_showtilltime` | Time (HH:MM) until which Saturday delivery option appears | string |

## Encryption

API passwords are encrypted using `DpdEncryptionManager` before storage:

- **Algorithm**: AES-128-CBC
- **Key**: PrestaShop's `_COOKIE_KEY_` constant
- **Storage**: Base64-encoded `iv::ciphertext`

> Changing `_COOKIE_KEY_` will invalidate stored encrypted passwords and require re-entering credentials.

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
