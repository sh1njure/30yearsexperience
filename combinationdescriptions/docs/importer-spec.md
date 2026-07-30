# Importer spec — `combination_descriptions` Webservice resource

Feed this to your Excel importer so it can map spreadsheet columns to the
PrestaShop Webservice resource.

- **Resource endpoint:** `/api/combination_descriptions`
- **Auth:** HTTP Basic — API key as username, empty password.
- **Content-Type for writes:** `text/xml`
- **Methods:** `GET` (read/list), `POST` (create-or-update), `PUT` (update by
  id), `DELETE` (remove by id).
- **Idempotent key:** `id_product_attribute` + `id_shop`. Re-posting the same
  pair updates the existing row (no duplicates). Safe to re-run the whole sheet.

## Column → field map

| Spreadsheet column (suggested) | XML field | Required | Type | Notes |
|---|---|---|---|---|
| `combination_id` | `id_product_attribute` | Yes | int | The PrestaShop combination id (`id_product_attribute`). |
| `product_id` | `id_product` | No | int | Parent product. Auto-derived from the combination if left blank. |
| `shop_id` | `id_shop` | No | int | Defaults to the API key's shop / context. Provide for multishop. |
| `lang_id` | `<language id="…">` | Yes* | int | The PrestaShop language id. *One column-set per language you import. |
| `description` | `description` | No | HTML | Long description, wrapped in `<language>`. |
| `description_short` | `description_short` | No | HTML | Short description, wrapped in `<language>`. |

### Multi-language sheets

Model one column pair **per language**, e.g.:

| combination_id | shop_id | description[1] | description_short[1] | description[2] | description_short[2] |
|---|---|---|---|---|---|
| 12 | 1 | `<p>Red / M</p>` | `<p>Red M</p>` | `<p>Rouge / M</p>` | `<p>Rouge M</p>` |

`[1]` = language id 1, `[2]` = language id 2. Emit one `<language id="N">` node
per non-empty language column.

## Example XML payload (POST body)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
  <combination_description>
    <id_product_attribute>12</id_product_attribute>
    <id_product>45</id_product>
    <id_shop>1</id_shop>
    <description>
      <language id="1"><![CDATA[<p>Slim-fit red T-shirt, size M.</p>]]></language>
      <language id="2"><![CDATA[<p>T-shirt rouge coupe ajustée, taille M.</p>]]></language>
    </description>
    <description_short>
      <language id="1"><![CDATA[<p>Red / M</p>]]></language>
      <language id="2"><![CDATA[<p>Rouge / M</p>]]></language>
    </description_short>
  </combination_description>
</prestashop>
```

## Blank schema (authoritative field list)

Always confirm the exact accepted structure against the live shop:

```
GET /api/combination_descriptions?schema=blank
```

The response echoes every writable field and the `<language>` wrapping for
`description` / `description_short`. Build your XML from that template.

## Response codes

| Method | Success | Meaning |
|---|---|---|
| POST (new row) | `201 Created` | Row created; body returns the new id. |
| POST (existing pair) | `200 OK` | Existing row updated in place. |
| PUT | `200 OK` | Row updated by primary key. |
| GET | `200 OK` | Row(s) returned. |
| DELETE | `200 OK` | Row removed. |

Filtering for verification:

```
GET /api/combination_descriptions?filter[id_product]=45&display=full
GET /api/combination_descriptions?filter[id_product_attribute]=12&display=full
```
