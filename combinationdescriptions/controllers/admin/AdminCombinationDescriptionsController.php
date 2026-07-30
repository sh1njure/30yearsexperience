<?php
/**
 * Back-office controller: "Combination Descriptions" (under Catalog).
 *
 * Intentionally a standalone tab. PrestaShop 8's combinations tab is an
 * AJAX/JS component whose injection hooks are unreliable across minor versions,
 * so we edit descriptions here instead of trying to splice into it.
 *
 * Flow: search a product -> list its combinations (attribute names + reference)
 * -> edit a TinyMCE description / short description per language -> save.
 * Bulk actions: copy one combination's text to every combination of the
 * product, or clear all of them.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'combinationdescriptions/src/Entity/CombinationDescription.php';

class AdminCombinationDescriptionsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        $this->lang = false;

        parent::__construct();
    }

    /**
     * Render our custom management screen. With $this->display = 'view' the
     * AdminController framework calls this and injects the result as the page
     * content (no default list toolbar).
     *
     * @return string
     */
    public function renderView()
    {
        $idShop = (int) $this->context->shop->id;
        $languages = Language::getLanguages(true, $idShop);
        $productQuery = trim((string) Tools::getValue('product_query'));
        $idProduct = (int) Tools::getValue('id_product');

        $this->context->smarty->assign([
            'cd_languages' => $languages,
            'cd_default_lang' => (int) $this->context->employee->id_lang,
            'cd_product_query' => $productQuery,
            'cd_search_results' => $productQuery !== '' ? $this->searchProducts($productQuery, $idShop) : [],
            'cd_selected_product' => $idProduct ? $this->getProductHeader($idProduct) : null,
            'cd_combinations' => $idProduct ? $this->getCombinationsWithDescriptions($idProduct, $languages, $idShop) : [],
            'cd_form_action' => self::$currentIndex . '&token=' . $this->token,
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'combinationdescriptions/views/templates/admin/manage.tpl'
        );
    }

    /**
     * Handle saves and bulk actions before the page renders.
     *
     * @return void
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitDescriptions')) {
            $this->processSave();
        } elseif (Tools::isSubmit('submitCopyToAll')) {
            $this->processCopyToAll();
        } elseif (Tools::isSubmit('submitClearAll')) {
            $this->processClearAll();
        }

        parent::postProcess();
    }

    /**
     * Persist every combination's description for the current product.
     *
     * @return void
     */
    protected function processSave()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $idShop = (int) $this->context->shop->id;
        $languages = Language::getLanguages(true, $idShop);
        $descriptions = Tools::getValue('cd_description');
        $shorts = Tools::getValue('cd_description_short');

        if (!is_array($descriptions)) {
            $descriptions = [];
        }
        if (!is_array($shorts)) {
            $shorts = [];
        }

        foreach ($this->getProductAttributeIds($idProduct) as $idProductAttribute) {
            $descByLang = [];
            $shortByLang = [];
            foreach ($languages as $lang) {
                $idLang = (int) $lang['id_lang'];
                $descByLang[$idLang] = isset($descriptions[$idProductAttribute][$idLang])
                    ? $descriptions[$idProductAttribute][$idLang] : '';
                $shortByLang[$idLang] = isset($shorts[$idProductAttribute][$idLang])
                    ? $shorts[$idProductAttribute][$idLang] : '';
            }
            $this->saveCombination((int) $idProductAttribute, $idProduct, $idShop, $descByLang, $shortByLang);
        }

        $this->confirmations[] = $this->trans('Descriptions saved.', [], 'Modules.Combinationdescriptions.Admin');
    }

    /**
     * Copy the source combination's text to every combination of the product.
     *
     * @return void
     */
    protected function processCopyToAll()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $idShop = (int) $this->context->shop->id;
        $sourcePa = (int) Tools::getValue('copy_source');
        if (!$sourcePa) {
            $this->errors[] = $this->trans('Pick a source combination first.', [], 'Modules.Combinationdescriptions.Admin');

            return;
        }

        $languages = Language::getLanguages(true, $idShop);
        $sourceId = CombinationDescription::getIdByProductAttribute($sourcePa, $idShop);
        if (!$sourceId) {
            $this->errors[] = $this->trans('The source combination has no description yet.', [], 'Modules.Combinationdescriptions.Admin');

            return;
        }

        $source = new CombinationDescription($sourceId);
        foreach ($this->getProductAttributeIds($idProduct) as $idProductAttribute) {
            if ((int) $idProductAttribute === $sourcePa) {
                continue;
            }
            $descByLang = [];
            $shortByLang = [];
            foreach ($languages as $lang) {
                $idLang = (int) $lang['id_lang'];
                $descByLang[$idLang] = is_array($source->description)
                    ? (isset($source->description[$idLang]) ? $source->description[$idLang] : '') : $source->description;
                $shortByLang[$idLang] = is_array($source->description_short)
                    ? (isset($source->description_short[$idLang]) ? $source->description_short[$idLang] : '') : $source->description_short;
            }
            $this->saveCombination((int) $idProductAttribute, $idProduct, $idShop, $descByLang, $shortByLang);
        }

        $this->confirmations[] = $this->trans('Copied to all combinations.', [], 'Modules.Combinationdescriptions.Admin');
    }

    /**
     * Delete every combination description of the current product.
     *
     * @return void
     */
    protected function processClearAll()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $idShop = (int) $this->context->shop->id;

        foreach ($this->getProductAttributeIds($idProduct) as $idProductAttribute) {
            $id = CombinationDescription::getIdByProductAttribute((int) $idProductAttribute, $idShop);
            if ($id) {
                $cd = new CombinationDescription($id);
                $cd->delete();
            }
        }

        $this->confirmations[] = $this->trans('All descriptions cleared.', [], 'Modules.Combinationdescriptions.Admin');
    }

    /**
     * Create or update one combination's description (idempotent on the
     * id_product_attribute + id_shop pair, via the ObjectModel's add()).
     *
     * @param int                $idProductAttribute
     * @param int                $idProduct
     * @param int                $idShop
     * @param array<int, string> $descByLang
     * @param array<int, string> $shortByLang
     *
     * @return void
     */
    protected function saveCombination($idProductAttribute, $idProduct, $idShop, array $descByLang, array $shortByLang)
    {
        $id = CombinationDescription::getIdByProductAttribute($idProductAttribute, $idShop);
        $cd = new CombinationDescription($id ?: null);
        $cd->id_product_attribute = $idProductAttribute;
        $cd->id_product = $idProduct;
        $cd->id_shop = $idShop;
        $cd->description = $this->cleanLangArray($descByLang);
        $cd->description_short = $this->cleanLangArray($shortByLang);

        if ($id) {
            $cd->update();
        } else {
            $cd->add();
        }
    }

    /**
     * Run each language value through HTMLPurifier, matching the isCleanHtml
     * validator on the entity.
     *
     * @param array<int, string> $values
     *
     * @return array<int, string>
     */
    protected function cleanLangArray(array $values)
    {
        $out = [];
        foreach ($values as $idLang => $value) {
            $out[(int) $idLang] = Tools::purifyHTML((string) $value);
        }

        return $out;
    }

    /**
     * @param int $idProduct
     *
     * @return int[]
     */
    protected function getProductAttributeIds($idProduct)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id_product_attribute FROM `' . _DB_PREFIX_ . 'product_attribute`
             WHERE id_product = ' . (int) $idProduct
        );
        $ids = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $ids[] = (int) $row['id_product_attribute'];
            }
        }

        return $ids;
    }

    /**
     * Product autocomplete: match on name or reference.
     *
     * @param string $query
     * @param int    $idShop
     *
     * @return array<int, array<string, mixed>>
     */
    protected function searchProducts($query, $idShop)
    {
        $idLang = (int) $this->context->language->id;
        $escaped = pSQL($query);
        $sql = 'SELECT p.id_product, pl.name, p.reference
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON (pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . (int) $idShop . ')
                WHERE pl.name LIKE "%' . $escaped . '%" OR p.reference LIKE "%' . $escaped . '%"
                GROUP BY p.id_product
                ORDER BY pl.name ASC
                LIMIT 30';
        $rows = Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param int $idProduct
     *
     * @return array<string, mixed>|null
     */
    protected function getProductHeader($idProduct)
    {
        $product = new Product($idProduct, false, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($product)) {
            return null;
        }

        return [
            'id_product' => (int) $product->id,
            'name' => $product->name,
            'reference' => $product->reference,
        ];
    }

    /**
     * Build the per-combination editing rows: attribute names, reference, and
     * the current description text keyed by language.
     *
     * @param int                               $idProduct
     * @param array<int, array<string, mixed>>  $languages
     * @param int                               $idShop
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getCombinationsWithDescriptions($idProduct, array $languages, $idShop)
    {
        $idLang = (int) $this->context->language->id;
        $product = new Product($idProduct, false, $idLang);
        if (!Validate::isLoadedObject($product)) {
            return [];
        }

        $combos = [];
        $rows = $product->getAttributeCombinations($idLang);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $idPa = (int) $row['id_product_attribute'];
                if (!isset($combos[$idPa])) {
                    $combos[$idPa] = [
                        'id_product_attribute' => $idPa,
                        'reference' => (string) $row['reference'],
                        'attributes' => [],
                        'description' => [],
                        'description_short' => [],
                    ];
                }
                $combos[$idPa]['attributes'][] = $row['group_name'] . ': ' . $row['attribute_name'];
            }
        }

        // Attach existing descriptions per language.
        foreach (array_keys($combos) as $idPa) {
            $id = CombinationDescription::getIdByProductAttribute((int) $idPa, $idShop);
            $desc = [];
            $short = [];
            if ($id) {
                foreach ($languages as $lang) {
                    $idLangLoop = (int) $lang['id_lang'];
                    $row = Db::getInstance()->getRow(
                        'SELECT description, description_short
                         FROM `' . _DB_PREFIX_ . 'combination_description_lang`
                         WHERE id_combination_description = ' . (int) $id . '
                         AND id_lang = ' . $idLangLoop . ' AND id_shop = ' . (int) $idShop
                    );
                    $desc[$idLangLoop] = $row ? (string) $row['description'] : '';
                    $short[$idLangLoop] = $row ? (string) $row['description_short'] : '';
                }
            }
            $combos[$idPa]['attributes'] = implode(', ', $combos[$idPa]['attributes']);
            $combos[$idPa]['description'] = $desc;
            $combos[$idPa]['description_short'] = $short;
        }

        return array_values($combos);
    }
}
