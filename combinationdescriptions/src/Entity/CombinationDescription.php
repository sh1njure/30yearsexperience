<?php
/**
 * CombinationDescription entity.
 *
 * Adds a per-combination (product attribute) description to PrestaShop, which
 * the platform lacks natively, and exposes it through the legacy Webservice.
 *
 * IMPORTANT: This class is deliberately declared in the GLOBAL namespace and
 * named exactly `CombinationDescription`. The legacy Webservice dispatcher
 * instantiates resources by their class name (`new CombinationDescription()`)
 * and does not know about module namespaces, so a namespaced class would make
 * it fatal. The main module file `include_once`s this file for the same reason.
 *
 * @author  combinationdescriptions
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CombinationDescription extends ObjectModel
{
    /** @var int */
    public $id_combination_description;

    /** @var int */
    public $id_product_attribute;

    /** @var int Denormalized product id, for fast front-office lookups by product. */
    public $id_product;

    /** @var int */
    public $id_shop;

    /** @var string|array<int, string> HTML long description (multilang). */
    public $description;

    /** @var string|array<int, string> HTML short description (multilang). */
    public $description_short;

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    /**
     * ObjectModel definition.
     *
     * We use `multilang` + `multilang_shop`. In PrestaShop 8.x `multilang_shop`
     * is the flag that makes the language rows shop-dependent — it adds the
     * `id_shop` column to the `_lang` table and writes one lang row per
     * (id_lang, id_shop). That is exactly the "multilang + multishop" behaviour
     * requested here, without needing a separate `{table}_shop` association
     * table (the schema denormalizes `id_shop` directly into the main table
     * instead). See README > "Multishop notes".
     *
     * @var array<string, mixed>
     */
    public static $definition = [
        'table' => 'combination_description',
        'primary' => 'id_combination_description',
        'multilang' => true,
        'multilang_shop' => true,
        'fields' => [
            'id_product_attribute' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            // Not 'required': callers (Webservice/importer) may send only
            // id_product_attribute; add()/update() derive id_product from it in
            // hydrateProductId() before persisting. The DB column stays NOT NULL.
            'id_product' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
            ],
            // No 'validate': the Webservice validates fields BEFORE add()/update()
            // run, and isUnsignedId rejects an empty value — so an omitted
            // id_shop would 400 before hydrateProductId() could default it.
            // hydrateProductId() guarantees a valid positive shop id at write
            // time; the DB column is NOT NULL.
            'id_shop' => [
                'type' => self::TYPE_INT,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
            'date_upd' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
            // Multilang, shop-dependent HTML fields.
            'description' => [
                'type' => self::TYPE_HTML,
                'lang' => true,
                'validate' => 'isCleanHtml',
                'size' => 4194303,
            ],
            'description_short' => [
                'type' => self::TYPE_HTML,
                'lang' => true,
                'validate' => 'isCleanHtml',
                'size' => 65535,
            ],
        ],
    ];

    /**
     * Webservice exposure.
     *
     * - `id_product_attribute` and `id_product` are real DB fields, so the
     *   generic `filter[field]=value` querystring already makes them filterable.
     * - `id_product_attribute` is exposed as an associated resource by pointing
     *   its `xlink_resource` at the built-in `combinations` resource.
     * - `description` / `description_short` are declared `lang => true` above, so
     *   `?schema=blank` returns them wrapped in `<language id="x">` nodes
     *   automatically.
     *
     * @var array<string, mixed>
     */
    public $webserviceParameters = [
        'objectsNodeName' => 'combination_descriptions',
        'objectNodeName' => 'combination_description',
        'fields' => [
            'id_product_attribute' => [
                'required' => true,
                'xlink_resource' => 'combinations',
            ],
            'id_product' => [
                'xlink_resource' => 'products',
            ],
            // id_shop stays a plain filterable integer field (not an xlink
            // association) — it is a shop id the module manages itself.
        ],
    ];

    /**
     * Persist a new row, but de-duplicate on (id_product_attribute, id_shop):
     * if a row already exists for that pair we UPDATE it in place instead of
     * inserting a duplicate. This is what makes idempotent Webservice POSTs and
     * bulk imports safe. A unique index enforces the same rule at the DB level.
     *
     * @param bool $autoDate
     * @param bool $nullValues
     *
     * @return bool
     */
    public function add($autoDate = true, $nullValues = false)
    {
        $this->hydrateProductId();

        if (empty($this->id)) {
            $existingId = self::getIdByProductAttribute(
                (int) $this->id_product_attribute,
                (int) $this->id_shop
            );
            if ($existingId) {
                $this->id = $existingId;

                return $this->update($nullValues);
            }
        }

        $result = parent::add($autoDate, $nullValues);
        $this->invalidateCache();

        return $result;
    }

    /**
     * @param bool $nullValues
     *
     * @return bool
     */
    public function update($nullValues = false)
    {
        $this->hydrateProductId();
        $result = parent::update($nullValues);
        $this->invalidateCache();

        return $result;
    }

    /**
     * @return bool
     */
    public function delete()
    {
        $this->invalidateCache();

        return parent::delete();
    }

    /**
     * Fill id_shop / id_product with sane defaults when the caller (e.g. the
     * Webservice or importer) omitted them.
     *
     * @return void
     */
    protected function hydrateProductId()
    {
        if (empty($this->id_shop)) {
            // Guarantee a valid positive shop id even when the Webservice
            // context has no shop resolved (it can report 0), so we never store
            // id_shop = 0 (which would fail isUnsignedId on a later write).
            $idShop = (int) Context::getContext()->shop->id;
            if ($idShop <= 0) {
                $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
            }
            $this->id_shop = $idShop > 0 ? $idShop : 1;
        }

        if (empty($this->id_product) && !empty($this->id_product_attribute)) {
            $this->id_product = (int) Db::getInstance()->getValue(
                'SELECT id_product FROM `' . _DB_PREFIX_ . 'product_attribute`
                 WHERE id_product_attribute = ' . (int) $this->id_product_attribute
            );
        }
    }

    /**
     * Purge the cached front-office JSON blob for this row's product.
     *
     * @return void
     */
    protected function invalidateCache()
    {
        if ((int) $this->id_product > 0) {
            self::clearProductCache((int) $this->id_product);
        }
    }

    /**
     * Return the id_combination_description for a given product attribute + shop,
     * or 0 when none exists.
     *
     * @param int $idProductAttribute
     * @param int $idShop
     *
     * @return int
     */
    public static function getIdByProductAttribute($idProductAttribute, $idShop)
    {
        $sql = 'SELECT id_combination_description
                FROM `' . _DB_PREFIX_ . 'combination_description`
                WHERE id_product_attribute = ' . (int) $idProductAttribute . '
                AND id_shop = ' . (int) $idShop;

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Build (and cache) the per-product blob of combination descriptions in the
     * shape the front office / API expects:
     *
     *   [ id_product_attribute => ['description' => '...', 'description_short' => '...'] ]
     *
     * @param int $idProduct
     * @param int $idLang
     * @param int $idShop
     *
     * @return array<int, array{description: string, description_short: string}>
     */
    public static function getDescriptionsForProduct($idProduct, $idLang, $idShop)
    {
        $cacheKey = self::getCacheKey($idProduct, $idLang, $idShop);

        if (self::isCacheEnabled() && Cache::getInstance()->exists($cacheKey)) {
            /** @var array<int, array{description: string, description_short: string}> $cached */
            $cached = Cache::getInstance()->get($cacheKey);

            return $cached;
        }

        $sql = 'SELECT cd.id_product_attribute, cdl.description, cdl.description_short
                FROM `' . _DB_PREFIX_ . 'combination_description` cd
                INNER JOIN `' . _DB_PREFIX_ . 'combination_description_lang` cdl
                    ON (cdl.id_combination_description = cd.id_combination_description
                        AND cdl.id_lang = ' . (int) $idLang . '
                        AND cdl.id_shop = ' . (int) $idShop . ')
                WHERE cd.id_product = ' . (int) $idProduct . '
                AND cd.id_shop = ' . (int) $idShop;

        $rows = Db::getInstance()->executeS($sql);
        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $out[(int) $row['id_product_attribute']] = [
                    'description' => (string) $row['description'],
                    'description_short' => (string) $row['description_short'],
                ];
            }
        }

        if (self::isCacheEnabled()) {
            Cache::getInstance()->set($cacheKey, $out);
        }

        return $out;
    }

    /**
     * @param int $idProduct
     * @param int $idLang
     * @param int $idShop
     *
     * @return string
     */
    protected static function getCacheKey($idProduct, $idLang, $idShop)
    {
        return 'combinationdescriptions_blob_' . (int) $idProduct
            . '_' . (int) $idShop . '_' . (int) $idLang;
    }

    /**
     * Drop every cached blob for a product (all langs / shops).
     *
     * @param int $idProduct
     *
     * @return void
     */
    public static function clearProductCache($idProduct)
    {
        if (!self::isCacheEnabled()) {
            return;
        }
        // Wildcard clean removes every lang/shop variant for this product.
        Cache::getInstance()->delete('combinationdescriptions_blob_' . (int) $idProduct . '_*');
    }

    /**
     * @return bool
     */
    protected static function isCacheEnabled()
    {
        return defined('_PS_CACHE_ENABLED_') && _PS_CACHE_ENABLED_;
    }
}
