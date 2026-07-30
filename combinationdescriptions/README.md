# Combination Descriptions (PrestaShop 8.x)

Adds a **per-combination description** to PrestaShop — a field the platform has
no native equivalent for — and exposes it through the **legacy Webservice API**
so an external tool (e.g. an Excel importer) can bulk-load descriptions.

Tested against **PrestaShop 8.2.3** (PHP 8.1+). Compatible range declared as
1.7.6 → 8.x.

---

## What it does

- Stores a long **description** and a **short description** (both HTML,
  multilang, per-shop) for every product combination (`id_product_attribute`).
- Exposes them as a Webservice resource **`combination_descriptions`** with full
  `GET / POST / PUT / DELETE`.
- Renders the right description on the storefront and swaps it live (no AJAX)
  when the customer changes a combination.
- Provides a back-office screen under **Catalog → Combination Descriptions** to
  edit descriptions with TinyMCE, per language, with bulk copy/clear actions.

---

## Install

1. Copy the `combinationdescriptions/` folder into your shop's `modules/`
   directory (or zip the folder and upload it via **Modules → Upload a module**).
2. Go to **Modules → Module Manager**, find *Combination Descriptions*, click
   **Install**.
3. On install the module:
   - creates `PREFIX_combination_description` and
     `PREFIX_combination_description_lang`,
   - registers the `addWebserviceResources` hook and the front-office display
     hook,
   - adds the **Catalog → Combination Descriptions** admin tab,
   - sets `CD_KEEP_DATA_ON_UNINSTALL = 1` (your data survives an uninstall by
     default; a reinstall keeps it).

### Settings (Module → Configure)

| Setting | Default | Meaning |
|---|---|---|
| **Front-office display hook** | `displayProductAdditionalInfo` | The hook used to render descriptions on the product page. Change it if your theme uses a different hook (e.g. `displayFooterProduct`). Changing it re-registers the module on the new hook. |
| **Keep data on uninstall** | Yes | When on, uninstalling leaves the tables in place so a reinstall keeps existing descriptions. Turn it off to have uninstall drop the tables. |

---

## Enable the Webservice resource permission

1. **Advanced Parameters → Webservice** → enable the Webservice, then
   **Add new webservice key** (or edit an existing key).
2. In the key's **Permissions** list you'll now see a
   **`combination_descriptions`** row (it appears because the module registers
   the `addWebserviceResources` hook).
3. Tick the columns you need — **View (GET)**, **Modify (PUT)**,
   **Add (POST)**, **Delete (DELETE)** — and **Save**.
4. Copy the generated API **key** (used as the HTTP Basic *username*, empty
   password).

---

## JSON / data model the API expects

Each row is one combination description:

| Field | Type | Notes |
|---|---|---|
| `id_product_attribute` | int, **required** | The combination. Unique together with `id_shop`. Exposed as an associated `combinations` resource. |
| `id_product` | int | Denormalized parent product. Filled automatically from the combination if omitted. |
| `id_shop` | int | Defaults to the key's/context shop if omitted. |
| `description` | HTML, multilang | Long description. Wrapped per language. |
| `description_short` | HTML, multilang | Short description. Wrapped per language. |
| `date_add` / `date_upd` | datetime | Managed automatically. |

**Idempotency:** a `POST` for an `id_product_attribute` + `id_shop` that already
has a row **updates that row** instead of creating a duplicate (also enforced by
a unique index). So the importer can safely re-run.

The front office consumes descriptions as this per-product blob (also the shape
returned by `CombinationDescription::getDescriptionsForProduct()`), embedded in
the product page and used by the live-swap JS:

```json
{
  "12": { "description": "<p>Red / M …</p>", "description_short": "<p>Red / M</p>" },
  "13": { "description": "<p>Red / L …</p>", "description_short": "<p>Red / L</p>" }
}
```
(keys are `id_product_attribute`.)

---

## Curl: POST one description

Get the blank schema first (shows the exact XML the resource accepts, including
the multilang wrapping):

```bash
curl -u "YOUR_API_KEY:" \
  "https://your-shop.example.com/api/combination_descriptions?schema=blank"
```

Create/update a description for combination `12` (shop 1), in language `1`:

```bash
curl -u "YOUR_API_KEY:" \
  -X POST \
  "https://your-shop.example.com/api/combination_descriptions" \
  -H "Content-Type: text/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
  <combination_description>
    <id_product_attribute>12</id_product_attribute>
    <id_shop>1</id_shop>
    <description>
      <language id="1"><![CDATA[<p>Slim-fit red T-shirt, size M. 100% combed cotton.</p>]]></language>
    </description>
    <description_short>
      <language id="1"><![CDATA[<p>Red / M</p>]]></language>
    </description_short>
  </combination_description>
</prestashop>'
```

- **HTTP 201** on create, **200** on update.
- Re-POSTing the same `id_product_attribute` + `id_shop` updates in place (no
  duplicate).
- `PUT` to `/api/combination_descriptions/{id}` updates by primary key.
- `GET /api/combination_descriptions?filter[id_product]=45` lists all rows for a
  product; `filter[id_product_attribute]=12` filters by combination.

---

## Front office behaviour

- The module prints, on the product page, a hidden block plus a JSON blob of all
  the product's combination descriptions.
- `views/js/front.js` listens for PrestaShop's **`updatedProduct`** event and
  swaps the shown text from the blob — no extra AJAX per combination change.
- **Fallback:** if the selected combination has no description, the block is
  hidden and the theme's own product description is left untouched. An empty
  block is never rendered.
- All HTML is purified server-side (`Tools::purifyHTML`) before output.

---

## Caching & performance

- The per-product blob is cached through PrestaShop's `Cache` layer
  (`_PS_CACHE_ENABLED_` gated), keyed by product + shop + lang.
- The cache is invalidated automatically on every add/update/delete of a
  description.
- Indexes: `UNIQUE (id_product_attribute, id_shop)` and
  `(id_product, id_shop)` — the front office queries by product, not by
  combination.

---

## Multishop notes

The ObjectModel uses `multilang => true` + `multilang_shop => true`. In
PrestaShop 8.x `multilang_shop` is the flag that makes language rows
**shop-dependent** — it puts the `id_shop` column in the `_lang` table and writes
one lang row per `(id_lang, id_shop)`. That is the requested
"multilang + multishop" behaviour, achieved without a separate
`{table}_shop` association table: the schema denormalizes `id_shop` directly into
the main table (and it's a filterable Webservice field). If you specifically need
the classic `_shop` association-table pattern instead, that's a small change to
the definition and SQL — open an issue.

---

## Uninstall

- With **Keep data on uninstall = Yes** (default): removes the tab and hooks but
  keeps the tables — reinstalling restores everything.
- With **Keep data on uninstall = No**: also drops both tables and deletes the
  module configuration.

---

## Developer notes

- Entity: `src/Entity/CombinationDescription.php` — a **global-namespace**
  `ObjectModel` (the Webservice dispatcher instantiates it by bare class name;
  the main module file `require_once`s it so it's always defined).
- Admin controller: `controllers/admin/AdminCombinationDescriptionsController.php`.
- Code targets **PSR-12** and **PHPStan level 5**.
- The importer field/XML spec lives in [`docs/importer-spec.md`](docs/importer-spec.md).
