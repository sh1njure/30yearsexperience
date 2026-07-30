{**
 * Back-office management screen for Combination Descriptions.
 *
 * 1. Product search box.
 * 2. Search results (when a query was entered).
 * 3. For the selected product: its combinations, each with a per-language
 *    TinyMCE editor for description + description_short, plus bulk actions.
 *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-search"></i> {l s='Find a product' d='Modules.Combinationdescriptions.Admin'}
    </div>
    <form method="get" action="{$cd_form_action|escape:'html':'UTF-8'}" class="form-horizontal">
        <input type="hidden" name="controller" value="AdminCombinationDescriptions" />
        <input type="hidden" name="token" value="{$smarty.get.token|escape:'html':'UTF-8'}" />
        <div class="input-group">
            <input type="text" name="product_query" class="form-control"
                   placeholder="{l s='Product name or reference' d='Modules.Combinationdescriptions.Admin'}"
                   value="{$cd_product_query|escape:'html':'UTF-8'}" />
            <span class="input-group-btn">
                <button type="submit" class="btn btn-default">
                    <i class="icon-search"></i> {l s='Search' d='Admin.Actions'}
                </button>
            </span>
        </div>
    </form>

    {if $cd_search_results}
        <table class="table" style="margin-top:15px;">
            <thead>
                <tr>
                    <th>{l s='ID' d='Admin.Global'}</th>
                    <th>{l s='Name' d='Admin.Global'}</th>
                    <th>{l s='Reference' d='Admin.Global'}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {foreach $cd_search_results as $p}
                    <tr>
                        <td>{$p.id_product|intval}</td>
                        <td>{$p.name|escape:'html':'UTF-8'}</td>
                        <td>{$p.reference|escape:'html':'UTF-8'}</td>
                        <td>
                            <a class="btn btn-primary btn-xs"
                               href="{$cd_form_action|escape:'html':'UTF-8'}&id_product={$p.id_product|intval}&product_query={$cd_product_query|urlencode}">
                                {l s='Edit combinations' d='Modules.Combinationdescriptions.Admin'}
                            </a>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {/if}
</div>

{if $cd_selected_product}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-list"></i>
            {l s='Combinations for' d='Modules.Combinationdescriptions.Admin'}
            {$cd_selected_product.name|escape:'html':'UTF-8'}
            {if $cd_selected_product.reference} ({$cd_selected_product.reference|escape:'html':'UTF-8'}){/if}
        </div>

        {if $cd_combinations}
            <form method="post" action="{$cd_form_action|escape:'html':'UTF-8'}" id="cd-manage-form">
                <input type="hidden" name="id_product" value="{$cd_selected_product.id_product|intval}" />

                <div class="alert alert-info">
                    {l s='Language tabs are shown per editor. Empty descriptions fall back to the parent product description on the storefront.' d='Modules.Combinationdescriptions.Admin'}
                </div>

                {foreach $cd_combinations as $c}
                    <div class="panel cd-combination">
                        <div class="panel-heading">
                            <strong>#{$c.id_product_attribute|intval}</strong>
                            &mdash; {$c.attributes|escape:'html':'UTF-8'}
                            {if $c.reference}
                                <span class="badge">{l s='Ref' d='Modules.Combinationdescriptions.Admin'}: {$c.reference|escape:'html':'UTF-8'}</span>
                            {/if}
                        </div>
                        <div class="panel-body">
                            <ul class="nav nav-tabs cd-lang-tabs" role="tablist">
                                {foreach $cd_languages as $lang}
                                    <li class="{if $lang.id_lang == $cd_default_lang}active{/if}">
                                        <a href="javascript:void(0);" class="cd-lang-tab"
                                           data-pa="{$c.id_product_attribute|intval}"
                                           data-lang="{$lang.id_lang|intval}">
                                            {$lang.name|escape:'html':'UTF-8'}
                                        </a>
                                    </li>
                                {/foreach}
                            </ul>

                            {foreach $cd_languages as $lang}
                                <div class="cd-lang-pane"
                                     data-pa="{$c.id_product_attribute|intval}"
                                     data-lang="{$lang.id_lang|intval}"
                                     {if $lang.id_lang != $cd_default_lang}style="display:none;"{/if}>
                                    <div class="form-group">
                                        <label>{l s='Short description' d='Modules.Combinationdescriptions.Admin'}</label>
                                        <textarea class="cd-rte rte form-control"
                                                  name="cd_description_short[{$c.id_product_attribute|intval}][{$lang.id_lang|intval}]">{if isset($c.description_short[$lang.id_lang])}{$c.description_short[$lang.id_lang]|escape:'html':'UTF-8'}{/if}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>{l s='Description' d='Modules.Combinationdescriptions.Admin'}</label>
                                        <textarea class="cd-rte rte form-control"
                                                  name="cd_description[{$c.id_product_attribute|intval}][{$lang.id_lang|intval}]">{if isset($c.description[$lang.id_lang])}{$c.description[$lang.id_lang]|escape:'html':'UTF-8'}{/if}</textarea>
                                    </div>
                                </div>
                            {/foreach}
                        </div>
                    </div>
                {/foreach}

                <div class="panel-footer">
                    <button type="submit" name="submitDescriptions" class="btn btn-primary pull-right">
                        <i class="process-icon-save"></i> {l s='Save' d='Admin.Actions'}
                    </button>
                </div>
            </form>

            {* Bulk actions *}
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-magic"></i> {l s='Bulk actions' d='Modules.Combinationdescriptions.Admin'}
                </div>
                <div class="panel-body">
                    <form method="post" action="{$cd_form_action|escape:'html':'UTF-8'}" class="form-inline"
                          style="margin-bottom:10px;">
                        <input type="hidden" name="id_product" value="{$cd_selected_product.id_product|intval}" />
                        <label>{l s='Copy description from' d='Modules.Combinationdescriptions.Admin'}</label>
                        <select name="copy_source" class="form-control">
                            {foreach $cd_combinations as $c}
                                <option value="{$c.id_product_attribute|intval}">
                                    #{$c.id_product_attribute|intval} &mdash; {$c.attributes|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                        <button type="submit" name="submitCopyToAll" class="btn btn-default">
                            {l s='Copy to all combinations' d='Modules.Combinationdescriptions.Admin'}
                        </button>
                    </form>
                    <form method="post" action="{$cd_form_action|escape:'html':'UTF-8'}" class="form-inline"
                          onsubmit="return confirm('{l s='Delete every combination description for this product?' d='Modules.Combinationdescriptions.Admin' js=true}');">
                        <input type="hidden" name="id_product" value="{$cd_selected_product.id_product|intval}" />
                        <button type="submit" name="submitClearAll" class="btn btn-danger">
                            <i class="icon-trash"></i> {l s='Clear all descriptions' d='Modules.Combinationdescriptions.Admin'}
                        </button>
                    </form>
                </div>
            </div>
        {else}
            <div class="panel-body">
                <p class="alert alert-warning">
                    {l s='This product has no combinations.' d='Modules.Combinationdescriptions.Admin'}
                </p>
            </div>
        {/if}
    </div>
{/if}
