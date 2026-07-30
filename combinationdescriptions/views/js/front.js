/**
 * Combination Descriptions — front-office swapper.
 *
 * Reads the JSON blob emitted by the module (one entry per combination) and
 * swaps the displayed text whenever PrestaShop fires `updatedProduct` (the
 * event dispatched when the customer changes a combination). This avoids an
 * extra AJAX round-trip per selection.
 *
 * Fallback behaviour: if the selected combination has no description we clear
 * our own block and leave the theme's native product description untouched —
 * we never render an empty block.
 */
(function () {
  'use strict';

  function readBlob() {
    var el = document.getElementById('cd-descriptions-data');
    if (!el) {
      return {};
    }
    try {
      return JSON.parse(el.textContent || el.innerText || '{}');
    } catch (e) {
      return {};
    }
  }

  function render(idProductAttribute) {
    var container = document.getElementById('cd-combination-descriptions');
    if (!container) {
      return;
    }
    var descEl = container.querySelector('.js-cd-description');
    var shortEl = container.querySelector('.js-cd-description-short');
    var data = readBlob();
    var entry = data[idProductAttribute] || data[String(idProductAttribute)];

    if (!entry || (!entry.description && !entry.description_short)) {
      // No per-combination text: hide our block, keep the parent description.
      if (descEl) descEl.innerHTML = '';
      if (shortEl) shortEl.innerHTML = '';
      container.style.display = 'none';
      return;
    }

    if (descEl) descEl.innerHTML = entry.description || '';
    if (shortEl) shortEl.innerHTML = entry.description_short || '';
    container.style.display = '';
  }

  function idFromEvent(event) {
    if (event && event.product_minimal_quantity !== undefined && event.id_product_attribute !== undefined) {
      return parseInt(event.id_product_attribute, 10);
    }
    if (event && event.id_product_attribute !== undefined) {
      return parseInt(event.id_product_attribute, 10);
    }
    // PrestaShop 8 passes the payload nested under `event.eventType`/`event`.
    if (event && event.event && event.event.dataset && event.event.dataset.idProductAttribute) {
      return parseInt(event.event.dataset.idProductAttribute, 10);
    }
    var hidden = document.querySelector('input[name="id_product_attribute"], #idCombination, .product-variants input[type="hidden"]');
    return hidden ? parseInt(hidden.value, 10) : 0;
  }

  function init() {
    // Initial render for the default combination on page load.
    var hidden = document.querySelector('input[name="id_product_attribute"], #idCombination');
    render(hidden ? parseInt(hidden.value, 10) : 0);

    if (typeof prestashop !== 'undefined' && typeof prestashop.on === 'function') {
      prestashop.on('updatedProduct', function (event) {
        render(idFromEvent(event));
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
