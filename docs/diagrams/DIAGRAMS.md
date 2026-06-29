<!--
DOCS_METADATA:
  generated_at: 2026-02-19T09:59:14Z
  git_hash: ea1640e
  tool_version: 1.0.0
  source_command: /create-documentation
-->

# Architecture & Flow Diagrams

<!-- AUTO-GENERATED:START - Do not edit manually -->

## Module Architecture Overview

```mermaid
graph TB
    subgraph "PrestaShop Core"
        PS_HOOKS[Hook System]
        PS_DB[(MySQL Database)]
        PS_CONFIG[Configuration Store]
        PS_ORDERS[Order Management]
    end

    subgraph "dpdconnect Module"
        MAIN[dpdconnect.php<br/>Module Entry Point]

        subgraph "Connect Layer"
            CONN[Connection.php<br/>API Client Factory]
            CACHE[DpdConnectCache]
            JWT[JWT Token Manager]
        end

        subgraph "Business Logic"
            LABELGEN[DpdLabelGenerator<br/>Label Orchestrator]
            CARRIER[DpdCarrier<br/>Carrier Manager]
            PREDICT[DpdParcelPredict<br/>Parcel Prediction]
            FRESH[FreshFreezeHelper<br/>Temp-controlled Shipping]
            SHIPLIST[DpdShippingList<br/>PDF Shipping List]
        end

        subgraph "Data Layer"
            LABELREPO[LabelRepo<br/>dpdshipment_label]
            BATCHREPO[BatchRepo<br/>dpd_batches]
            JOBREPO[JobRepo<br/>dpd_jobs]
            PRODHELPER[DpdProductHelper<br/>carrier_dpd_product]
        end

        subgraph "Admin Controllers"
            BULK[AdminDpdBulkActionsController]
            LABELS[AdminDpdLabelsController]
            BATCHES[AdminDpdBatchesController]
            FF[AdminDpdFreshFreezeController]
        end

        subgraph "Frontend Controllers"
            PS_SHOP[OneStepParcelshop<br/>AJAX Locator]
            CALLBACK[callback.php<br/>DPD Webhook]
        end

        ENCRYPT[DpdEncryptionManager<br/>AES-128-CBC]
    end

    subgraph "DPD Connect API"
        DPD_AUTH[Authentication / JWT]
        DPD_LABELS[Labels API]
        DPD_PRODUCTS[Products API]
        DPD_PARCELSHOP[Parcel Shop API]
    end

    PS_HOOKS --> MAIN
    MAIN --> CONN
    CONN --> ENCRYPT
    CONN --> JWT
    CONN --> CACHE
    CONN --> DPD_AUTH

    MAIN --> LABELGEN
    LABELGEN --> CONN
    LABELGEN --> LABELREPO
    LABELGEN --> BATCHREPO
    LABELGEN --> JOBREPO
    LABELGEN --> DPD_LABELS

    MAIN --> CARRIER
    CARRIER --> DPD_PRODUCTS
    CARRIER --> PRODHELPER

    MAIN --> PREDICT
    PREDICT --> CONN

    BULK --> LABELGEN
    BULK --> SHIPLIST
    LABELS --> LABELREPO
    BATCHES --> BATCHREPO
    FF --> FRESH

    PS_SHOP --> DPD_PARCELSHOP
    CALLBACK --> JOBREPO
    CALLBACK --> BATCHREPO
    CALLBACK --> LABELREPO

    LABELREPO --> PS_DB
    BATCHREPO --> PS_DB
    JOBREPO --> PS_DB
    PRODHELPER --> PS_DB
    CONN --> PS_CONFIG
```

---

## Label Generation Flow (Synchronous)

For small batches (below `dpdconnect_async_treshold`):

```mermaid
sequenceDiagram
    actor Merchant
    participant Orders as PS Order Grid
    participant Bulk as AdminDpdBulkActionsController
    participant LG as DpdLabelGenerator
    participant FF as FreshFreezeHelper
    participant LabelRepo as LabelRepo
    participant DPD as DPD API

    Merchant->>Orders: Select orders + click "Print DPD Labels"
    Orders->>Bulk: POST /dpdconnect/print-dpd-labels
    Bulk->>LG: generateLabel(orderIds, ...)
    LG->>FF: bundleOrders(orderIds)
    FF-->>LG: {orderId: {shippingType: [products]}}

    loop For each order + shipping type
        LG->>LabelRepo: getLabelOutOfDb(orderId)
        alt Label exists in DB
            LabelRepo-->>LG: PDF blob
        else Label not in DB
            LG->>LG: generateShipmentInfo(orderId, ...)
            LG->>DPD: POST shipment request
            DPD-->>LG: {mpsId, labelNumbers, labelData}
            LG->>LabelRepo: store label
        end
    end

    LG-->>Bulk: array of PDF blobs
    Bulk->>Merchant: Download merged PDF
```

---

## Label Generation Flow (Asynchronous)

For large batches (above `dpdconnect_async_treshold`):

```mermaid
sequenceDiagram
    actor Merchant
    participant Bulk as AdminDpdBulkActionsController
    participant LG as DpdLabelGenerator
    participant BatchRepo
    participant JobRepo
    participant DPD as DPD API
    participant CB as callback.php

    Merchant->>Bulk: POST /dpdconnect/print-dpd-labels (many orders)
    Bulk->>LG: generateLabel(orderIds, ...)
    LG->>BatchRepo: create(shipmentCount) → batchId
    loop For each order
        LG->>JobRepo: create(batchId, externalId, orderId, type) → jobId
        LG->>DPD: POST async shipment request
        DPD-->>LG: {externalId: "123"}
    end
    LG-->>Merchant: "Processing — check batch status"

    Note over DPD,CB: DPD processes in background
    DPD->>CB: POST /callback with result
    CB->>JobRepo: update(status: success/failed, label data)
    CB->>BatchRepo: updateStatus(job) — recalculate batch
    CB->>LabelRepo: store label
    CB->>PS: update order status (if configured)
```

---

## Checkout Parcel Shop Flow

```mermaid
sequenceDiagram
    actor Customer
    participant PS as PrestaShop Checkout
    participant Hook as displayAfterCarrier Hook
    participant Widget as DPD Parcel Shop Widget (JS)
    participant AJAX as OneStepParcelshop Controller
    participant DPD as DPD Parcel Shop API
    participant Session as PS Session / Cookie
    participant DB as parcelshop table

    Customer->>PS: Open checkout carrier step
    PS->>Hook: displayAfterCarrier
    Hook-->>PS: Render parcel shop locator widget

    Customer->>Widget: Enter address / use location
    Widget->>AJAX: POST address data
    AJAX->>DPD: GET nearby parcel shops
    DPD-->>AJAX: List of parcel shops (JSON)
    AJAX-->>Widget: Parcel shop list

    Customer->>Widget: Select parcel shop
    Widget->>Session: Store selected parcelshop_id + data in cookie

    Customer->>PS: Complete order
    PS->>Hook: actionCarrierProcess
    Hook->>Session: Read parcelshop data from cookie

    PS->>Hook: displayOrderConfirmation
    Hook->>DB: INSERT parcelshop (order_id, parcelshop_id, parcelshop_data)
```

---

## Carrier Lifecycle

```mermaid
stateDiagram-v2
    [*] --> NotCreated

    NotCreated --> Active : install() / createCarriers()
    Active --> SoftDeleted : uninstall() / deleteCarriers()
    SoftDeleted --> Active : install() / createCarriers()
    Active --> NewVersion : PrestaShop rate update
    NewVersion --> Active : getLatestCarrierByReferenceId()

    note right of SoftDeleted
        deleted=1 in ps_carrier
        id_reference preserved
    end note

    note right of NewVersion
        PS creates new carrier record
        with same id_reference
    end note
```

---

## Authentication & JWT Flow

```mermaid
sequenceDiagram
    participant Module as dpdconnect Module
    participant Enc as DpdEncryptionManager
    participant Config as PS Configuration
    participant CB as ClientBuilder
    participant DPD as DPD Auth API

    Module->>Config: get('dpdconnect_password') → encrypted
    Module->>Enc: decrypt(encrypted) → plain password
    Module->>CB: buildAuthenticatedByPassword(user, pass)
    CB->>Config: get('dpdconnect_jwt_token') → cached token
    CB-->>Module: Client with JWT

    Note over Module,DPD: On first API call or token expiry
    Module->>DPD: API request with JWT
    alt Token valid
        DPD-->>Module: Response
    else Token expired
        DPD-->>Module: 401 Unauthorized
        Module->>DPD: Authenticate (user + pass)
        DPD-->>Module: New JWT token
        Module->>Config: updateValue('dpdconnect_jwt_token', newToken)
        Module->>DPD: Retry API request
        DPD-->>Module: Response
    end
```

---

## Batch Status State Machine

```mermaid
stateDiagram-v2
    [*] --> status_queued : BatchRepo::create()

    status_queued --> status_processing : First job starts
    status_processing --> status_success : All jobs succeeded
    status_processing --> status_failed : All jobs failed
    status_processing --> status_partially_failed : Mix of success + failure

    note right of status_partially_failed
        successCount > 0 AND failureCount > 0
    end note
```

<!-- AUTO-GENERATED:END -->

<!-- MANUAL:START - Safe to edit, preserved on updates -->
<!-- MANUAL:END -->
