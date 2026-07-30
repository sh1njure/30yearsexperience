{**
 * Front-office combination descriptions block.
 *
 * Renders a container whose text is swapped client-side when the customer picks
 * a different combination. The parent product description stays visible as the
 * fallback (handled in front.js): if the selected combination has no text we
 * simply leave the theme's own product description untouched.
 *}
<div id="cd-combination-descriptions"
     class="cd-combination-descriptions"
     data-cd-product="{$cd_product_id|intval}">
    <div class="cd-description js-cd-description"></div>
    <div class="cd-description-short js-cd-description-short"></div>
</div>
<script type="application/json" id="cd-descriptions-data">{$cd_blob_json nofilter}</script>
