/**
 * Combination Descriptions — front-office Summary swapper.
 *
 * Overrides the theme's existing Summary element (the top product description,
 * PrestaShop's `description_short`, e.g. `.product-description-short`) with the
 * selected combination's text, and swaps it live on PrestaShop's `updatedProduct`
 * event — no extra AJAX per selection.
 *
 * Fallback: a combination with no text restores the product's original Summary,
 * so the block is never emptied. HTML is purified server-side before output.
 */
(function () {
  'use strict';

  function readData() {
    var el = document.getElementById('cd-descriptions-data');
    if (!el) {
      return { blob: {}, selector: '.product-description-short, [id^="product-description-short-"]' };
    }
    var blob = {};
    try {
      blob = JSON.parse(el.textContent || el.innerText || '{}');
    } catch (e) {
      blob = {};
    }
    return {
      blob: blob,
      selector: el.getAttribute('data-cd-selector')
        || '.product-description-short, [id^="product-description-short-"]'
    };
  }

  var data = readData();
  // The product's own Summary HTML, captured once before we touch it, used as
  // the fallback for combinations that have no description.
  var productDefault = null;

  function targets() {
    if (!data.selector) {
      return [];
    }
    try {
      return Array.prototype.slice.call(document.querySelectorAll(data.selector));
    } catch (e) {
      return [];
    }
  }

  function textFor(idProductAttribute) {
    var entry = data.blob[idProductAttribute] || data.blob[String(idProductAttribute)];
    if (!entry) {
      return null;
    }
    // The Summary uses the short description, falling back to the long one so a
    // single-column import still works.
    return entry.description_short || entry.description || null;
  }

  function render(idProductAttribute) {
    var els = targets();
    if (!els.length) {
      return;
    }
    if (productDefault === null) {
      productDefault = els[0].innerHTML;
    }
    var text = textFor(idProductAttribute);
    var html = (text !== null && text !== '') ? text : productDefault;
    els.forEach(function (el) { el.innerHTML = html; });
  }

  function currentId(event) {
    if (event && event.id_product_attribute !== undefined && event.id_product_attribute !== null) {
      return parseInt(event.id_product_attribute, 10);
    }
    var hidden = document.querySelector(
      'input[name="id_product_attribute"], #idCombination, .product-variants input[type="hidden"]');
    return hidden ? parseInt(hidden.value, 10) : 0;
  }

  function init() {
    var hidden = document.querySelector('input[name="id_product_attribute"], #idCombination');
    render(hidden ? parseInt(hidden.value, 10) : 0);

    if (typeof prestashop !== 'undefined' && typeof prestashop.on === 'function') {
      prestashop.on('updatedProduct', function (event) {
        // Run after the theme finishes updating the DOM for the new combination.
        setTimeout(function () { render(currentId(event)); }, 0);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
