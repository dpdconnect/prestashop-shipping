<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Class Reference

<!-- AUTO-GENERATED:START - Do not edit manually -->

## Main Module Class

### `dpdconnect` (`dpdconnect.php`)

The PrestaShop module entry point. Extends `Module`.

**Key responsibilities**:
- Module lifecycle: `install()`, `uninstall()`
- Configuration form rendering and processing: `getContent()`
- Hook registration and dispatch
- Factory methods for service instances

**Key methods**:

| Method | Description |
|--------|-------------|
| `install()` | Creates DB tables, registers hooks, creates carriers |
| `uninstall()` | Deletes carriers (soft), removes hooks and config |
| `getContent()` | Renders and processes the module configuration page |
| `dpdCarrier()` | Returns `DpdCarrier` instance |
| `dpdLabelGenerator()` | Returns `DpdLabelGenerator` instance |
| `dpdShippingList()` | Returns `DpdShippingList` instance |
| `dpdDeliveryOptionsFinder(...)` | Returns `DpdDeliveryOptionsFinder` instance |

**Hook handler methods** are prefixed with `hook` — see [HOOKS.md](../hooks/HOOKS.md).

---

## `classes/Connect/`

### `Connection` (`classes/Connect/Connection.php`)

Builds and returns an authenticated DPD SDK client.

- Reads API URL, username, and encrypted password from PS configuration
- Decrypts password via `DpdEncryptionManager`
- Builds `DpdConnect\Sdk\Client` via `ClientBuilder`
- Attaches JWT caching: reads from config, updates config on refresh
- Exposes `getPublicJwtToken()` for token validation

### `Label` (`classes/Connect/Label.php`)

Wraps the DPD SDK label resource for API calls.

### `Product` (`classes/Connect/Product.php`)

Wraps the DPD SDK product list resource. Used to fetch available DPD shipping products from the API.

### `LabelRepo` (`classes/Connect/LabelRepo.php`)

Database repository for the `dpdshipment_label` table. Handles label storage and retrieval by order ID.

### `DpdConnectCache` (`classes/Connect/DpdConnectCache.php`)

Implements the SDK's cache callable interface. Adapts PrestaShop's cache mechanisms for use with the DPD SDK.

---

## `classes/` — Core Business Logic

### `DpdCarrier` (`classes/DpdCarrier.php`)

Manages PrestaShop carrier entities that correspond to DPD products.

| Method | Description |
|--------|-------------|
| `createCarriers()` | Fetches DPD products from API and creates/restores carriers |
| `deleteCarriers()` | Soft-deletes all DPD carriers (`deleted = 1`) |
| `createCarrier(array $dpdProduct)` | Creates a single PrestaShop carrier for a DPD product |
| `softDeleteCarriers($carrier_id)` | Marks a carrier as deleted |
| `unDeleteCarrier($carrier_id)` | Restores a soft-deleted carrier |
| `isSaturdayCarrier($carrierId)` | Checks if the carrier is a Saturday delivery carrier |
| `checkIfSaturdayAllowed()` | Checks current time against configured Saturday delivery window |
| `getLatestCarrierByReferenceId($id, $except_deleted)` | Gets the most recent carrier version for a reference ID |

**Note**: Carriers in PrestaShop are versioned — each rate change creates a new carrier record with the same `id_reference`. This class always works with `id_reference` and uses `getLatestCarrierByReferenceId()` to resolve to the actual current carrier.

### `DpdLabelGenerator` (`classes/DpdLabelGenerator.php`)

The main label generation orchestrator.

| Method | Description |
|--------|-------------|
| `generateLabel($orderIds, $parcelCount, $return, $volume, $freshFreezeData)` | Main entry point; generates labels for one or multiple orders |
| `generateShipmentInfo($orderId, ...)` | Builds the shipment request payload for one order |
| `getLabelOutOfDb($orderId, $return)` | Returns previously stored label from DB if available |

**Flow**:
1. Groups orders by shipping type (via `FreshFreezeHelper::bundleOrders`)
2. Checks DB for existing labels
3. Builds shipment info payloads
4. Calls DPD API (sync or async depending on `dpdconnect_async_treshold`)
5. Stores returned labels in `dpdshipment_label` table

### `DpdParcelPredict` (`classes/DpdParcelPredict.php`)

Handles parcel prediction and checkout data for displaying delivery estimates.

| Method | Description |
|--------|-------------|
| `checkIfDpdSending($orderId)` | Returns true if the order uses a DPD carrier |

Builds a DPD SDK client directly (like `DpdLabelGenerator`).

### `DpdProductHelper` (`classes/DpdProductHelper.php`)

Manages the mapping between PrestaShop carriers and DPD product codes in the `carrier_dpd_product` table.

| Method | Description |
|--------|-------------|
| `mapProductToCarrier(array $dpdProduct, string $carrierId)` | Creates a carrier ↔ DPD product mapping |
| `getCarrierByProduct(array $dpdProduct)` | Returns carrier record for a DPD product |
| `getProductByCarrier($carrierId)` | Returns DPD product record for a carrier |
| `getDpdCarriers()` | Returns all DPD carrier mappings |

### `DpdHelper` (`classes/DpdHelper.php`)

Utility class for building the module configuration form using PrestaShop's `HelperForm`.

### `DpdEncryptionManager` (`classes/DpdEncryptionManager.php`)

Symmetric encryption/decryption for API credentials.

- **Algorithm**: AES-128-CBC
- **Key**: `_COOKIE_KEY_` constant
- `encrypt(string $plain): string` — returns base64(`iv::ciphertext`)
- `decrypt(string $encoded): string` — reverses the above

### `DpdShippingList` (`classes/DpdShippingList.php`)

Generates a PDF shipping list for selected orders, using `HTMLTemplateDPDShippingList`.

### `DpdDeliveryOptionsFinder` (`classes/DpdDeliveryOptionsFinder.php`)

Finds and formats available DPD delivery options for the checkout flow.

### `DpdError` (`classes/DpdError.php`)

Maps DPD API error codes to human-readable messages.

### `FreshFreezeHelper` (`classes/FreshFreezeHelper.php`)

Utility for handling temperature-controlled shipping products.

**Product types**:

| Constant | Value | Meaning |
|----------|-------|---------|
| `TYPE_DEFAULT` | `default` | Standard shipping |
| `TYPE_FRESH` | `fresh` | Refrigerated shipping |
| `TYPE_FREEZE` | `freeze` | Frozen shipping |

| Method | Description |
|--------|-------------|
| `ordersContainFreshFreezeProducts($orderIds)` | Returns true if any order contains fresh/freeze products |
| `bundleOrders($orderIds)` | Groups order products by DPD shipping type |
| `getDefaultDate()` | Returns default delivery date (current date + 5 weekdays) |

### `BatchRepo` (`classes/BatchRepo.php`)

CRUD operations for the `dpd_batches` table.

| Method | Description |
|--------|-------------|
| `create($shipmentCount)` | Creates a new batch record, returns batch ID |
| `updateStatus($job)` | Recalculates and updates batch status from job results |

### `JobRepo` (`classes/JobRepo.php`)

CRUD operations for the `dpd_jobs` table.

| Method | Description |
|--------|-------------|
| `create($batchId, $externalId, $orderId, $type)` | Creates a job record, returns job ID |
| `get($id)` | Retrieves a job by ID |
| `update(...)` | Updates job status and result data |

### `OrderResponseTransformer` (`classes/OrderResponseTransformer.php`)

Transforms DPD API order/shipment response objects into the format expected by the module's label storage.

### `Version` (`classes/Version.php`)

Provides version metadata for API calls.

| Method | Returns |
|--------|---------|
| `type()` | `"Prestashop"` |
| `webshop()` | `_PS_VERSION_` |
| `plugin()` | `dpdconnect::VERSION` |

---

## `classes/enums/`

### `JobStatus`

Constants and HTML tag renderer for job lifecycle states.

**States**: `STATUSREQUEST`, `STATUSQUEUED`, `STATUSPROCESSING`, `STATUSSUCCESS`, `STATUSFAILED`

### `BatchStatus`

Constants and HTML tag renderer for batch lifecycle states.

**States**: All of `JobStatus` plus `STATUSPARTIALLYFAILED`

### `ParcelType`

| Constant | Value | Usage |
|----------|-------|-------|
| `TYPEREGULAR` | `1` | Standard outbound |
| `TYPERETURN` | `2` | Return label |
| `TYPESATURDAY` | `3` | Saturday delivery |

`parse($return, $manualSaturday)` derives the type from boolean flags.

---

## `classes/Service/`

### `SettingsDataValidator` (`classes/Service/SettingsDataValidator.php`)

Validates module settings values before they are persisted. Called during `getContent()`.

---

## `classes/pdf/`

### `HTMLTemplateDPDShippingList`

Extends PrestaShop's `HTMLTemplate` to render a DPD-branded shipping list as a printable PDF.

---

## `src/` — Symfony Components (PS8)

### `ProductShippingInformation` (`src/Entity/ProductShippingInformation.php`)

Doctrine ORM entity mapping to the `product_shipping_information` table.

| Property | Column | Description |
|----------|--------|-------------|
| `id` | `id` | Auto-increment PK |
| `productId` | `product_id` | PrestaShop product ID |
| `dpdShippingProduct` | `dpd_shipping_product` | Shipping type (`default`/`fresh`/`freeze`) |
| `dpdCarrierDescription` | `dpd_carrier_description` | Carrier description override |

### `ProductFormModifier` (`src/Form/Modifier/ProductFormModifier.php`)

Symfony service that hooks into PrestaShop's product form builder to inject DPD-specific fields (via `actionProductFormBuilderModifier` hook).

### `DpdType` (`src/Form/Type/DpdType.php`)

Symfony Form type defining the DPD fields rendered on the product admin page.

### `FreshFreezeService` (`src/Service/FreshFreezeService.php`)

Service layer for Fresh/Freeze order management. Registered as `dpdconnect.fresh_freeze_service` in `config/services.yml`.

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
