<?php
/**
 * Combination Descriptions.
 *
 * Adds a per-combination (product attribute) description to PrestaShop 8.x —
 * something the platform has no native field for — and exposes it through the
 * legacy Webservice API so an external import tool can bulk-load it.
 *
 * @author  combinationdescriptions
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * The Webservice dispatcher instantiates resources by bare class name
 * (`new CombinationDescription()`) and does NOT know about module namespaces or
 * autoloaders, so the entity is a global-namespace class that we include here.
 * Loading it at the top of the module file guarantees the class is defined
 * whenever this module (and therefore hookAddWebserviceResources) is loaded.
 */
require_once __DIR__ . '/src/Entity/CombinationDescription.php';

class CombinationDescriptions extends Module
{
    /** @var string Config key: keep DB data when the module is uninstalled. */
    const CONFIG_KEEP_DATA = 'CD_KEEP_DATA_ON_UNINSTALL';

    /** @var string Config key: the (theme-dependent) front-office display hook. */
    const CONFIG_FRONT_HOOK = 'CD_FRONT_HOOK';

    /** @var string Default front-office display hook. */
    const DEFAULT_FRONT_HOOK = 'displayProductAdditionalInfo';

    /** @var string Config key: CSS selector of the theme's Summary element to override. */
    const CONFIG_SUMMARY_SELECTOR = 'CD_SUMMARY_SELECTOR';

    /** @var string Default target: the classic theme's Summary (short description). */
    const DEFAULT_SUMMARY_SELECTOR = '.product-description-short';

    /** @var string Admin controller class name. */
    const ADMIN_CONTROLLER = 'AdminCombinationDescriptions';

    public function __construct()
    {
        $this->name = 'combinationdescriptions';
        $this->tab = 'catalog';
        $this->version = '1.0.0';
        $this->author = 'combinationdescriptions';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => '8.99.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Combination Descriptions', [], 'Modules.Combinationdescriptions.Admin');
        $this->description = $this->trans(
            'Adds a description to each product combination and exposes it through the Webservice API.',
            [],
            'Modules.Combinationdescriptions.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure? Combination descriptions are kept by default; disable "Keep data on uninstall" first to delete them.',
            [],
            'Modules.Combinationdescriptions.Admin'
        );
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->installSql()
            && $this->registerHook('addWebserviceResources')
            && $this->registerHook($this->getFrontHook())
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->installTab()
            && Configuration::updateValue(self::CONFIG_KEEP_DATA, 1)
            && Configuration::updateValue(self::CONFIG_FRONT_HOOK, self::DEFAULT_FRONT_HOOK)
            && Configuration::updateValue(self::CONFIG_SUMMARY_SELECTOR, self::DEFAULT_SUMMARY_SELECTOR);
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        $keepData = (bool) Configuration::get(self::CONFIG_KEEP_DATA);

        $ok = $this->uninstallTab();

        if (!$keepData) {
            $ok = $ok && $this->uninstallSql();
            Configuration::deleteByName(self::CONFIG_KEEP_DATA);
            Configuration::deleteByName(self::CONFIG_FRONT_HOOK);
        }

        return $ok && parent::uninstall();
    }

    /**
     * @return bool
     */
    protected function installSql()
    {
        return (bool) (require __DIR__ . '/sql/install.php');
    }

    /**
     * @return bool
     */
    protected function uninstallSql()
    {
        return (bool) (require __DIR__ . '/sql/uninstall.php');
    }

    /**
     * Create the "Combination Descriptions" back-office tab under Catalog.
     *
     * @return bool
     */
    protected function installTab()
    {
        if (Tab::getIdFromClassName(self::ADMIN_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');
        $tab->icon = 'description';
        $tab->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = $this->trans(
                'Combination Descriptions',
                [],
                'Modules.Combinationdescriptions.Admin',
                $lang['locale']
            );
        }

        return (bool) $tab->add();
    }

    /**
     * @return bool
     */
    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if (!$idTab) {
            return true;
        }
        $tab = new Tab($idTab);

        return (bool) $tab->delete();
    }

    /**
     * Currently configured front-office hook (defaults to the standard one).
     *
     * @return string
     */
    public function getFrontHook()
    {
        $hook = (string) Configuration::get(self::CONFIG_FRONT_HOOK);

        return $hook !== '' ? $hook : self::DEFAULT_FRONT_HOOK;
    }

    /**
     * CSS selector of the theme element whose content is replaced with the
     * selected combination's text (the storefront Summary, by default).
     *
     * @return string
     */
    public function getSummarySelector()
    {
        $selector = (string) Configuration::get(self::CONFIG_SUMMARY_SELECTOR);

        return $selector !== '' ? $selector : self::DEFAULT_SUMMARY_SELECTOR;
    }

    /* ------------------------------------------------------------------ *
     *  Webservice
     * ------------------------------------------------------------------ */

    /**
     * Register the entity as a Webservice resource. The dispatcher merges this
     * into the resource list and the resource then appears (and is grantable)
     * under Advanced Parameters > Webservice > (key) > permissions.
     *
     * @return array<string, array<string, string>>
     */
    public function hookAddWebserviceResources()
    {
        return [
            'combination_descriptions' => [
                'description' => 'Per-combination product descriptions',
                'class' => 'CombinationDescription',
            ],
        ];
    }

    /* ------------------------------------------------------------------ *
     *  Front office
     * ------------------------------------------------------------------ */

    /**
     * Route any registered display hook (the name is theme-configurable) to the
     * single front-office renderer. Only the hooks we explicitly register are
     * ever dispatched here.
     *
     * @param string             $method
     * @param array<int, mixed>  $arguments
     *
     * @return string
     */
    public function __call($method, $arguments)
    {
        if (stripos($method, 'hookDisplay') === 0) {
            $params = isset($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : [];

            return $this->renderFrontDescriptions($params);
        }

        return '';
    }

    /**
     * Explicit default hook (kept for clarity / when the configured hook is the
     * default). Delegates to the shared renderer.
     *
     * @param array<string, mixed> $params
     *
     * @return string
     */
    public function hookDisplayProductAdditionalInfo(array $params)
    {
        return $this->renderFrontDescriptions($params);
    }

    /**
     * Emit the parent-product fallback block plus the JSON blob of every
     * combination description for the current product. A small JS file swaps the
     * text client-side on PrestaShop's `updatedProduct` event, so changing a
     * combination costs no extra AJAX round-trip.
     *
     * @param array<string, mixed> $params
     *
     * @return string
     */
    protected function renderFrontDescriptions(array $params)
    {
        $context = $this->context;
        $idProduct = $this->resolveProductId($params);
        if ($idProduct <= 0) {
            return '';
        }

        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $blob = CombinationDescription::getDescriptionsForProduct($idProduct, $idLang, $idShop);

        // Purify HTML server-side before it ever reaches the page / JSON blob.
        $safeBlob = [];
        foreach ($blob as $idProductAttribute => $texts) {
            $safeBlob[(int) $idProductAttribute] = [
                'description' => Tools::purifyHTML($texts['description']),
                'description_short' => Tools::purifyHTML($texts['description_short']),
            ];
        }

        // Never render an empty block: bail out if this product has no data.
        if (empty($safeBlob)) {
            return '';
        }

        $this->context->smarty->assign([
            'cd_product_id' => $idProduct,
            'cd_blob_json' => json_encode($safeBlob, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
            'cd_summary_selector' => $this->getSummarySelector(),
        ]);

        return $this->fetch('module:combinationdescriptions/views/templates/hook/front.tpl');
    }

    /**
     * Attach the front-office swapper script only on the product page.
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia(array $params)
    {
        if (isset($this->context->controller->php_self) && $this->context->controller->php_self === 'product') {
            $this->context->controller->registerJavascript(
                'modules-combinationdescriptions-front',
                'modules/' . $this->name . '/views/js/front.js',
                ['position' => 'bottom', 'priority' => 200]
            );
        }
    }

    /**
     * Load TinyMCE + our admin script on the module's own admin controller.
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function hookActionAdminControllerSetMedia(array $params)
    {
        if (Tools::getValue('controller') === self::ADMIN_CONTROLLER) {
            $this->context->controller->addJqueryPlugin('autocomplete');
            $this->context->controller->addJS(_PS_JS_DIR_ . 'tiny_mce/tiny_mce.js');
            $this->context->controller->addJS(_PS_JS_DIR_ . 'admin/tinymce.inc.js');
            $this->context->controller->addJS($this->_path . 'views/js/admin.js');
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return int
     */
    protected function resolveProductId(array $params)
    {
        if (!empty($params['product']) && is_object($params['product']) && isset($params['product']->id)) {
            return (int) $params['product']->id;
        }
        if (!empty($params['product']['id_product'])) {
            return (int) $params['product']['id_product'];
        }

        return (int) Tools::getValue('id_product');
    }

    /* ------------------------------------------------------------------ *
     *  Configuration screen (getContent)
     * ------------------------------------------------------------------ */

    /**
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitCombinationDescriptions')) {
            $output .= $this->postProcessSettings();
        }

        return $output . $this->renderSettingsForm();
    }

    /**
     * @return string
     */
    protected function postProcessSettings()
    {
        $newHook = trim((string) Tools::getValue(self::CONFIG_FRONT_HOOK));
        $keepData = (int) Tools::getValue(self::CONFIG_KEEP_DATA);

        if ($newHook === '' || !Validate::isHookName($newHook)) {
            return $this->displayError(
                $this->trans('Invalid hook name.', [], 'Modules.Combinationdescriptions.Admin')
            );
        }

        $currentHook = $this->getFrontHook();
        if ($newHook !== $currentHook) {
            $this->unregisterHook($currentHook);
            $this->registerHook($newHook);
        }

        $selector = trim((string) Tools::getValue(self::CONFIG_SUMMARY_SELECTOR));
        if ($selector === '') {
            $selector = self::DEFAULT_SUMMARY_SELECTOR;
        }

        Configuration::updateValue(self::CONFIG_FRONT_HOOK, $newHook);
        Configuration::updateValue(self::CONFIG_KEEP_DATA, $keepData ? 1 : 0);
        Configuration::updateValue(self::CONFIG_SUMMARY_SELECTOR, $selector);

        return $this->displayConfirmation(
            $this->trans('Settings updated.', [], 'Modules.Combinationdescriptions.Admin')
        );
    }

    /**
     * @return string
     */
    protected function renderSettingsForm()
    {
        $fields = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Combinationdescriptions.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Summary CSS selector', [], 'Modules.Combinationdescriptions.Admin'),
                        'name' => self::CONFIG_SUMMARY_SELECTOR,
                        'desc' => $this->trans(
                            'The theme element whose text is replaced by the selected combination (the storefront Summary). Default .product-description-short. Change it if your theme shows the summary elsewhere.',
                            [],
                            'Modules.Combinationdescriptions.Admin'
                        ),
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Front-office display hook', [], 'Modules.Combinationdescriptions.Admin'),
                        'name' => self::CONFIG_FRONT_HOOK,
                        'desc' => $this->trans(
                            'The hook used to inject the combination data on the product page. Defaults to displayProductAdditionalInfo; change it if your theme does not fire that hook.',
                            [],
                            'Modules.Combinationdescriptions.Admin'
                        ),
                        'required' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Keep data on uninstall', [], 'Modules.Combinationdescriptions.Admin'),
                        'name' => self::CONFIG_KEEP_DATA,
                        'is_bool' => true,
                        'desc' => $this->trans(
                            'When enabled, uninstalling the module leaves the description tables in place so a reinstall keeps your data.',
                            [],
                            'Modules.Combinationdescriptions.Admin'
                        ),
                        'values' => [
                            [
                                'id' => 'keep_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'keep_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitCombinationDescriptions';
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->fields_value = [
            self::CONFIG_SUMMARY_SELECTOR => $this->getSummarySelector(),
            self::CONFIG_FRONT_HOOK => $this->getFrontHook(),
            self::CONFIG_KEEP_DATA => (int) Configuration::get(self::CONFIG_KEEP_DATA),
        ];

        return $helper->generateForm([$fields]);
    }
}
