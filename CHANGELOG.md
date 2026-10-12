# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.4.1] - 2026-07-29

### Security

- Verifying the callback secret is now read-only. The check went through a
  helper that generates a secret when none is stored, so an anonymous request
  to the endpoint could rotate it and invalidate every callback URL already
  handed to DPD, leaving those batches stuck without any error.

### Fixed

- A success callback without `shipment.trackingInfo.parcelNumbers` no longer
  crashes with a `TypeError` (which the surrounding `catch (Exception)` does not
  catch, leaving the job queued forever and DPD retrying). The job is now marked
  failed with an explanatory message.

### Changed

- PHPStan now analyses against the PHP 7.4 floor instead of 8.4. Note this does
  not catch calls to PHP 8-only stdlib functions: PHPStan resolves function
  existence from the runtime it runs on, not from `phpVersion`. Documented in
  the config so the limitation is not mistaken for coverage.

## [2.4.0] - 2026-07-28

### Security

- The asynchronous callback endpoint is now authenticated. It is a public front
  controller reachable at a completely predictable URL, and it accepted any
  request: a caller could overwrite the tracking number on any order, advance
  that order's status and trigger the shipment e-mail to the customer. A shared
  secret is now generated once, embedded in the callback URL handed to DPD and
  compared with `hash_equals()`.
- The callback no longer trusts the order id from the request body; it uses the
  order recorded on the stored job instead, so a callback can only ever affect
  the shipment it belongs to.
- Fixed SQL injection in the callback path: the job lookup interpolated the
  `jobid` from the request body into a query unescaped, and the resulting job id
  was concatenated into an `UPDATE` without a cast, which allowed a crafted
  callback to modify unrelated rows.

  Note when upgrading: batches that are still queued at upgrade time were handed
  the old callback URL without a token, so their callbacks are now rejected and
  those jobs stay queued. Regenerate their labels from the Batches screen.

### Fixed

- Parcel weights sent to DPD were overstated. Rounding happened before the
  kilogram-to-decagram conversion, so every weight was rounded up to a whole
  kilogram: a 400 g parcel was declared as 1 kg and a 50 g parcel as 1 kg
  (20x). Parcels of 31.4 kg were declared as 32 kg, over the DPD limit.
- The 31.5 kg overweight check compared kilograms against a decagram threshold
  and therefore only triggered at 3150 kg, meaning over-limit parcels were sent
  to DPD without a warning.
- The DPD tab on the product page threw a `TypeError` on PrestaShop 1.7 /
  PHP 7.4 because of a `mixed` parameter type hint, which PHP 7.4 resolves as a
  class name. Note that this is not a syntax error, so `php -l` did not catch it.
- The callback no longer fails on an unknown job id (it previously produced a
  SQL syntax error) and no longer processes an already-completed job twice,
  which duplicated the stored label.
- The callback now accepts a state sent as a JSON string. A string `"4"` failed
  the strict comparison, so the job silently stayed queued forever.

## [2.3.0] - 2026-07-17

### Added

- PHP 8.2, 8.3 and 8.4 compatibility: all dynamically created object properties
  are now explicitly declared (dynamic properties are deprecated since PHP 8.2
  and become a fatal error in PHP 9.0).
- Explicit PHP requirement (`>=7.4`) in `composer.json` and a runtime guard
  that prevents the module from loading on PHP older than 7.4.
- CI: PHP 7.4 and PHP 8.4 lint jobs and a PHPStan static-analysis job on merge
  requests.

### Changed

- PHP floor is now explicitly 7.4 (PrestaShop 1.7) — in practice this was
  already the minimum since typed properties were introduced in 2.x.
  PrestaShop 8 requires PHP 8.1+. `ps_versions_compliancy` now has an explicit
  upper bound (max 8.99.99) so the module no longer silently claims
  compatibility with future PrestaShop 9.
- The `dpdconnect/php-sdk` dependency is now pinned to the stable `^1.1`
  release line instead of the floating `dev-master` branch.
- Merged label PDF downloads now use the actively maintained `iio/libmergepdf`
  library instead of the unmaintained, PHP 7.1-era `myokyawhtun/pdfmerger`.
- CI: the PrestaShop 1.7 build job now runs on PHP 7.4 (was 7.1); release
  artifacts are built with `composer install --no-dev`.

### Fixed

- Settings validation used `str_contains()` (PHP 8.0+), which broke saving the
  module configuration on PrestaShop 1.7 / PHP 7.4. Replaced with a
  PHP 7.4-compatible check.

- Merged label PDF downloads were served with an incorrect
  `Content-Type: application/zip` header; they are now served as
  `application/pdf`.
- Temporary files created while merging label PDFs were never cleaned up; the
  merge now happens in memory.

## [2.2.3] - 2026-07-17

### Fixed

- Bulk label generation now updates the order on every order in the batch. The
  tracking number is written to the order and (when configured) the order status
  is advanced, matching single-label behaviour. Previously two separate defects
  caused bulk-generated labels to leave orders untouched:
  - In the synchronous path (batches below the async threshold), every API
    response was applied to the *last* order in the batch instead of its own
    order, so all other orders received neither a tracking number nor a status
    change.
  - In the asynchronous path (bulk batches at or above the threshold), the
    completion callback only stored the label PDF and never updated the order's
    tracking number or status at all.
- The tracking-number and status-update logic is now shared between the
  synchronous flow and the asynchronous callback so the two paths cannot diverge
  again.

## [2.2.2] - 2026-05-22

### Fixed

- Oversized legacy default package types (rejected by DPD with "Het pakket
  overschrijdt de maximaal toegestane afmetingen") are reset to a safe
  60×40×30 default on upgrade.
- Parcelshop map URL fix and removal of leftover debug logging.

## [2.2.1] - 2026-05-21

### Added

- Parcelshop data is now saved server-side at order creation via the
  `actionValidateOrder` hook (with a carrier check), so the parcelshop choice
  can no longer be lost between checkout and order.

### Fixed

- Label generation now uses the carrier product code.
- The async callback URL falls back to the shop base URL when the configured
  callback URL is empty, instead of producing a hostless relative URL.

## [2.1.1] - 2026-06-02

### Fixed

- Removed hardcoded default values for parcel weight and dimensions. The module
  no longer falls back to a fixed weight (`5.0`), the configured default product
  weight, or the configured default package type when product data is missing.
  These defaults were transmitted to DPD at hand-over, causing weight and
  dimension data that did not match the actual parcel to appear in DPD's systems
  before any physical measurement took place.
- Parcel volume is now computed from the actual product dimensions
  (width/height/depth, with unit conversion to centimeters) instead of a fixed
  default package type.
- Orders with missing weight or dimensions are now blocked at label creation with
  a clear, per-order error message ("Order #… skipped: missing product data …")
  instead of silently shipping with default values.
- Per-order validation errors no longer abort the entire batch; orders with
  complete data still get their labels generated.
- Guarded `count()` against a `null` `$errors` property to avoid a `TypeError`
  on PHP 8.
