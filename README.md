# Combination Descriptions — PrestaShop 8.x module

A PrestaShop **8.x** module that adds a **per-combination product description**
(a field PrestaShop has no native equivalent for) and exposes it through the
**legacy Webservice API**, so an external tool — e.g. an Excel importer — can
bulk-load descriptions.

Tested against **PrestaShop 8.2.3** (PHP 8.1+). Declared compatibility: 1.7.6 → 8.x.

> The installable module lives in [`combinationdescriptions/`](combinationdescriptions/).
> Drop that folder into your shop's `modules/` directory (or zip it and upload
> via **Modules → Upload a module**).

---

## Features

- Long **description** + **short description** per combination (`id_product_attribute`),
  both HTML, multilang, and per-shop.
- Full Webservice resource **`combination_descriptions`** — `GET / POST / PUT / DELETE`.
- **Idempotent** writes: re-POSTing the same `id_product_attribute` + `id_shop`
  updates the row in place instead of creating a duplicate (also enforced by a
  unique index).
- Back-office screen under **Catalog → Combination Descriptions**: product search,
  per-combination TinyMCE editors with per-language tabs, and bulk copy / clear.
- Storefront: renders the right description and swaps it live on PrestaShop's
  `updatedProduct` event — no extra AJAX per combination change. Empty
  combinations fall back to the parent product description.
- Per-product JSON blob cached via PrestaShop's `Cache`, invalidated on save.

---

## Try it in Docker (turnkey shop, one line)

Spin up a full **PrestaShop 8.2.3 with this module pre-installed** — no existing
shop required. Pulls the prebuilt image from GHCR and starts everything:

```bash
curl -sSL https://raw.githubusercontent.com/sh1njure/30yearsexperience/claude/prestashop-combination-descriptions-9mo86z/docker-compose.yml | docker compose -f - up -d
```

Then:

- Storefront: <http://localhost:8080>
- Back office: <http://localhost:8080/admin1> — login `admin@example.com` / `prestashop123`

A one-shot `module-installer` container waits for PrestaShop to finish its
first-boot install, then enables the module automatically (first boot takes a
couple of minutes). Watch it with `docker compose logs -f module-installer`.

Stop / wipe:

```bash
docker compose down          # stop, keep data
docker compose down -v       # stop and delete all data
```

<details>
<summary>Build the image locally instead of pulling</summary>

```bash
git clone -b claude/prestashop-combination-descriptions-9mo86z https://github.com/sh1njure/30yearsexperience.git
cd 30yearsexperience
docker compose -f docker-compose.yml -f docker-compose.build.yml up -d --build
```
</details>

> The image is published by the [`docker-publish`](.github/workflows/docker-publish.yml)
> GitHub Actions workflow to `ghcr.io/sh1njure/30yearsexperience`. After the
> first CI run, make that package **Public** (GitHub → Packages → package
> settings) so the one-liner works without `docker login`.

---

## Install into an existing PrestaShop

**One-liner** — pull + install + clear cache (set `PS` to your shop path):

```bash
PS=/var/www/prestashop; git clone -b claude/prestashop-combination-descriptions-9mo86z --depth 1 https://github.com/sh1njure/30yearsexperience.git /tmp/cd-mod && sudo cp -r /tmp/cd-mod/combinationdescriptions "$PS/modules/" && sudo chown -R www-data:www-data "$PS/modules/combinationdescriptions" && (cd "$PS" && sudo -u www-data php bin/console prestashop:module install combinationdescriptions && sudo -u www-data php bin/console cache:clear --no-warmup)
```

<details>
<summary>Same thing, step by step</summary>

```bash
# 1. copy the module into your shop
cp -r combinationdescriptions /path/to/prestashop/modules/

# 2. install it
cd /path/to/prestashop
php bin/console prestashop:module install combinationdescriptions

# 3. clear cache
php bin/console cache:clear --no-warmup
```
</details>

Then, in the admin panel, enable the Webservice and tick the
`combination_descriptions` permission on your API key
(**Advanced Parameters → Webservice**).

### Example: POST one description

```bash
curl -u "YOUR_API_KEY:" -X POST \
  "https://your-shop.com/api/combination_descriptions" \
  -H "Content-Type: text/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
  <combination_description>
    <id_product_attribute>12</id_product_attribute>
    <id_shop>1</id_shop>
    <description>
      <language id="1"><![CDATA[<p>Slim-fit red T-shirt, size M.</p>]]></language>
    </description>
    <description_short>
      <language id="1"><![CDATA[<p>Red / M</p>]]></language>
    </description_short>
  </combination_description>
</prestashop>'
```

---

## Documentation

- **Full module guide** (install, enabling the Webservice permission, JSON
  structure, curl examples, multishop notes):
  [`combinationdescriptions/README.md`](combinationdescriptions/README.md)
- **Importer spec** (spreadsheet column → field map + example XML payload for an
  Excel importer):
  [`combinationdescriptions/docs/importer-spec.md`](combinationdescriptions/docs/importer-spec.md)

---

## Repository layout

```
combinationdescriptions/
├── combinationdescriptions.php                     # main module (hooks, install/uninstall, settings)
├── config.xml
├── logo.png
├── src/Entity/CombinationDescription.php           # ObjectModel + Webservice params
├── controllers/admin/AdminCombinationDescriptionsController.php
├── views/templates/  views/js/  views/css/         # BO screen + front-office swapper
├── sql/install.php  sql/uninstall.php              # schema create / drop
├── docs/importer-spec.md
└── README.md
```

## License

MIT.
