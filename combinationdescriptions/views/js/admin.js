/**
 * Combination Descriptions — back-office script.
 *
 * - Initialises TinyMCE on the per-combination editors.
 * - Drives the per-language tabs (show one language pane at a time).
 */
$(document).ready(function () {
  'use strict';

  // --- Language tabs -------------------------------------------------------
  $(document).on('click', '.cd-lang-tab', function () {
    var pa = $(this).data('pa');
    var lang = $(this).data('lang');

    $(this).closest('.cd-lang-tabs').find('li').removeClass('active');
    $(this).closest('li').addClass('active');

    $('.cd-lang-pane[data-pa="' + pa + '"]').hide();
    $('.cd-lang-pane[data-pa="' + pa + '"][data-lang="' + lang + '"]').show();
  });

  // --- TinyMCE -------------------------------------------------------------
  function initEditors() {
    if (typeof tinySetup === 'function') {
      // PrestaShop's helper: applies the BO's standard RTE config.
      tinySetup({ editor_selector: 'cd-rte' });
    } else if (typeof tinyMCE !== 'undefined') {
      tinyMCE.init({
        selector: 'textarea.cd-rte',
        menubar: false,
        plugins: 'link lists code',
        toolbar: 'bold italic underline | bullist numlist | link | code',
        entity_encoding: 'raw'
      });
    }
  }

  initEditors();
});
