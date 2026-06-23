# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.2.4] - 2026-06-23

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
