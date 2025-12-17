<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

$arParams['DISPLAY_COMPARE'] = $arParams['DISPLAY_COMPARE'] ? 'Y' : 'N';

/* category path */
if (
    $arResult['IBLOCK_SECTION_ID']
    && !$arResult['CATEGORY_PATH']
) {
    $arCategoryPath = [];
    if (isset($arResult['SECTION']['PATH'])) {
        foreach ($arResult['SECTION']['PATH'] as $arCategory) {
            $arCategoryPath[$arCategory['ID']] = $arCategory['NAME'];
        }
    }

    $arResult['CATEGORY_PATH'] = implode('/', $arCategoryPath);
}

$arResult['HAS_SKU2'] = true;

if ($arResult['OFFERS']) {
    TSolution\Product\Prices::fixOffersMinPrice($arResult['OFFERS'], $arParams);
    $arResult['MIN_PRICE'] = TSolution\Product\Price::getMinPriceFromOffersExt($arResult['OFFERS']);
    $arResult['MAX_PRICE'] = TSolution\Product\Price::getMaxPriceFromOffersExt($arResult['OFFERS']);
}

$arResult['DETAIL_PICTURE'] = $arResult['DETAIL_PICTURE'] ?: $arResult['PREVIEW_PICTURE'];
