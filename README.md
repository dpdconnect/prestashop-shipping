<div align="center">

<img src="logo.png" width="130" alt="DPD Connect" />

<h1>DPD Connect for PrestaShop 8</h1>

<p>
  <img src="https://img.shields.io/badge/PHP-7.1%2B-777BB4?style=flat-square&logo=php&logoColor=white&labelColor=474A8A" alt="PHP 7.1+" />
  &nbsp;
  <img src="https://img.shields.io/badge/PrestaShop-1.7%2B-DF0067?style=flat-square&logo=prestashop&logoColor=white&labelColor=4D4F5C" alt="PrestaShop 1.7+" />
  &nbsp;
  <img src="https://img.shields.io/badge/License-GPL--3.0-0072B8?style=flat-square&labelColor=555555" alt="License GPL-3.0" />
  &nbsp;
  <img src="https://img.shields.io/badge/Version-2.1-2ea44f?style=flat-square&labelColor=555555" alt="Version 2.1" />
</p>

<p>
  <strong>Integrate DPD parcel shipping directly into your PrestaShop store.</strong><br>
  Generate labels, offer Parcelshop pickup at checkout, and monitor async batches — all from the PrestaShop admin.
</p>

<p>
  <a href="#features">Features</a> &nbsp;·&nbsp;
  <a href="#requirements">Requirements</a> &nbsp;·&nbsp;
  <a href="#installation">Installation</a> &nbsp;·&nbsp;
  <a href="#configuration">Configuration</a> &nbsp;·&nbsp;
  <a href="#development">Development</a>
</p>

</div>

---

<a name="features"></a>

## 📦 Features

### 🏷️ Label Generation

| Feature | Description |
|---------|-------------|
| **Single label** | Generate from any order page via the order actions tab |
| **Bulk labels** | Select multiple orders from the orders grid and process in one action |
| **Return labels** | Generated and stored separately from outbound shipping labels |
| **Fresh & Freeze** | Temperature-controlled shipments with per-product shipping type assignment |
| **Multi-parcel** | Split a single order across multiple parcels via the parcel count input |

### 🗺️ Delivery Options

| Feature | Description |
|---------|-------------|
| **DPD Classic** | Standard delivery via DPD carriers synced from the Connect API |
| **DPD Parcelshop** | Embedded Google Maps picker at checkout — customer chooses a pickup point |
| **DPD Predict** | Home delivery with email notification for B2C and B2B MSG products |
| **Saturday delivery** | Saturday carrier shown automatically within the configured day/time window |

### ⚙️ Admin & Operations

| Feature | Description |
|---------|-------------|
| **Batch processing** | Large order sets processed asynchronously via a batch/job queue |
| **Batch monitor** | Dedicated admin page showing batch and per-job status with error details |
| **Shipping list** | Generate a printable PDF shipping list for selected orders in bulk |
| **Carrier sync** | DPD carriers are created automatically from the Connect API product list |
| **Order grid columns** | Label download, return label, and job status columns directly in the orders grid |

### 🌍 International Shipping

| Feature | Description |
|---------|-------------|
| **Customs declarations** | Attach HS codes, country of origin, and customs value per product |
| **EORI number** | Configurable EORI number sent with all international shipments |
| **Age check** | Product-level age verification flag included in the shipment request |
| **Per-product attributes** | Managed via a dedicated product attribute screen in the admin |

---

<a name="requirements"></a>

## 🛠️ Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 7.1+ (PrestaShop 1.7) / 8.1+ (PrestaShop 8) |
| PrestaShop | 1.7.x or 8.x |
| MySQL / MariaDB | As required by PrestaShop |
| OpenSSL PHP extension | Required for credential encryption |
| DPD Connect account | Credentials provided by DPD Nederland B.V. |

---

<a name="installation"></a>

## 🚀 Installation

### Via Back Office (recommended)

1. Download the latest `dpdconnect.zip` from the Releases page or build it yourself (see below).
2. Go to **Modules → Module Manager → Upload a module**.
3. Upload `dpdconnect.zip` and click **Install**.
4. After installation, click **Configure** to enter your DPD credentials.

### Via CLI

```bash
php bin/console prestashop:module install dpdconnect
```

### Building from source

```bash
git clone <repository-url> dpdconnect
cd dpdconnect
composer install --no-dev
zip -r dpdconnect.zip . --exclude ".git/*" --exclude ".gitlab-ci.yml" --exclude "tests/*" --exclude "docs/*"
```

> Database migrations are applied automatically by PrestaShop's upgrade system from the SQL files in `install/`.

---

<a name="configuration"></a>

## ⚙️ Configuration

After installation, go to **Modules → Module Manager → DPD Connect → Configure**.

### Account Settings

| Field | Description |
|-------|-------------|
| **API URL** | DPD Connect API endpoint (provided by DPD) |
| **Username** | Your DPD Connect account username |
| **Password** | Your DPD Connect account password (stored AES-128-CBC encrypted) |

### Sender Address

Fill in your company name, street, postal code, city, country, email address, depot number, and VAT number. These are used as the sender details on every generated label.

### Product Settings

| Field | Description |
|-------|-------------|
| **Default weight** | Fallback weight (in grams) when a product has no weight set |
| **Default country of origin** | ISO 2-letter country code used for customs when none is set on the product |
| **Default HS code** | Fallback Harmonized System code for customs |
| **Age check attribute** | Product attribute ID that triggers age verification on the label |
| **Customs value feature** | Product feature ID used to read the declared customs value |
| **HS code feature** | Product feature ID used to read the per-product HS code |

### Advanced Settings

| Field | Description |
|-------|-------------|
| **Label format** | Output format for generated labels (e.g. PDF) |
| **Merge PDFs** | Combine multiple labels into a single downloadable PDF |
| **Default package type** | Default packaging type sent with shipment requests |
| **EORI number** | European customs registration number |
| **Async threshold** | Orders above this count use asynchronous batch processing |
| **Callback URL** | Publicly accessible URL where DPD posts async job results |
| **Order status on label** | PS order status to apply automatically when a label is generated |
| **Maps key** | Google Maps API key for the parcel shop locator widget |
| **Use DPD map key** | Use DPD's built-in map key instead of a custom Google Maps key |

> Full configuration reference: [docs/architecture/CONFIGURATION.md](docs/architecture/CONFIGURATION.md)

---

<a name="development"></a>

## 💻 Development

### Local setup

```bash
# Clone into a folder named exactly 'dpdconnect'
git clone <repository-url> dpdconnect
cd dpdconnect

# Install dependencies
composer install

# Symlink into your PrestaShop installation
ln -s $(pwd) /path/to/prestashop/modules/dpdconnect
```

### Static analysis

```bash
cd tests/phpstan
composer install
vendor/bin/phpstan analyse --configuration=phpstan.neon
```

### CI/CD

GitLab CI builds two artifacts on every push:

| Job | PHP | Target |
|-----|-----|--------|
| `prestashop17` | 7.1 | PrestaShop 1.7 |
| `prestashop8` | 8.1 | PrestaShop 8 |

Both produce a `dpdconnect.zip` artifact retained for 14 days.

### Documentation

Full developer documentation is in the [`docs/`](docs/README.md) folder:

| Document | Description |
|----------|-------------|
| [Architecture](docs/architecture/ARCHITECTURE.md) | Module structure, patterns, directory layout |
| [Database schema](docs/database/DATABASE.md) | All custom tables and columns |
| [Class reference](docs/classes/CLASSES.md) | Every class with methods and responsibilities |
| [Hook reference](docs/hooks/HOOKS.md) | All 12 registered PrestaShop hooks |
| [Admin controllers](docs/admin/ADMIN.md) | Back office controllers and Symfony routes |
| [Diagrams](docs/diagrams/DIAGRAMS.md) | Architecture and flow diagrams |
| [Troubleshooting](docs/support/TROUBLESHOOTING.md) | Common issues and resolution guide |

---
