<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

$this->setFrameMode(true);

global $arTheme;
use Bitrix\Main\Localization\Loc;

$bOrderViewBasket = $arParams['ORDER_VIEW'];
$dataItem = TSolution::getDataItem($arResult);
$bOrderButton = $arResult['PROPERTIES']['FORM_ORDER']['VALUE_XML_ID'] == 'YES';

$totalCount = $arResult['OFFERS'] ? 0 : TSolution\Product\Quantity::getTotalCount([
    'ITEM' => $arResult,
    'PARAMS' => $arParams,
]);

$elementName = !empty($arResult['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE']) ? $arResult['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE'] : $arResult['NAME'];

$status = $arStatus['NAME'];
$statusCode = $arStatus['CODE'];
/* sku replace end */

// prices
$arPriceConfig = [
    'PRICE_CODE' => $arParams['PRICE_CODE'],
    'PRICE_FONT' => 16,
    'PRICEOLD_FONT' => 12,
];

$prices = new TSolution\Product\Prices(
    $arResult,
    $arParams,
    [
        'PRICE_FONT' => 16,
        'PRICEOLD_FONT' => 12,
    ]
);
?>

<div class="product-analog grid-list__item outer-rounded-x color-theme-parent-all js-popup-block">
    <div class="product-analog__title color_light font_13 p-block p-block--8 p-inline p-inline--20">
        <?=$arParams['BLOCK_TITLE'] ?: Loc::getMessage('OUT_OF_PRODUCTION_TITLE');?>
    </div>

    <div class="product-analog__content-wrapper outer-rounded-x white-bg bordered">
        <div class="product-analog__content p p--20">
            <div class="js-config-img" data-img-config='<?=str_replace('\'', '"', CUtil::PhpToJSObject($arImgConfig, false, true)); ?>'></div>

            <div class="product-analog__dynamic-content line-block line-block--gap line-block--gap-20 line-block--align-normal">
                <?php
                $arImgConfig = [
                    'TYPE' => 'catalog_block',
                    'ADDITIONAL_IMG_CLASS' => 'js-replace-img',
                    'ADDITIONAL_WRAPPER_CLASS' => 'product-analog__image',
                    'FV_WITH_ICON' => 'Y',
                    'FV_WITH_TEXT' => 'N',
                    'FV_BTN_CLASS' => 'fv-icon',
                    'FV_BTN_WRAPPER' => 'btn-fast-view-container btn-fast-view-container--cover'
                ];
                if ($arParams['IMG_CORNER'] == 'Y') {
                    $arImgConfig['ADDITIONAL_WRAPPER_CLASS'] .= ' catalog-block__item--img-corner';
                }

                TSolution\Product\Image::showImage(
                    array_merge(
                        [
                            'ITEM' => $arResult,
                            'PARAMS' => $arParams,
                        ],
                        $arImgConfig
                    )
                );
                ?>

                <div class="product-analog__info" data-item="<?=$dataItem;?>">
                    <div class="line-block line-block--column line-block--align-normal line-block--gap line-block--gap-16">
                        <div class="product-analog__top">
                            <div class="line-block line-block--column line-block--align-normal line-block--gap line-block--gap-4">
                                <div class="product-analog__price js-popup-price" data-price-config='<?=str_replace('\'', '"', CUtil::PhpToJSObject($arPriceConfig, false, true));?>'>
                                    <?$prices->show();?>
                                </div>

                                <div class="product-analog__name">
                                    <a href="<?=$arResult['DETAIL_PAGE_URL'];?>" class="js-popup-title dark_link color-theme-target">
                                        <?=$elementName;?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="product-analog__buttons">
                            <?php
                            $arBtnConfig = [
                                'BASKET_URL' => false,
                                'BASKET' => $bOrderViewBasket,
                                'BTN_CLASS_MORE' => 'btn-sm bg-theme-target border-theme-target',
                                'BTN_CLASS_SUBSCRIBE' => 'btn-sm',
                                'BTN_CLASS' => 'btn-sm btn-wide',
                                'BTN_IN_CART_CLASS' => 'btn-sm btn-wide',
                                'BTN_ORDER_CLASS' => 'btn-sm btn-wide btn-transparent-bg',
                                'CATALOG_IBLOCK_ID' => $arResult['IBLOCK_ID'],
                                'DETAIL_PAGE' => true,
                                'DISPLAY_COMPARE' => $arParams['DISPLAY_COMPARE'],
                                'ITEM_ID' => $arResult['ID'],
                                'ONE_CLICK_BUY' => false,
                                'ORDER_BTN' => $bOrderButton,
                                'SHOW_COUNTER' => false,
                                'SHOW_MORE' => (bool)$arResult['OFFERS'],
                            ];

                            $arBasketConfig = TSolution\Product\Basket::getOptions(array_merge(
                                $arBtnConfig,
                                [
                                    'ITEM' => $arResult,
                                    'PARAMS' => $arParams,
                                    'TOTAL_COUNT' => $totalCount,
                                    'HAS_PRICE' => $prices->isGreaterThanZero(),
                                    'EMPTY_PRICE' => $prices->isEmpty(),
                                    'IS_OFFER' => (bool)$arResult['OFFERS'],
                                ]
                            ));
                            ?>
                            <div class="line-block line-block--gap line-block--gap-16">
                                <div class="line-block__item js-btn-state-wrapper flex-1<?=!$arBasketConfig['HTML'] ? ' hidden' : ''; ?>">
                                    <div class="js-replace-btns js-config-btns" data-btn-config='<?=str_replace('\'', '"', CUtil::PhpToJSObject($arBtnConfig, false, true)); ?>'>
                                        <?=$arBasketConfig['HTML'];?>
                                    </div>
                                </div>

                                <?=TSolution\Product\Common::getActionIcons([
                                    'ITEM' => $arResult,
                                    'PARAMS' => $arParams,
                                    'SHOW_FAVORITE' => $arParams['SHOW_FAVORITE'],
                                    'SHOW_COMPARE' => $arParams['DISPLAY_COMPARE'],
                                ]);?>
                            </div>
                        </div>

                        <div class="product-analog__note secondary-color font_12">
                            <?=$arParams['BLOCK_NOTE'] ?: Loc::getMessage('OUT_OF_PRODUCTION_NOTE');?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
