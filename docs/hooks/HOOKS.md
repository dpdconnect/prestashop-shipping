<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Hooks Reference

<!-- AUTO-GENERATED:START - Do not edit manually -->

The module registers the following hooks. Each hook is defined in `dpdconnect.php::$hooks` and handled by a corresponding `hook*()` method.

## Admin Hooks

### `displayAdminOrderTabLink`

**Handler**: `hookDisplayAdminOrderTabLink($params)`

Adds a DPD tab link to the order detail page in the PrestaShop back office. Allows merchants to view DPD label information directly from the order.

---

### `displayAdminOrderTabContent`

**Handler**: `hookDisplayAdminOrderTabContent($params)`

Renders the content of the DPD tab on the order detail page. Shows:
- Existing labels for the order (with download links)
- Return label information
- Job/batch status if async processing was used

Templates: `views/templates/admin/_adminOrderTab.tpl`, `_adminOrderTabLabels.tpl`

---

### `actionCarrierProcess`

**Handler**: `hookActionCarrierProcess($params)`

Triggered during the checkout carrier selection step. Used to capture parcel shop selections and store them in the `parcelshop` table.

---

### `actionDispatcher`

**Handler**: `hookActionDispatcher($params)`

A broad hook fired on every dispatch. Used to detect admin actions that require DPD carriers to be updated (e.g., after module configuration changes).

---

### `actionProductFormBuilderModifier`

**Handler**: `hookActionProductFormBuilderModifier($params)`

Injects DPD-specific fields into the product creation/edit form in the admin. Delegates to `ProductFormModifier::buildForm()`.

**Added fields** (via `DpdType`):
- DPD shipping product type (default / fresh / freeze)
- DPD carrier description

---

### `actionAfterUpdateProductFormHandler`

**Handler**: `hookActionAfterUpdateProductFormHandler(array $params)`

Processes and saves DPD-specific product fields after the product form is submitted. Persists data to `product_shipping_information` via Doctrine.

---

### `actionOrderGridDefinitionModifier`

**Handler**: `hookActionOrderGridDefinitionModifier(array $params)`

Adds DPD-specific columns and bulk actions to the PrestaShop orders grid:

- **Columns added**: Label download button, return label button, batch/job status indicator
- **Bulk actions added**: Generate DPD labels, print return labels, generate shipping list

---

### `displayBackOfficeHeader`

**Handler**: `hookDisplayBackOfficeHeader()`

Injects module CSS and JavaScript into the admin back office header.

---

## Checkout Hooks

### `actionCheckoutRender`

**Handler**: `hookActionCheckoutRender($params)`

Fired during checkout rendering. Used to inject parcel shop data and configuration into the checkout context.

---

### `displayAfterCarrier`

**Handler**: `hookDisplayAfterCarrier(array $params)`

Renders additional content after the carrier selection list in checkout. This is where the parcel shop locator widget is displayed when a DPD parcel shop carrier is selected.

**Template**: `views/templates/8/_dpdLocator8.tpl` (PS8) / `views/templates/_dpdLocator.tpl` (PS1.7)

**Configuration injected**:
- `dpdParcelshopMapUrl` — URL for the parcel shop map JS
- Selected carrier type
- Google Maps key (or DPD key depending on `dpdconnect_use_dpd_key`)
- Default package type from configuration

---

## Order Confirmation Hooks

### `displayOrderConfirmation`

**Handler**: `hookDisplayOrderConfirmation($params)`

Fired on the order confirmation page. Used to save parcel shop data from the session cookie into the `parcelshop` database table, associating it with the confirmed order.

---

## Hook Registration

Hooks are registered during `install()` via `registerHook()` for each entry in `$this->hooks`. They are unregistered automatically during `uninstall()`.

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
