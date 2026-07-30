<?php
/**
 * Schema teardown for the combinationdescriptions module.
 *
 * The caller (Module::uninstall) only includes this file when the merchant has
 * NOT asked to keep their data (see CD_KEEP_DATA_ON_UNINSTALL). Keeping data on
 * uninstall is the default, so a reinstall preserves existing descriptions.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'combination_description_lang`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'combination_description`;';

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
