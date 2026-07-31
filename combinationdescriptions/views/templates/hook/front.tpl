{**
 * Front-office data carrier for combination descriptions.
 *
 * We do NOT render a visible block: instead front.js overrides the theme's own
 * Summary element (data-cd-selector) in place, so the existing top description
 * updates when the customer changes combination. This tag only carries the
 * per-combination text and the target selector.
 *
 * Blob shape: { id_product_attribute: {description, description_short} }.
 *}
<script type="application/json"
        id="cd-descriptions-data"
        data-cd-product="{$cd_product_id|intval}"
        data-cd-selector="{$cd_summary_selector|escape:'html':'UTF-8'}">{$cd_blob_json nofilter}</script>
