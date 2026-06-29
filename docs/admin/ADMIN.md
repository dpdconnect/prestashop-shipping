<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Admin Controllers & Routes

<!-- AUTO-GENERATED:START - Do not edit manually -->

## Admin Controllers (`controllers/admin/`)

### `AdminDpdLabelsController`

Handles individual label generation and download from the order view.

**Actions**:
- Generate a single label for an order
- Download an existing label
- Generate a return label

---

### `AdminDownloadLabelController`

Provides the label file download endpoint. Streams the label PDF to the browser.

---

### `AdminDpdBulkActionsController`

Handles bulk operations triggered from the order grid. Registered via Symfony routes.

**Routes** (from `config/routes.yml`):

| Route name | HTTP Method | Path | Action |
|------------|-------------|------|--------|
| `dpdconnect_bulk_actions_shipping_list_dpd` | POST | `/dpdconnect/shipping-list-dpd` | `processBulkShippingListDPD` |
| `dpdconnect_bulk_actions_print_dpd_labels` | POST | `/dpdconnect/print-dpd-labels` | `processPrintDPDLabels` |
| `dpdconnect_bulk_actions_print_dpd_return_labels` | POST | `/dpdconnect/print-dpd-return-labels` | `processPrintDPDReturnLabels` |

**Actions**:
- `processBulkShippingListDPD` — Generates and downloads a PDF shipping list for selected orders
- `processPrintDPDLabels` — Generates and downloads labels for selected orders
- `processPrintDPDReturnLabels` — Generates and downloads return labels for selected orders

---

### `AdminDpdBatchesController`

Displays the DPD batches management page. Shows the list of batch label generation jobs with their statuses.

Accessible from: **Admin → DPD Batches**

---

### `AdminDpdJobsController`

Displays individual jobs within a batch. Shows per-order job status, errors, and state messages.

---

### `AdminDpdFreshFreezeController`

Handles the Fresh & Freeze delivery scheduling interface.

**Route**:

| Route name | HTTP Method | Path | Action |
|------------|-------------|------|--------|
| `dpdconnect_fresh_freeze` | GET | `/dpdconnect/fresh-freeze` | `renderView` |

---

### `AdminDpdShippingListController`

Generates a printable shipping list for a set of orders.

---

### `AdminDpdProductAttributesController`

Manages DPD-specific product attributes used for customs declarations on international shipments.

**Editable fields**:
- HS Code (Harmonized System tariff code)
- Country of Origin
- Customs Value
- Age Check requirement

Accessible from the product management grid.

---

## Frontend Controllers (`controllers/front/`)

### `OneStepParcelshop` (`controllers/front/OneStepParcelshop.php`)

AJAX endpoint used by the parcel shop locator widget in the checkout. Returns available parcel shop locations based on the customer's address.

**Request**: POST with address data
**Response**: JSON list of nearby parcel shops

### `callback` (`controllers/front/callback.php`)

DPD callback endpoint. DPD calls this URL to update the status of async label generation jobs.

**Flow**:
1. DPD POSTs job result to `dpdconnect_callback_url`
2. Controller validates the request
3. Updates `dpd_jobs` record via `JobRepo`
4. Triggers `BatchRepo::updateStatus()` to recalculate batch status
5. If job succeeded: stores label in `dpdshipment_label`
6. Optionally updates PrestaShop order status to `dpdconnect_mark_status`

---

## Module Configuration Page

Accessible via **Modules → Module Manager → DPD Connect → Configure** (or `AdminModules` with `configure=dpdconnect`).

The configuration page is rendered by `dpdconnect::getContent()` using `DpdHelper::displayConfigurationForm()`.

**Form sections**:

1. **Account Settings** — API URL, username, password
2. **Sender Address** — Company, street, postal code, city, country, email, depot, VAT
3. **Product Settings** — Weight, country of origin, HS code defaults, age check, customs features
4. **Advanced Settings** — Label format, EORI, SPR, async threshold, callback URL, order status, merge PDF, maps key, package type

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
