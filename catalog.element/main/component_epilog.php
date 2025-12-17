<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
global $arTheme, $APPLICATION;

$arExtensions = ['fancybox', 'detail', 'swiper', 'swiper_events', 'rounded_columns', 'viewed', 'gallery', 'fancybox', 'stores_amount','countdown'];

if ($arParams['SHOW_RATING']) {
    $arExtensions[] = 'rating';
    $arExtensions[] = 'rate';
}

if($templateData['BIG_GALLERY']){
    $arExtensions[] = 'ui-card';
}

if ($templateData['POPUP_VIDEO']) {
    $arExtensions[] = 'video';
}

if ($templateData['SHOW_REVIEW']) {
    $arExtensions[] = 'reviews';
}

if ($templateData['USE_SHARE']) {
    $arExtensions[] = 'share';
}

if ($templateData['BRAND']) {
    $arExtensions[] = 'chip';
}

if ($templateData['USE_OFFERS_SELECT']) {
    $arExtensions[] = 'select_offer';
    $arExtensions[] = 'select_offer_load';
}

if ($templateData['SHOW_CHARACTERISTICS']) {
    $arExtensions[] = 'hint';
}

// top banner
if ($templateData['SECTION_BNR_CONTENT']) {
    $GLOBALS['SECTION_BNR_CONTENT'] = true;
    $GLOBALS['bodyDopClass'] .= ' has-long-banner '.($templateData['SECTION_BNR_UNDER_HEADER'] === 'YES' ? 'header_opacity front_page' : '');
    if ($templateData['SECTION_BNR_COLOR'] !== 'dark') {
        $APPLICATION->SetPageProperty('HEADER_COLOR_CLASS', 'theme-dark');
        $APPLICATION->SetPageProperty('HEADER_LOGO', 'light');
    }
    if ($templateData['SECTION_BNR_UNDER_HEADER'] === 'YES') {
        $arExtensions[] = 'header_opacity';
    }
    $arExtensions[] = 'banners';
    $arExtensions[] = 'animate';
}

define('ASPRO_PAGE_WO_TITLE', true); // remove h1 from page_title

// can order?
$bOrderViewBasket = $templateData['ORDER'];

// use tabs?
if ($arParams['USE_DETAIL_TABS'] === 'Y') {
    $bUseDetailTabs = true;
} elseif ($arParams['USE_DETAIL_TABS'] === 'N') {
    $bUseDetailTabs = false;
} else {
    $bUseDetailTabs = $arTheme['USE_DETAIL_TABS']['VALUE'] != 'N';
}

// blocks order
if (
    !$bUseDetailTabs
    && array_key_exists('DETAIL_BLOCKS_ALL_ORDER', $arParams)
    && $arParams['DETAIL_BLOCKS_ALL_ORDER']
) {
    $arBlockOrder = explode(',', $arParams['DETAIL_BLOCKS_ALL_ORDER']);
} else {
    $arBlockOrder = explode(',', $arParams['DETAIL_BLOCKS_ORDER']);
    $arTabOrder = explode(',', $arParams['DETAIL_BLOCKS_TAB_ORDER']);
}

if (!Bitrix\Main\Loader::includeModule('blog') || !$templateData['SHOW_REVIEW']) {
    $arBlockOrder = array_diff($arBlockOrder, ['reviews']);

    if ($arTabOrder) {
        $arTabOrder = array_diff($arTabOrder, ['reviews']);
    }
}

TSolution\Functions::replaceListParams($arParams, ['PROPERTY_CODE' => 'PROPERTY_CODE']);
TSolution\Extensions::init($arExtensions);
?>

<?php
// custom blocks
$customBlocks = new TSolution\Product\Blocks(blockParams: $arParams['~CUSTOM_DETAIL_BLOCKS']);
$customBlocks->resolveHtml(params: $arParams, result: $arResult, templateData: $templateData);
?>
<div class="catalog-detail__bottom-info flexbox gap gap--48  mt mt--80">
    <?php
    $arEpilogBlocks = new TSolution\Template\Epilog\Blocks([
        'BEFORE_ORDERED' => ['product_analog'],
        'ORDERED' => $arBlockOrder,
        'TABS' => $arTabOrder ?? [],
    ], templatePath: __DIR__, customBlocks: $customBlocks);

foreach ($arEpilogBlocks->beforeOrdered as $path) {
    include $path;
}

foreach ($arEpilogBlocks->ordered as $code => $path) {
    include $path;
}?>
</div>
<script type="text/javascript">
    var viewedCounter = {
        path: '/bitrix/components/bitrix/catalog.element/ajax.php',
        params: {
            AJAX: 'Y',
            SITE_ID: '<?= SITE_ID; ?>',
            PRODUCT_ID: '<?= $arResult['ID']; ?>',
            PARENT_ID: '<?= $arResult['ID']; ?>',
        }
    };
    BX.ready(
        BX.defer(function() {
            BX.ajax.post(
                viewedCounter.path,
                viewedCounter.params
            );
        })
    );

    viewItemCounter('<?= $arResult['ID']; ?>', '<?= current($arParams['PRICE_CODE']); ?>');
</script>
<?if ($templateData['SCHEMA_ORG']):?>
    <script type="application/ld+json"><?=Bitrix\Main\Web\Json::encode($templateData['SCHEMA_ORG']);?></script>
<?endif;?>
