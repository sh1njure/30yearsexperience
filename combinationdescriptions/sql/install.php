<?php
/**
 * Schema creation for the combinationdescriptions module.
 *
 * Two tables:
 *   - {prefix}combination_description        (one row per combination + shop)
 *   - {prefix}combination_description_lang   (multilang, shop-dependent text)
 *
 * A UNIQUE index on (id_product_attribute, id_shop) enforces the "one
 * description per combination per shop" rule at the DB level, backing up the
 * idempotent-POST logic in the ObjectModel. A composite index on
 * (id_product, id_shop) serves the front-office query which looks up by product.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'combination_description` (
    `id_combination_description` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product_attribute` INT(11) UNSIGNED NOT NULL,
    `id_product` INT(11) UNSIGNED NOT NULL,
    `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_combination_description`),
    UNIQUE KEY `uniq_pa_shop` (`id_product_attribute`, `id_shop`),
    KEY `idx_id_product_attribute` (`id_product_attribute`),
    KEY `idx_product_shop` (`id_product`, `id_shop`)
) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'combination_description_lang` (
    `id_combination_description` INT(11) UNSIGNED NOT NULL,
    `id_lang` INT(11) UNSIGNED NOT NULL,
    `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
    `description` LONGTEXT NULL,
    `description_short` TEXT NULL,
    PRIMARY KEY (`id_combination_description`, `id_shop`, `id_lang`)
) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
