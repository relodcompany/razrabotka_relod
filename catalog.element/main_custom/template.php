<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}
if(CModule::IncludeModule('logictim.balls')){
	$APPLICATION->IncludeComponent(
		"logictim:bonus.catalog",
		"aspro_premier",
		Array(
			"COMPONENT_TEMPLATE" => ".default",
			"COMPOSITE_FRAME_MODE" => "A",
			"COMPOSITE_FRAME_TYPE" => "AUTO",
			"ITEMS" => array("ITEMS"=>$arResult)
		)
	);}
$this->setFrameMode(true);

// Предзаказ инициализируем позже, после вычисления статуса
$isPreorder = false;




global $arTheme;
use Bitrix\Main\Localization\Loc;

$bUseSchema = !(isset($arParams['NO_USE_SHCEMA_ORG']) && $arParams['NO_USE_SHCEMA_ORG'] == 'Y');
$bOrderViewBasket = $arParams['ORDER_VIEW'];
$basketURL = TSolution::GetFrontParametrValue('BASKET_PAGE_URL');
$dataItem = TSolution::getDataItem($arResult);
$bOrderButton = $arResult['PROPERTIES']['FORM_ORDER']['VALUE_XML_ID'] == 'YES';
$bAskButton = $arResult['PROPERTIES']['FORM_QUESTION']['VALUE_XML_ID'] == 'YES';
$bOcbButton = $arParams['SHOW_ONE_CLICK_BUY'] != 'N';
$bGallerythumbVertical = $arParams['GALLERY_THUMB_POSITION'] === 'vertical';
$cntVisibleChars = $arParams['VISIBLE_PROP_COUNT'];

$bShowRating = $arParams['SHOW_RATING'] == 'Y';
$bShowCompare = $arParams['DISPLAY_COMPARE'] == 'Y';
$bShowFavorit = $arParams['SHOW_FAVORITE'] == 'Y';
$bUseShare = $arParams['USE_SHARE'] == 'Y';
$bShowSendGift = $arParams['SHOW_SEND_GIFT'] === 'Y' && !$arResult['PRODUCT_ANALOG'];
$bShowCheaperForm = $arParams['SHOW_CHEAPER_FORM'] === 'Y' && !$arResult['PRODUCT_ANALOG'];
$bShowReview = $arParams['SHOW_REVIEW'] !== 'N';
$hasPopupVideo = (bool) $arResult['POPUP_VIDEO'];
$bShowCalculateDelivery = $arParams['CALCULATE_DELIVERY'] === 'Y' && !$arResult['PRODUCT_ANALOG'];
$bShowSKUDescription = $arParams['SHOW_SKU_DESCRIPTION'] === 'Y';

$templateData['USE_OFFERS_SELECT'] = false;

$arSkuTemplateData = [];
$bSKU2 = $arParams['TYPE_SKU'] === 'TYPE_2';
$bShowSkuProps = !$bSKU2;

$arSKUSetsData = [];
if ($arResult['SKU']['SKU_GROUP']) {
    $arSKUSetsData = [
        'IBLOCK_ID' => $arResult['SKU']['CURRENT']['IBLOCK_ID'],
        'ITEMS' => $arResult['SKU']['SKU_GROUP_VALUES'],
        'CURRENT_ID' => $arResult['SKU']['CURRENT']['ID'],
    ];
}

$bCrossAssociated = isset($arParams['CROSS_LINK_ITEMS']['ASSOCIATED']['VALUE']) && !empty($arParams['CROSS_LINK_ITEMS']['ASSOCIATED']['VALUE']);
$bCrossExpandables = isset($arParams['CROSS_LINK_ITEMS']['EXPANDABLES']['VALUE']) && !empty($arParams['CROSS_LINK_ITEMS']['EXPANDABLES']['VALUE']);

/* set array props for component_epilog */
$templateData = [
    'DETAIL_PAGE_URL' => $arResult['DETAIL_PAGE_URL'],
    'IBLOCK_SECTION_ID' => $arResult['IBLOCK_SECTION_ID'],
    'INCLUDE_FOLDER_PATH' => $arResult['INCLUDE_FOLDER_PATH'],
    'ORDER' => $bOrderViewBasket,
    'TIZERS' => [
        'IBLOCK_ID' => $arParams['IBLOCK_TIZERS_ID'],
        'VALUE' => $arResult['TIZERS'],
    ],
    'SALE' => TSolution\Functions::getCrossLinkedItems($arResult, ['LINK_SALE'], ['LINK_GOODS', 'LINK_GOODS_FILTER'], $arParams),
    'ARTICLES' => TSolution\Functions::getCrossLinkedItems($arResult, ['LINK_ARTICLES'], ['LINK_GOODS', 'LINK_GOODS_FILTER'], $arParams),
    'SERVICES' => TSolution\Functions::getCrossLinkedItems($arResult, ['SERVICES'], ['LINK_GOODS', 'LINK_GOODS_FILTER'], $arParams),
    'FAQ' => TSolution\Functions::getCrossLinkedItems($arResult, ['LINK_FAQ']),
    'ASSOCIATED' => $arParams['USE_ASSOCIATED_CROSS'] ? [] : TSolution\Functions::getCrossLinkedItems($arResult, ['ASSOCIATED', 'ASSOCIATED_FILTER']),
    'EXPANDABLES' => $arParams['USE_EXPANDABLES_CROSS'] ? [] : TSolution\Functions::getCrossLinkedItems($arResult, ['EXPANDABLES', 'EXPANDABLES_FILTER']),
    'CATALOG_SETS' => [
        'SET_ITEMS' => $arResult['SET_ITEMS'],
        'SKU_SETS' => $arSKUSetsData,
    ],
    'POPUP_VIDEO' => $hasPopupVideo,
    'RATING' => floatval($arResult['PROPERTIES']['EXTENDED_REVIEWS_RAITING'] ? $arResult['PROPERTIES']['EXTENDED_REVIEWS_RAITING']['VALUE'] : 0),
    'REVIEWS_COUNT' => intval($arResult['PROPERTIES']['EXTENDED_REVIEWS_COUNT'] ? $arResult['PROPERTIES']['EXTENDED_REVIEWS_COUNT']['VALUE'] : 0),
    'USE_SHARE' => $arParams['USE_SHARE'] === 'Y',
    'SHOW_REVIEW' => $bShowReview,
    'CALCULATE_DELIVERY' => $bShowCalculateDelivery,
    'BRAND' => $arResult['BRAND_ITEM'],
    'CUSTOM_BLOCKS_DATA' => [
        'PROPERTIES' => TSolution\Product\Blocks::getPropertiesByParams($arParams['CUSTOM_PROPERTY_DATA'], $arResult["PROPERTIES"]),
    ],
    'SHOW_CHARACTERISTICS' => false,
    'PRODUCT_ANALOG' => $arResult['PRODUCT_ANALOG'] ?? false
];
?>

<script>
	console.log("Hello, world");
</script>

<?if (TSolution::isSaleMode()):?>
    <div class="basket_props_block" id="bx_basket_div_<?=$arResult['ID']; ?>" style="display: none;">
        <?if (!empty($arResult['PRODUCT_PROPERTIES_FILL'])):?>
            <?foreach ($arResult['PRODUCT_PROPERTIES_FILL'] as $propID => $propInfo):?>
                <input type="hidden" name="<?=$arParams['PRODUCT_PROPS_VARIABLE']; ?>[<?=$propID; ?>]" value="<?=htmlspecialcharsbx($propInfo['ID']); ?>">
                <?php
                if (isset($arResult['PRODUCT_PROPERTIES'][$propID])) {
                    unset($arResult['PRODUCT_PROPERTIES'][$propID]);
                }
                ?>
            <?endforeach; ?>
        <?endif; ?>
        <?if ($arResult['PRODUCT_PROPERTIES']):?>
            <div class="wrapper">
                <?foreach($arResult['PRODUCT_PROPERTIES'] as $propID => $propInfo):?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group fill-animate">
                                <?if(
                                    $arResult['PROPERTIES'][$propID]['PROPERTY_TYPE'] == 'L'
                                    && $arResult['PROPERTIES'][$propID]['LIST_TYPE'] == 'C'
                                ):?>
                                    <label class="font_14"><span><?=$arResult['PROPERTIES'][$propID]['NAME']; ?></span></label>
                                    <?foreach($propInfo['VALUES'] as $valueID => $value):?>
                                        <div class="form-radiobox">
                                            <label class="form-radiobox__label">
                                                <input class="form-radiobox__input" type="radio" name="<?=$arParams['PRODUCT_PROPS_VARIABLE']; ?>[<?=$propID; ?>]" value="<?=$valueID; ?>">
                                                <span class="bx_filter_input_checkbox">
                                                    <span><?=$value; ?></span>
                                                </span>
                                                <span class="form-radiobox__box"></span>
                                            </label>
                                        </div>
                                    <?endforeach; ?>
                                <?else:?>
                                    <label class="font_14"><span><?=$arResult['PROPERTIES'][$propID]['NAME']; ?></span></label>
                                    <div class="input">
                                        <select class="form-control" name="<?=$arParams['PRODUCT_PROPS_VARIABLE']; ?>[<?=$propID; ?>]">
                                            <?foreach($propInfo['VALUES'] as $valueID => $value):?>
                                                <option value="<?=$valueID; ?>" <?= $valueID == $propInfo['SELECTED'] ? '"selected"' : ''; ?>><?=$value; ?></option>
                                            <?endforeach; ?>
                                        </select>
                                    </div>
                                <?endif; ?>
                            </div>
                        </div>
                    </div>
                <?endforeach; ?>
            </div>
        <?endif; ?>
    </div>
<?endif; ?>

<?// top banner?>
<?$templateData['SECTION_BNR_CONTENT'] = isset($arResult['PROPERTIES']['BNR_TOP']) && $arResult['PROPERTIES']['BNR_TOP']['VALUE_XML_ID'] == 'YES'; ?>
<?if($templateData['SECTION_BNR_CONTENT']):?>
    <?php
    $templateData['SECTION_BNR_UNDER_HEADER'] = $arResult['PROPERTIES']['BNR_TOP_UNDER_HEADER']['VALUE_XML_ID'];
    $templateData['SECTION_BNR_COLOR'] = $arResult['PROPERTIES']['BNR_TOP_COLOR']['VALUE_XML_ID'];
    $atrTitle = $arResult['PROPERTIES']['BNR_TOP_BG']['DESCRIPTION'] ?: $arResult['PROPERTIES']['BNR_TOP_BG']['TITLE'] ?: $arResult['NAME'];
    $atrAlt = $arResult['PROPERTIES']['BNR_TOP_BG']['DESCRIPTION'] ?: $arResult['PROPERTIES']['BNR_TOP_BG']['ALT'] ?: $arResult['NAME'];

    // buttons
    $bannerButtons = [
        [
            'TITLE' => $arResult['PROPERTIES']['BUTTON1TEXT']['VALUE'] ?? '',
            'CLASS' => 'btn choise '.($arResult['PROPERTIES']['BUTTON1CLASS']['VALUE_XML_ID'] ?? 'btn-default').' '.($arResult['PROPERTIES']['BUTTON1COLOR']['VALUE_XML_ID'] ?? ''),
            'ATTR' => [
                $arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID'] === 'scroll' || !$arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID']
                    ? 'data-block=".right_block .detail"'
                    : 'target="'.$arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID'].'"',
            ],
            'LINK' => $arResult['PROPERTIES']['BUTTON1LINK']['VALUE'],
            'TYPE' => $arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID'] === 'scroll' || !$arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID']
                ? 'anchor'
                : 'link',
        ],
    ];

    if($arResult['PROPERTIES']['BUTTON2TEXT']['VALUE'] && $arResult['PROPERTIES']['BUTTON2LINK']['VALUE']) {
        $bannerButtons[] = [
            'TITLE' => $arResult['PROPERTIES']['BUTTON2TEXT']['VALUE'],
            'CLASS' => 'btn choise '.($arResult['PROPERTIES']['BUTTON2CLASS']['VALUE_XML_ID'] ?? 'btn-default').' '.($arResult['PROPERTIES']['BUTTON2COLOR']['VALUE_XML_ID'] ?? ''),
            'ATTR' => [
                $arResult['PROPERTIES']['BUTTON2TARGET']['VALUE_XML_ID'] ? 'target="'.$arResult['PROPERTIES']['BUTTON2TARGET']['VALUE_XML_ID'].'"' : '',
            ],
            'LINK' => $arResult['PROPERTIES']['BUTTON2LINK']['VALUE'],
            'TYPE' => 'link',
        ];
    }
    ?>
    <?$this->SetViewTarget('section_bnr_content'); ?>
        <?TSolution\Functions::showBlockHtml([
            'FILE' => '/images/detail_banner.php',
            'PARAMS' => [
                'TITLE' => $arResult['NAME'],
                'COLOR' => $templateData['SECTION_BNR_COLOR'],
                'TEXT' => [
                    'TOP' => $arResult['SECTION'] ? reset($arResult['SECTION']['PATH'])['NAME'] : '',
                    'PREVIEW' => [
                        'TYPE' => $arResult['PREVIEW_TEXT_TYPE'],
                        'VALUE' => $arResult['PREVIEW_TEXT'],
                    ],
                ],
                'PICTURES' => [
                    'BG' => CFile::GetFileArray($arResult['PROPERTIES']['BNR_TOP_BG']['VALUE']),
                    'IMG' => CFile::GetFileArray($arResult['PROPERTIES']['BNR_TOP_IMG']['VALUE']),
                ],
                'BUTTONS' => $bannerButtons,
                'ATTR' => [
                    'ALT' => $atrAlt,
                    'TITLE' => $atrTitle,
                ],
                'TOP_IMG' => $bTopImg,
            ],
        ]); ?>
    <?$this->EndViewTarget(); ?>
<?endif; ?>

<?php
$article = $arResult['DISPLAY_PROPERTIES']['CML2_ARTICLE']['VALUE'];

// unset($arResult['OFFERS']); // get correct totalCount
$totalCount = TSolution\Product\Quantity::getTotalCount([
    'ITEM' => $arResult,
    'PARAMS' => $arParams,
]);
$arStatus = TSolution\Product\Quantity::getStatus([
    'ITEM' => $arResult,
    'PARAMS' => $arResult['HAS_SKU2'] ? array_merge($arParams, ['CATALOG_SHOW_AMOUNT_STORES' => 'N']) : $arParams,
    'TOTAL_COUNT' => $totalCount,
    'IS_DETAIL' => true,
]);

/* sku replace start */
$arCurrentOffer = $arResult['SKU']['CURRENT'];
$elementName = !empty($arResult['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE']) ? $arResult['IPROPERTY_VALUES']['ELEMENT_PAGE_TITLE'] : $arResult['NAME'];
$bShowSelectOffer = $arCurrentOffer && $bShowSkuProps;
$templateData['CURRENT_OFFER_ID'] = $bShowSelectOffer ? $arCurrentOffer['ID'] : null;

if ($bShowSelectOffer) {
    $arResult['PARENT_IMG'] = '';
    if ($arResult['PREVIEW_PICTURE']) {
        $arResult['PARENT_IMG'] = $arResult['PREVIEW_PICTURE'];
    } elseif ($arResult['DETAIL_PICTURE']) {
        $arResult['PARENT_IMG'] = $arResult['DETAIL_PICTURE'];
    }

    $arResult['DETAIL_PAGE_URL'] = $arCurrentOffer['DETAIL_PAGE_URL'];

    if ($arParams['SHOW_GALLERY'] === 'Y') {
        if(!$arCurrentOffer['DETAIL_PICTURE'] && $arCurrentOffer['PREVIEW_PICTURE']) {
            $arCurrentOffer['DETAIL_PICTURE'] = $arCurrentOffer['PREVIEW_PICTURE'];
        }

        $arOfferGallery = TSolution\Functions::getSliderForItem([
            'TYPE' => 'catalog_block',
            'PROP_CODE' => $arParams['OFFER_ADD_PICT_PROP'],
            // 'ADD_DETAIL_SLIDER' => false,
            'ITEM' => $arCurrentOffer,
            'PARAMS' => $arParams,
        ]);
        if ($arOfferGallery) {
            $arResult['GALLERY'] = array_merge($arOfferGallery, $arResult['GALLERY']);
        }
    } else {
        if ($arCurrentOffer['PREVIEW_PICTURE'] || $arCurrentOffer['DETAIL_PICTURE']) {
            if ($arCurrentOffer['PREVIEW_PICTURE']) {
                $arResult['PREVIEW_PICTURE'] = $arCurrentOffer['PREVIEW_PICTURE'];
            } elseif ($arCurrentOffer['DETAIL_PICTURE']) {
                $arResult['PREVIEW_PICTURE'] = $arCurrentOffer['DETAIL_PICTURE'];
            }
        }
    }
    if (!$arCurrentOffer['PREVIEW_PICTURE'] && !$arCurrentOffer['DETAIL_PICTURE']) {
        if ($arResult['PREVIEW_PICTURE']) {
            $arCurrentOffer['PREVIEW_PICTURE'] = $arResult['PREVIEW_PICTURE'];
        } elseif ($arResult['DETAIL_PICTURE']) {
            $arCurrentOffer['PREVIEW_PICTURE'] = $arResult['DETAIL_PICTURE'];
        }
    }

    if ($arCurrentOffer['DISPLAY_PROPERTIES']['CML2_ARTICLE']['VALUE'] || $arCurrentOffer['DISPLAY_PROPERTIES']['ARTICLE']['VALUE']) {
        $article = $arCurrentOffer['DISPLAY_PROPERTIES']['CML2_ARTICLE']['VALUE'] ?? $arCurrentOffer['DISPLAY_PROPERTIES']['ARTICLE']['VALUE'];
    }

    $arResult['DISPLAY_PROPERTIES']['FORM_ORDER'] = $arCurrentOffer['DISPLAY_PROPERTIES']['FORM_ORDER'];
    $arResult['DISPLAY_PROPERTIES']['PRICE'] = $arCurrentOffer['DISPLAY_PROPERTIES']['PRICE'];

    if($arParams['SET_SKU_TITLE'] !== 'N') {
        $arResult['NAME'] = $arCurrentOffer['NAME'];
        $elementName = $arCurrentOffer['NAME'];
    }

    $arResult['OFFER_PROP'] = TSolution::PrepareItemProps($arCurrentOffer['DISPLAY_PROPERTIES']);
    TSolution\LinkableProperty::resolve($arResult['OFFER_PROP'], $arCurrentOffer['IBLOCK_ID'], $arResult['IBLOCK_SECTION_ID']);

    $dataItem = TSolution::getDataItem($arCurrentOffer);

    $totalCount = TSolution\Product\Quantity::getTotalCount([
        'ITEM' => $arCurrentOffer,
        'PARAMS' => $arParams,
    ]);
    $arStatus = TSolution\Product\Quantity::getStatus([
        'ITEM' => $arCurrentOffer,
        'PARAMS' => $arParams,
        'TOTAL_COUNT' => $totalCount,
        'IS_DETAIL' => true,
    ]);
}

$status = $arStatus['NAME'];
$statusCode = $arStatus['CODE'];

// Строгое правило: предзаказ только если статус на карточке = "Под заказ"
$isPreorder = false;
$statusLower = mb_strtolower(trim((string)$status));
if ($statusLower !== '' && mb_strpos($statusLower, 'под заказ') !== false) {
    $isPreorder = true;
}



/* sku replace end */
?>

<?// detail description?>
<?$bSKUDescription = $bShowSKUDescription && strlen($arResult['SKU']['CURRENT']['DETAIL_TEXT']); ?>
<?$templateData['DETAIL_TEXT'] = boolval(strlen($arResult['DETAIL_TEXT']) || $bSKUDescription); ?>
<?if($templateData['DETAIL_TEXT']):?>
    <?$this->SetViewTarget('PRODUCT_DETAIL_TEXT_INFO'); ?>
        <div class="content content--max-width js-detail-description" itemprop="description">
            <?if($bSKUDescription):?>
                <?=$arResult['SKU']['CURRENT']['DETAIL_TEXT']; ?>
            <?else:?>
                <?=$arResult['DETAIL_TEXT']; ?>
            <?endif; ?>
        </div>
    <?$this->EndViewTarget(); ?>
<?endif; ?>

<?// files?>
<?$templateData['DOCUMENTS'] = boolval($arResult['DOCUMENTS']); ?>
<?if ($templateData['DOCUMENTS']):?>
    <?$this->SetViewTarget('PRODUCT_FILES_INFO'); ?>
        <?TSolution\Functions::showBlockHtml([
            'FILE' => '/documents.php',
            'PARAMS' => [
                'ITEMS' => $arResult['DOCUMENTS'],
            ],
        ]); ?>
    <?$this->EndViewTarget(); ?>
<?endif; ?>

<?// big gallery?>
<?$templateData['BIG_GALLERY'] = boolval($arResult['BIG_GALLERY']); ?>
<?if($arResult['BIG_GALLERY']):?>
    <?$this->SetViewTarget('PRODUCT_BIG_GALLERY_INFO'); ?>
        <?TSolution\Functions::showGallery($arResult['BIG_GALLERY'], [
            'CONTAINER_CLASS' => 'gallery-detail font_13',
        ]); ?>
    <?$this->EndViewTarget(); ?>
<?endif; ?>

<?// video?>
<?$templateData['VIDEO'] = boolval($arResult['VIDEO']);
$bOneVideo = count((array) $arResult['VIDEO']) == 1;
?>
<?if($arResult['VIDEO']):?>
    <?$this->SetViewTarget('PRODUCT_VIDEO_INFO'); ?>
        <?TSolution\Functions::showBlockHtml([
            'FILE' => 'video/detail_video_block.php',
            'PARAMS' => [
                'VIDEO' => $arResult['VIDEO'],
            ],
        ]); ?>
    <?$this->EndViewTarget(); ?>
<?endif; ?>

<?// ask question?>
<?if($bAskButton):?>
    <?if($arParams['LEFT_BLOCK_CATALOG_DETAIL'] === 'N'):?>
        <?$this->SetViewTarget('PRODUCT_SIDE_INFO'); ?>
    <?else:?>
        <?$this->SetViewTarget('under_sidebar_content'); ?>
    <?endif; ?>
        <div class="ask-block bordered rounded-4">
            <div class="ask-block__container">
                <div class="ask-block__icon">
                    <?=TSolution::showIconSvg('ask colored', SITE_TEMPLATE_PATH.'/images/svg/Question_lg.svg'); ?>
                </div>
                <div class="ask-block__text text-block color_666 font_14">
                    <?=$arResult['INCLUDE_ASK']; ?>
                </div>
                <div class="ask-block__button">
                    <div class="btn btn-default btn-transparent-bg animate-load" data-event="jqm" data-param-id="<?=TSolution::getFormID(VENDOR_PARTNER_NAME.'_'.VENDOR_SOLUTION_NAME.'_question'); ?>" data-autoload-need_product="<?=TSolution::formatJsName($arResult['NAME']); ?>" data-name="question">
                        <span><?=htmlspecialcharsbx(TSolution::GetFrontParametrValue('EXPRESSION_FOR_ASK_QUESTION')); ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?$this->EndViewTarget(); ?>
<?endif; ?>

<?php
/* gifts */
if ($arParams['USE_GIFTS_DETAIL'] === 'Y') {
    $templateData['GIFTS'] = [
        'ADD_URL_TEMPLATE' => $arResult['~ADD_URL_TEMPLATE'],
        'BUY_URL_TEMPLATE' => $arResult['~BUY_URL_TEMPLATE'],
        'SUBSCRIBE_URL_TEMPLATE' => $arResult['~SUBSCRIBE_URL_TEMPLATE'],
        'POTENTIAL_PRODUCT_TO_BUY' => [
            'ID' => $arResult['ID'],
            'MODULE' => $arResult['MODULE'] ?? 'catalog',
            'PRODUCT_PROVIDER_CLASS' => $arResult['PRODUCT_PROVIDER_CLASS'] ?? 'CCatalogProductProvider',
            'QUANTITY' => $arResult['QUANTITY'] ?? '',
            'IBLOCK_ID' => $arResult['IBLOCK_ID'],

            'PRIMARY_OFFER_ID' => $arResult['OFFERS'][0]['ID'] ?? '',
            'SECTION' => [
                'ID' => $arResult['SECTION']['ID'] ?? '',
                'IBLOCK_ID' => $arResult['SECTION']['IBLOCK_ID'] ?? '',
                'LEFT_MARGIN' => $arResult['SECTION']['LEFT_MARGIN'] ?? '',
                'RIGHT_MARGIN' => $arResult['SECTION']['RIGHT_MARGIN'] ?? '',
            ],
        ],
    ];
}
?>


<div class="catalog-detail__top-info flexbox flexbox--direction-row flexbox--wrap-nowrap gap gap--40">
    <?php
    // add to viewed
    TSolution\Product\Common::addViewed([
        'ITEM' => $arCurrentOffer ?: $arResult,
    ]);
?>

    <?if ($arResult['SKU_CONFIG']):?><div class="js-sku-config hidden" data-value='<?=str_replace('\'', '"', CUtil::PhpToJSObject($arResult['SKU_CONFIG'], false, true)); ?>'></div><?endif; ?>
    <?if ($arResult['SKU']['PROPS']):?>
        <template class="offers-template-json">
            <?=TSolution\SKU::getOfferTreeJson($arResult['SKU']['OFFERS']); ?>
        </template>
        <?$templateData['USE_OFFERS_SELECT'] = true; ?>
    <?endif; ?>


    <?$topGalleryClassList = '';
    if ($hasPopupVideo) {
        $topGalleryClassList .= ' detail-gallery-big--with-video';
    }?>
    <div class="detail-gallery-big <?=$topGalleryClassList; ?> swipeignore image-list__link">
        <div class="sticky-block">
            <div class="detail-gallery-big-wrapper">
                <?php
                $countPhoto = count($arResult['GALLERY']);
                $isMoreThanOnePhoto = ($countPhoto > 1);

                $arFirstPhoto = reset($arResult['GALLERY']);
                $urlFirstPhoto = $arFirstPhoto['BIG']['src'] ? $arFirstPhoto['BIG']['src'] : $arFirstPhoto['SRC'];
                ?>

                <link href="<?=$urlFirstPhoto; ?>" itemprop="image"/>

                <?php
                $gallerySetting = [
                    'MAIN' => [
                        'SLIDE_CLASS_LIST' => 'detail-gallery-big__item detail-gallery-big__item--big swiper-slide',
                        'PLUGIN_OPTIONS' => [
                            'direction' => 'horizontal',
                            'init' => false,
                            'keyboard' => [
                                'enabled' => true,
                            ],
                            'loop' => false,
                            'pagination' => [
                                'enabled' => true,
                                'el' => '.detail-gallery-big-slider-main .swiper-pagination',
                            ],
                            'navigation' => [
                                'nextEl' => '.detail-gallery-big-slider-main .swiper-button-next',
                                'prevEl' => '.detail-gallery-big-slider-main .swiper-button-prev',
                            ],
                            'slidesPerView' => 1,
                            'thumbs' => [
                                'swiper' => '.gallery-slider-thumb',
                            ],
                            'type' => 'detail_gallery_main',
                            'preloadImages' => false,
                        ],
                    ],
                    'THUMBS' => [
                        'SLIDE_CLASS_LIST' => 'gallery__item gallery__item--thumb swiper-slide rounded-x pointer',
                        'PLUGIN_OPTIONS' => [
                            'direction' => ($bGallerythumbVertical ? 'vertical' : 'horizontal'),
                            'init' => false,
                            'spaceBetween' => 4,
                            'loop' => false,
                            'navigation' => [
                                'nextEl' => '.gallery-slider-thumb-button--next',
                                'prevEl' => '.gallery-slider-thumb-button--prev',
                            ],
                            'pagination' => false,
                            'slidesPerView' => 'auto',
                            'type' => 'detail_gallery_thumb',
                            'watchSlidesProgress' => true,
                            'preloadImages' => false,
                        ],
                    ],
                ];
                ?>

                <?TSolution\Functions::showBlockHtml([
                    'FILE' => '/catalog/images/detail_top_gallery.php',
                    'ITEM' => $arResult,
                    'PARAMS' => [
                        'arParams' => $arParams,
                        'gallerySetting' => $gallerySetting,
                        'countPhoto' => $countPhoto,
                        'isMoreThanOnePhoto' => $isMoreThanOnePhoto,
                        'bShowSelectOffer' => $bShowSelectOffer,
                        'hasPopupVideo' => $hasPopupVideo,
                    ],
                ]); ?>
            </div>
        </div>
    </div>

    <div class="catalog-detail__main">
        <?// discount counter?>
        <?ob_start(); ?>
        <?if ($arParams['SHOW_DISCOUNT_TIME'] === 'Y' && $arParams['SHOW_DISCOUNT_TIME_IN_LIST'] !== 'N'):?>
            <?php
            $discountDateTo = '';
            if (TSolution::isSaleMode()) {
                $arDiscount = TSolution\Product\Price::getDiscountByItemID($arResult['ID']);
                $discountDateTo = $arDiscount ? $arDiscount['ACTIVE_TO'] : '';
            } else {
                $discountDateTo = $arResult['DISPLAY_PROPERTIES']['DATE_COUNTER']['VALUE'];
            }

            if ($discountDateTo) {
                TSolution\Functions::showDiscountCounter([
                    'ICONS' => true,
                    'DATE' => $discountDateTo,
                    'ITEM' => $arResult,
                ]);
            }
            ?>
        <?endif; ?>
        <?$itemDiscount = ob_get_clean(); ?>

        <div class="catalog-detail__main-parts line-block line-block--gap line-block--gap-40" data-id="<?=$arResult['ID']; ?>" data-item="<?=$dataItem; ?>">
            <div class="catalog-detail__main-part catalog-detail__main-part--left flex-1 width-100 line-block__item">
                <div class="grid-list grid-list--items gap gap--24">
                    <div class="grid-list grid-list--items gap gap--16">
                        <div>
                            <?TSolution\Product\Common::showStickers([
                                'TYPE' => '',
                                'ITEM' => $arResult,
                                'PARAMS' => $arParams,
                                'DOP_CLASS' => 'sticker--static mb mb--8',
                                'CONTENT' => $itemDiscount,
                            ]); ?>
                            <h1 class="font_24 switcher-title js-popup-title mb mb--0"><?=$elementName; ?></h1>
                            <?TSolution\Product\Common::showSubTitle($arResult, 'font_14 mt mt--8');?>
                        </div>

                        <?php
                        $isShowProductStatsByLeft = (strlen($article) || $bShowRating);
                        $isShowProductStatsByRight = $bShowCompare || $bShowFavorit || $bUseShare;
                        $isShowProductStats = $isShowProductStatsByLeft || $isShowProductStatsByRight;
                        ?>
                        <?if ($isShowProductStats):?>
                            <div class="catalog-detail__info-tc">
                                <div class="line-block line-block--gap line-block--gap-20 line-block--align-normal flexbox--justify-between">
                                    <div class="line-block__item">
                                        <?if ($isShowProductStatsByLeft):?>
                                            <div class="catalog-detail__info-tech">
                                                <div class="line-block line-block--gap line-block--gap-16 line-block--row-gap line-block--row-gap-8 flexbox--wrap js-popup-info">
                                                    <?if ($bShowRating):?>
                                                        <div class="line-block__item font_13">
                                                            <?=TSolution\Product\Common::getRatingHtml([
                                                                'ITEM' => $arResult,
                                                                'PARAMS' => $arParams,
                                                                'SHOW_REVIEW_COUNT' => $bShowReview,
                                                                'SVG_SIZE' => [
                                                                    'WIDTH' => 12,
                                                                    'HEIGHT' => 12,
                                                                ],
                                                            ]); ?>
                                                        </div>
                                                    <?endif; ?>

                                                    <?if (strlen($article)):?>
                                                        <div class="line-block__item font_13 secondary-color">
                                                            <span class="article"><?=GetMessage('S_ARTICLE'); ?>&nbsp;<span
                                                                class="js-replace-article"
                                                                data-value="<?=$arResult['DISPLAY_PROPERTIES']['CML2_ARTICLE']['VALUE']; ?>"
                                                            ><?=$article; ?></span></span>
                                                        </div>
                                                    <?endif; ?>
                                                </div>
                                            </div>
                                        <?endif; ?>
                                    </div>

                                    <?if ($isShowProductStatsByRight):?>
                                        <div class="line-block__item no-shrinked">
                                            <div class="flexbox flexbox--row gap gap--16">
                                                <?if (!$arResult['HAS_SKU2']):?>
                                                    <?=TSolution\Product\Common::getActionIcons([
                                                        'ITEM' => $arCurrentOffer ?: $arResult,
                                                        'PARAMS' => $arParams,
                                                        'SHOW_FAVORITE' => $arParams['SHOW_FAVORITE'],
                                                        'SHOW_COMPARE' => $arParams['DISPLAY_COMPARE'],
                                                    ]); ?>
                                                <?endif; ?>

                                                <?if ($bUseShare):?>
                                                    <?php TSolution\Functions::showShareBlock([
                                                        'INNER_CLASS' => 'item-action__inner',
                                                        'CLASS' => 'item-action item-action--horizontal',
                                                        'SVG_SIZE' => ['WIDTH' => 20, 'HEIGHT' => 20],
                                                    ]); ?>
                                                <?endif; ?>
                                            </div>
                                        </div>
                                    <?endif; ?>
                                </div>
                            </div>
                        <?endif; ?>

                        <div class="border-bottom"></div>
                    </div>

                    <?if ($templateData['PRODUCT_ANALOG']):?>
                        <div class="visible-by-container-rule visible-by-container-rule--ignore-hidden">#<?=$templateData['PRODUCT_ANALOG']['MARKER'];?>#</div>
                    <?endif;?>

                    <?php
                    $arPriceConfig = [
                        'PRICE_CODE' => $arParams['PRICE_CODE'],
                        'PRICE_FONT' => 24,
                        'PRICEOLD_FONT' => 16,
                    ];

                    $prices = new TSolution\Product\Prices(
                        $arCurrentOffer ? $arCurrentOffer : $arResult,
                        $arParams,
                        [
                            'PRICE_FONT' => 24,
                            'PRICEOLD_FONT' => 15,
                            'SHOW_SCHEMA' => false
                        ]
                    );
                    ?>
                    <div class="visible-by-container-rule">
                        <div class="line-block__item catalog-detail__price catalog-detail__info--margined js-popup-price" data-price-config='<?=str_replace('\'', '"', CUtil::PhpToJSObject($arPriceConfig, false, true)); ?>'>
                            <?$prices->show(); ?>
                        </div>
						<div class="lb_bonus lb_ajax_<?=$arResult["ID"]?>" data-item="<?=$arResult["ID"]?>"></div>
                    </div>

                    <?if ($bShowSkuProps && $arResult['SKU']['PROPS']):?>
                        <div class="grid-list__item catalog-detail__offers">
                            <div
                            class="sku-props sku-props--detail sku-props--reset-margin-top grid-list grid-list--items gap gap--24"
                            data-site-id="<?=SITE_ID; ?>"
                            data-item-id="<?=$arResult['ID']; ?>"
                            data-iblockid="<?=$arResult['IBLOCK_ID']; ?>"
                            data-offer-id="<?=$arCurrentOffer['ID']; ?>"
                            data-offer-iblockid="<?=$arCurrentOffer['IBLOCK_ID']; ?>"
                            data-offers-id='<?=str_replace('\'', '"', CUtil::PhpToJSObject($GLOBALS[$arParams['FILTER_NAME']]['OFFERS_ID'], false, true)); ?>'
                            >
                                <?=TSolution\SKU\Template::showSkuPropsHtml($arResult['SKU']['PROPS']);?>
                            </div>
                            <?php // table sizes?>
                            <?if ($arResult['SIZE_PATH']):?>
                                <div class="catalog-detail__pseudo-link catalog-detail__pseudo-link--with-gap table-sizes mt mt--8">
                                    <button type="button" class="btn--no-btn-appearance font_13 link-opacity-color link-opacity-color--hover"
                                        data-event="jqm"
                                        data-param-form_id="include_block"
                                        data-param-url="<?= $arResult['SIZE_PATH']; ?>"
                                        data-param-block_title="<?= urlencode(TSolution::formatJsName(GetMessage('TABLE_SIZES'))); ?>"
                                        data-name="include_block"
                                    >
                                        <span class="dotted"><?= GetMessage('TABLES_SIZE'); ?></span>
                                    </button>
                                </div>
                            <?endif; ?>
                        </div>
                    <?endif; ?>

                    <?ob_start(); ?>
                        <?if ($arResult['HAS_SKU2']):?>
                            <div class="catalog-detail__cart border-bottom pb pb--24">
                                <?=TSolution\Product\Basket::getAnchorButton([
                                    'BTN_NAME' => TSolution::GetFrontParametrValue('EXPRESSION_READ_MORE_OFFERS_DEFAULT'),
                                    'BTN_CLASS_MORE' => 'btn-elg btn-wide',
                                    'BLOCK' => 'sku',
                                ]); ?>
                            </div>
                        <?else:?>
                            <?php
                            $arBtnConfig = [
                                'BASKET_URL' => $basketURL,
                                'BASKET' => $bOrderViewBasket,
                                'DETAIL_PAGE' => true,
                                'ORDER_BTN' => $bOrderButton,
                                'BTN_CLASS' => 'btn-elg btn-wide',
                                'BTN_CLASS_MORE' => 'btn-elg btn-wide',
                                'BTN_CLASS_SUBSCRIBE' => 'btn-elg btn-wide btn-transparent',
                                'BTN_IN_CART_CLASS' => 'btn-elg btn-wide',
                                'BTN_CALLBACK_CLASS' => 'btn-transparent-border',
                                'BTN_OCB_CLASS' => 'btn-wide btn-transparent-bg btn-lg btn-ocb',
                                'BTN_OCB_WRAPPER_HIDE_MOBILE' => 'N',
                                'BTN_ORDER_CLASS' => 'btn-wide btn-transparent-bg btn-elg',
                                'SHOW_COUNTER' => false,
                                'ONE_CLICK_BUY' => $bOcbButton,
                                'QUESTION_BTN' => $bAskButton,
                                'DISPLAY_COMPARE' => $arParams['DISPLAY_COMPARE'],
                                'CATALOG_IBLOCK_ID' => $arResult['IBLOCK_ID'],
                                'ITEM_ID' => $arResult['ID'],
                            ];

                            $arBasketConfig = TSolution\Product\Basket::getOptions(array_merge(
                                $arBtnConfig,
                                [
                                    'ITEM' => ($arCurrentOffer ? $arCurrentOffer : $arResult),
                                    'PARAMS' => $arParams,
                                    'TOTAL_COUNT' => $totalCount,
                                    'HAS_PRICE' => $prices->isGreaterThanZero(),
                                    'EMPTY_PRICE' => $prices->isEmpty(),
                                    'IS_OFFER' => (bool)$arCurrentOffer,
                                ]
                            ));
                            ?>

                            <div class="catalog-detail__cart js-replace-btns js-config-btns<?= $arBasketConfig['HTML'] ? '' : ' hidden'; ?>"
     data-preorder="<?= $isPreorder ? '1' : '0' ?>"
     data-btn-config='<?=str_replace('\'', '"', CUtil::PhpToJSObject($arBtnConfig, false, true)); ?>'>
    <?=$arBasketConfig['HTML']; ?>
</div>

                        <?endif; ?>
                    <?php
                    $btnHtml = trim(ob_get_contents());
                    ob_end_clean();
                    ?>

                    <?ob_start(); ?>
                        <div class="grid-list__item">
                            <div class="catalog-detail__forms grid-list grid-list--items font_13">
                                <?// status?>
                                <?if (strlen($status)):?>
                                    <div class="grid-list__item">
                                        <?TSolution\Product\Quantity::show(
                                            $statusCode,
                                            $status,
                                            [
                                                'USE_SHEMA_ORG' => false,
                                                'IS_DETAIL' => true,
                                            ]
                                        ); ?>
                                    </div>
                                <?endif; ?>

                                <?if ($bShowCalculateDelivery && !$arResult['HAS_SKU2']):?>
                                    <div class="grid-list__item">
                                        <?php
                                        $arConfig = [
                                            'NAME' => $arParams['EXPRESSION_FOR_CALCULATE_DELIVERY'],
                                            'SVG_NAME' => 'delivery',
                                            'SVG_SIZE' => ['WIDTH' => 16, 'HEIGHT' => 15],
                                            'SVG_PATH' => '/catalog/item_order_icons.svg#delivery',
                                            'WRAPPER' => 'stroke-dark-light-block dark_link animate-load',
                                            'DATA_ATTRS' => [
                                                'event' => 'jqm',
                                                'param-form_id' => 'delivery',
                                                'name' => 'delivery',
                                                'param-product_id' => $arCurrentSKU ? $arCurrentSKU['ID'] : $arResult['ID'],
                                            ],
                                        ];

                                    if ($arParams['USE_REGION'] === 'Y' && $arParams['STORES'] && is_array($arParams['STORES'])) {
                                        $arConfig['DATA_ATTRS']['param-region_stores_id'] = implode(',', $arParams['STORES']);
                                    }
                                    ?>
                                        <?=TSolution\Product\Common::showModalBlock($arConfig); ?>
                                        <?unset($arConfig); ?>
                                    </div>
                                <?endif; ?>

                                <?if ($bShowCheaperForm):?>
                                    <div class="grid-list__item">
                                        <?=TSolution\Product\Common::showModalBlock([
                                        'NAME' => $arParams['CHEAPER_FORM_NAME'],
                                        'SVG_NAME' => 'valet',
                                        'SVG_PATH' => '/catalog/item_order_icons.svg#valet',
                                        'SVG_SIZE' => ['WIDTH' => 16, 'HEIGHT' => 16],
                                        'WRAPPER' => 'stroke-dark-light-block dark_link animate-load',
                                        'DATA_ATTRS' => [
                                            'event' => 'jqm',
                                            'param-id' => TSolution::partnerName.'_'.TSolution::solutionName.'_cheaper',
                                            'name' => 'cheaper',
                                            'autoload-product_name' => TSolution::formatJsName($arCurrentSKU ? $arCurrentSKU['NAME'] : $arResult['NAME']),
                                            'autoload-product_id' => $arCurrentSKU ? $arCurrentSKU['ID'] : $arResult['ID'],
                                        ],
                                    ]); ?>
                                    </div>
                                <?endif; ?>

                                <?if ($bShowSendGift):?>
                                    <div class="grid-list__item">
                                        <?=TSolution\Product\Common::showModalBlock([
                                        'NAME' => $arParams['SEND_GIFT_FORM_NAME'],
                                        'SVG_NAME' => 'gift',
                                        'WRAPPER' => 'stroke-dark-light-block dark_link animate-load',
                                        'SVG_SIZE' => ['WIDTH' => 16, 'HEIGHT' => 17],
                                        'SVG_PATH' => '/catalog/item_order_icons.svg#gift',
                                        'DATA_ATTRS' => [
                                            'event' => 'jqm',
                                            'param-id' => TSolution::partnerName.'_'.TSolution::solutionName.'_send_gift',
                                            'name' => 'send_gift',
                                            'autoload-product_name' => TSolution::formatJsName($arResult['NAME']),
                                            'autoload-product_link' => (CMain::IsHTTPS() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$APPLICATION->GetCurPage(),
                                            'autoload-product_id' => $arResult['ID'],
                                        ],
                                    ]); ?>
                                    </div>
                                <?endif; ?>

                                <?if (trim(strip_tags($arResult['INCLUDE_CONTENT']))):?>
                                    <div class="grid-list__item">
                                        <?=TSolution\Product\Common::showModalBlock([
                                        'SVG_NAME' => 'gift',
                                        'SVG_PATH' => '/catalog/item_order_icons.svg#attention-16-16',
                                        'USE_SIZE_IN_PATH' => false,
                                        'SVG_SIZE' => ['WIDTH' => 17, 'HEIGHT' => 16],
                                        'TEXT' => $arResult['INCLUDE_CONTENT'],
                                        'WRAPPER' => 'fill-dark-light color_555',
                                    ]); ?>
                                    </div>
                                <?endif; ?>
                            </div>
                        </div>
                    <?php
                    $formsHtml = trim(ob_get_contents());
                    ob_end_clean();
                    ?>


<div class="visible-by-container-rule">
    <?=$btnHtml;?>
</div>
                    <div class="visible-by-container-rule">
                        <?=$formsHtml; ?>
                    </div>
                    <?
                    $cntChars = count($arResult['CHARACTERISTICS']) + count((array)$arResult['OFFER_PROP']);
                    $templateData['SHOW_CHARACTERISTICS'] = true;
                    $templateData['VISIBLE_PROPS_BLOCK'] = $cntChars > $cntVisibleChars;
                    ?>
                    <?TSolution\Functions::showBlockHtml([
                    'FILE' => '/catalog/props_in_section.php',
                    'ITEM' => $arResult,
                    'PARAMS' => [
                        'SHOW_HINTS' => $arParams['SHOW_HINTS'] === 'Y',
                        'TITLE' => ($arParams["T_CHARACTERISTICS"] ?: Loc::getMessage("T_CHARACTERISTICS")),
                        'VISIBLE_PROP_COUNT' => $cntVisibleChars,
                        'WRAPPER_CLASSES' => ($cntChars ? 'mt mt--8' : 'hidden'),
                        'SHOW_TAB_LINK' => $templateData['VISIBLE_PROPS_BLOCK'],
                        'FONT_CLASSES' => 'font_13',
                        'HIDE_MOBILE' => false,
                        'GAP_SIZE' => '6',
                        'TEXT_CLASSES' => 'secondary-color',
                        'IS_DETAIL' => true,
                    ],
                    ]);?>
                    <?$bSKUPreviewDescription = $bShowSKUDescription && strlen($arResult['SKU']['CURRENT']['PREVIEW_TEXT']); ?>
                    <?$showPreviewText = boolval(strlen($arResult['PREVIEW_TEXT']) || $bSKUPreviewDescription); ?>
                    <?if($showPreviewText):?>
                        <div class="grid-list__item catalog-detail__previewtext" itemprop="description">
                            <div class="fw-500 font_14 color_222"><?=$arParams['T_DESC']; ?></div>

                            <div class="text-block font_13 no-margin-p mt mt--8 lineclamp-4 js-preview-description">
                                <?if($bSKUPreviewDescription):?>
                                    <?=$arResult['SKU']['CURRENT']['PREVIEW_TEXT']; ?>
                                <?else:?>
                                    <?=$arResult['PREVIEW_TEXT']; ?>
                                <?endif; ?>
                            </div>

                            <?$isShowMoreLinkForPreviewText = strlen($arResult['DETAIL_TEXT']); ?>
                            <?if($isShowMoreLinkForPreviewText):?>
                                <span class="catalog-detail__pseudo-link link-opacity-color link-opacity-color--hover pointer font_13 mt mt--8">
                                    <span class="choise dotted" data-block="desc"><?=Loc::getMessage('MORE_TEXT_BOTTOM'); ?></span>
                                </span>
                            <?endif; ?>
                        </div>
                    <?endif; ?>

                </div>
            </div>

            <div class="catalog-detail__main-part catalog-detail__main-part--right sticky-block flex-1 line-block__item grid-list--fill-bg">
                <div class="grid-list grid-list--items gap gap--24">
                    <?if ($templateData['PRODUCT_ANALOG']):?>
                        <div class="grid-list__item hidden-by-container-rule">#<?=$templateData['PRODUCT_ANALOG']['MARKER'];?>#</div>
                    <?endif;?>

                    <div class="grid-list__item<?= ($btnHtml || $prices->isFilled()) ? '' : ' hidden'; ?> hidden-by-container-rule">
<?php
$linkSource = !empty($arCurrentOffer) ? $arCurrentOffer : $arResult;
$detailLink = trim($linkSource['PROPERTIES']['BUY_LINK']['VALUE']);
?>
<div class="catalog-detail__buy-block catalog-detail__cell-block outer-rounded-x bordered p p--20 pt pt--24"
     data-preorder="<?= $isPreorder ? '1' : '0' ?>">
  <div class="js-popup-block-adaptive grid-list grid-list--items gap gap--24">

    <?php if (!$arResult['PRODUCT_ANALOG']): ?>
      <div class="grid-list__item">
        <div class="catalog-detail__price"
             data-price-config='<?= CUtil::PhpToJSObject($arPriceConfig, false, true) ?>'>
          <? $prices->show(); ?>
        </div>
      </div>
	  <div class="lb_bonus lb_ajax_<?=$arResult["ID"]?>" data-item="<?=$arResult["ID"]?>"></div>
    <?php endif; ?>

    <?php if ($detailLink): ?>
      <div class="grid-list__item">
        <a class="btn-elg btn-wide buy_link"
           href="<?= htmlspecialcharsbx($detailLink) ?>"
           target="_blank"
           rel="nofollow">
          Купить
        </a>
      </div>
    <?php else: ?>
      <div class="grid-list__item">
        <?= $btnHtml; ?>
      </div>
    <?php endif; ?>

    <div class="grid-list__item">
      <?= $formsHtml; ?>
    </div>

  </div>
</div>
</div>
                    <?$isShowSalesBlock = ($templateData['SALE']['VALUE'] && $templateData['SALE']['IBLOCK_ID']); ?>
                    <?if ($isShowSalesBlock):?>
                        <?$GLOBALS['arSalesFilter'] = ['ID' => $templateData['SALE']['VALUE']]; ?>
                        <?$GLOBALS['arSalesFilter'] = TSolution\Regionality::mergeFilterWithRegionFilter($GLOBALS['arSalesFilter']); ?>
                        <?ob_start(); ?>
                            <?$APPLICATION->IncludeComponent(
                                'bitrix:news.list',
                                'sale-linked',
                                [
                                    'IBLOCK_ID' => $templateData['SALE']['IBLOCK_ID'],
                                    'CACHE_TYPE' => $arParams['CACHE_TYPE'],
                                    'CACHE_TIME' => $arParams['CACHE_TIME'],
                                    'CACHE_GROUPS' => $arParams['CACHE_GROUPS'],
                                    'CACHE_FILTER' => 'Y',
                                    'FILTER_NAME' => 'arSalesFilter',
                                    'PAGE_ELEMENT_COUNT' => '999',
                                    'PROPERTIES' => [],
                                    'SET_TITLE' => 'N',
                                    'SET_BROWSER_TITLE' => 'N',
                                    'SET_META_KEYWORDS' => 'N',
                                    'SET_META_DESCRIPTION' => 'N',
                                    'SET_LAST_MODIFIED' => 'N',
                                    'SHOW_ALL_WO_SECTION' => 'Y',
                                    'FIELD_CODE' => [
                                        'NAME',
                                    ],
                                    'COMPONENT_TEMPLATE' => 'sale_linked',

                                    'ELEMENTS_IN_ROW' => '1',
                                    'SHOW_PICTURE' => 'N',
                                    'IMAGES' => 'N',
                                    'SHOW_TITLE_IN_BLOCK' => 'N',
                                    'SHOW_PREVIEW_TEXT' => 'N',

                                    'SET_TITLE' => 'N',
                                    'SET_STATUS_404' => 'N',
                                    'INCLUDE_IBLOCK_INTO_CHAIN' => 'N',
                                    'ADD_SECTIONS_CHAIN' => 'N',

                                    'USE_REGION' => $arParams['USE_REGION'],
                                ],
                                false, ['HIDE_ICONS' => 'Y']
                            ); ?>
                        <?$html_sales_announce = trim(ob_get_clean()); ?>
                        <?if ($html_sales_announce && strpos($html_sales_announce, 'error') === false):?>
                            <div class="grid-list__item">
                                <?=$html_sales_announce; ?>
                            </div>
                        <?endif; ?>
                    <?endif; ?>

                    <?$isShowServicesBlock = (
                        $templateData['ORDER']
                        && $templateData['SERVICES']['VALUE']
                        && $templateData['SERVICES']['IBLOCK_ID']
                        && intval($arParams['COUNT_SERVICES_IN_ANNOUNCE']) > 0
                    ); ?>
                    <?if ($isShowServicesBlock):?>
                        <?$GLOBALS['arBuyServicesFilter'] = ['ID' => $templateData['SERVICES']['VALUE'], 'CATALOG_AVAILABLE' => 'Y']; ?>
                        <?$GLOBALS['arBuyServicesFilter'] = TSolution\Regionality::mergeFilterWithRegionFilter($GLOBALS['arBuyServicesFilter']); ?>
                        <?ob_start(); ?>
                            <?$APPLICATION->IncludeComponent(
                                'bitrix:catalog.section',
                                'services_buy_announce',
                                [
                                    'IBLOCK_ID' => $templateData['SERVICES']['IBLOCK_ID'],
                                    'CACHE_TYPE' => $arParams['CACHE_TYPE'],
                                    'CACHE_TIME' => $arParams['CACHE_TIME'],
                                    'CACHE_GROUPS' => $arParams['CACHE_GROUPS'],
                                    'CACHE_FILTER' => 'Y',
                                    'FILTER_NAME' => 'arBuyServicesFilter',
                                    'PAGE_ELEMENT_COUNT' => '999',
                                    'PROPERTIES' => [],
                                    'SET_TITLE' => 'N',
                                    'SET_BROWSER_TITLE' => 'N',
                                    'SET_META_KEYWORDS' => 'N',
                                    'SET_META_DESCRIPTION' => 'N',
                                    'SET_LAST_MODIFIED' => 'N',
                                    'SHOW_ALL_WO_SECTION' => 'Y',
                                    'FIELD_CODE' => [
                                        'NAME',
                                        'PREVIEW_PICTURE',
                                    ],
                                    'COMPONENT_TEMPLATE' => 'services_buy_announce',

                                    'VISIBLE_COUNT' => $arParams['COUNT_SERVICES_IN_ANNOUNCE'] ?? 2,
                                    'ELEMENTS_IN_ROW' => '1',
                                    'SHOW_PICTURE' => 'N',
                                    'IMAGES' => 'N',
                                    'SHOW_TITLE_IN_BLOCK' => 'N',
                                    'SHOW_PREVIEW_TEXT' => 'N',

                                    'ORDER_VIEW' => $arParams['ORDER_VIEW'],
                                    'PRICE_CODE' => $arParams['PRICE_CODE'],
                                    'STORES' => $arParams['STORES'],
                                    'EXPRESSION_SERVICE_ORDER_BUTTON' => $arParams['EXPRESSION_SERVICE_ORDER_BUTTON'],
                                    'EXPRESSION_SERVICE_ADD_BUTTON' => $arParams['EXPRESSION_SERVICE_ADD_BUTTON'],
                                    'USE_PRICE_COUNT' => $arParams['USE_PRICE_COUNT'],
                                    'SHOW_PRICE_COUNT' => $arParams['SHOW_PRICE_COUNT'],
                                    'COMPATIBLE_MODE' => $arParams['COMPATIBLE_MODE'],
                                    'CONVERT_CURRENCY' => $arParams['CONVERT_CURRENCY'],
                                    'CURRENCY_ID' => $arParams['CURRENCY_ID'],
                                    'PRICE_VAT_INCLUDE' => $arParams['PRICE_VAT_INCLUDE'],
                                    'BASKET_URL' => $arParams['BASKET_URL'],
                                    'SHOW_POPUP_PRICE' => $arParams['SHOW_POPUP_PRICE'],
                                    'SHOW_DISCOUNT_TIME' => $arParams['SHOW_DISCOUNT_TIME'],
                                    'SHOW_DISCOUNT_PERCENT' => $arParams['SHOW_DISCOUNT_PERCENT'],
                                    'SHOW_MEASURE' => $arParams['SHOW_MEASURE'],
                                    'DISCOUNT_PRICE' => $arParams['DISCOUNT_PRICE'],
                                    'SHOW_OLD_PRICE' => $arParams['SHOW_OLD_PRICE'],
                                    'USE_REGION' => $arParams['USE_REGION'],
                                ],
                                false, ['HIDE_ICONS' => 'Y']
                            ); ?>
                        <?$html_buy_services_announce = trim(ob_get_clean()); ?>

                        <?if($html_buy_services_announce && strpos($html_buy_services_announce, 'error') === false):?>
                            <div class="grid-list__item">
                                <div class="services-buy-wrapper in_announce" data-product_id="<?= $templateData['CURRENT_OFFER_ID'] ?? $arResult['ID']; ?>">
                                    <?=$html_buy_services_announce; ?>
                                </div>
                            </div>
                        <?endif; ?>
                    <?endif; ?>

                    <?$isShowBrandBlock = ($arResult['BRAND_ITEM'] && $arResult['BRAND_ITEM']['IMAGE']); ?>
                    <?if ($isShowBrandBlock):?>
                        <div class="grid-list__item">
                            <div class="brand-detail flexbox line-block--gap line-block--gap-12">
                                <div class="brand-detail-info">
                                    <meta itemprop="name" content="<?=$arResult['BRAND_ITEM']['NAME']; ?>" />
                                    <div class="brand-detail-info__image rounded-x">
                                        <a href="<?=$arResult['BRAND_ITEM']['DETAIL_PAGE_URL']; ?>">
                                            <img src="<?=$arResult['BRAND_ITEM']['IMAGE']['src']; ?>" alt="<?=$arResult['BRAND_ITEM']['NAME']; ?>" title="<?=$arResult['BRAND_ITEM']['NAME']; ?>">
                                        </a>
                                    </div>
                                </div>

                                <div class="brand-detail-info__preview line-block line-block--gap line-block--gap-8 flexbox--wrap font_14">
                                    <div class="line-block__item">
                                        <a class="chip chip--transparent bordered" href="<?=$arResult['BRAND_ITEM']['DETAIL_PAGE_URL']; ?>" target="_blank">
                                            <span class="chip__label"><?=GetMessage('ITEMS_BY_BRAND', ['#BRAND#' => $arResult['BRAND_ITEM']['NAME']]); ?></span>
                                        </a>
                                    </div>
                                    <?if ($arResult['SECTION']):?>
                                        <div class="line-block__item">
                                            <a class="chip chip--transparent bordered" href="<?= $arResult['BRAND_ITEM']['CATALOG_PAGE_URL']; ?>" target="_blank">
                                                <span class="chip__label"><?=GetMessage('ITEMS_BY_SECTION'); ?></span>
                                            </a>
                                        </div>
                                    <?endif; ?>
                                </div>
                            </div>
                        </div>
                    <?endif; ?>

                    <?$isShowTizersBlock = ($templateData['TIZERS']['VALUE'] && $templateData['TIZERS']['IBLOCK_ID']); ?>
                    <?if ($isShowTizersBlock):?>
                        <?$GLOBALS['arTizersFilter'] = ['ID' => $templateData['TIZERS']['VALUE']]; ?>
                        <?ob_start(); ?>
                            <?$APPLICATION->IncludeComponent(
                                'bitrix:news.list',
                                'tizers-linked',
                                [
                                    'IBLOCK_ID' => $templateData['TIZERS']['IBLOCK_ID'],
                                    'CACHE_TYPE' => $arParams['CACHE_TYPE'],
                                    'CACHE_TIME' => $arParams['CACHE_TIME'],
                                    'CACHE_GROUPS' => $arParams['CACHE_GROUPS'],
                                    'CACHE_FILTER' => 'Y',
                                    'FILTER_NAME' => 'arTizersFilter',
                                    'SORT_BY1' => 'SORT',
                                    'SORT_ORDER1' => 'ASC',
                                    'SORT_BY2' => 'ID',
                                    'SORT_ORDER2' => 'ASC',
                                    'NEWS_COUNT' => '999',
                                    'PROPERTY_CODE' => [
                                        'NOT_INLINE_SVG',
                                        'TIZER_ICON',
                                    ],
                                    'SET_TITLE' => 'N',
                                    'SET_BROWSER_TITLE' => 'N',
                                    'SET_META_KEYWORDS' => 'N',
                                    'SET_META_DESCRIPTION' => 'N',
                                    'SET_LAST_MODIFIED' => 'N',
                                    'SHOW_ALL_WO_SECTION' => 'Y',
                                    'FIELD_CODE' => [
                                        0 => 'NAME',
                                        1 => 'PREVIEW_TEXT',
                                        2 => 'PREVIEW_PICTURE',
                                        3 => 'DATE_ACTIVE_FROM',
                                        4 => '',
                                    ],
                                    'COMPONENT_TEMPLATE' => 'sale_linked',

                                    'ELEMENTS_IN_ROW' => '1',
                                    'SHOW_PICTURE' => 'N',
                                    'IMAGES' => 'N',
                                    'SHOW_TITLE_IN_BLOCK' => 'N',
                                    'SHOW_PREVIEW_TEXT' => 'N',

                                    'SET_TITLE' => 'N',
                                    'SET_STATUS_404' => 'N',
                                    'INCLUDE_IBLOCK_INTO_CHAIN' => 'N',
                                    'ADD_SECTIONS_CHAIN' => 'N',

                                    'USE_REGION' => $arParams['USE_REGION'],
                                ],
                                false, ['HIDE_ICONS' => 'Y']
                            ); ?>
                        <?$html_tizers_announce = trim(ob_get_clean()); ?>
                        <?if ($html_tizers_announce && strpos($html_tizers_announce, 'error') === false):?>
                            <div class="grid-list__item">
                                <?=$html_tizers_announce; ?>
                            </div>
                        <?endif; ?>
                    <?endif; ?>

                    <?if(strlen($arResult['INCLUDE_PRICE'])):?>
                        <div class="price_txt font_13 secondary-color">
                            <?=$arResult['INCLUDE_PRICE']; ?>
                        </div>
                    <?endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>





<?// props content?>
<?if ($templateData['SHOW_CHARACTERISTICS']):?>
    <?$this->SetViewTarget('PRODUCT_PROPS_INFO');?>
    <?TSolution\Functions::showBlockHtml([
        'FILE' => '/chars.php',
        'PARENT_COMPONENT' => $this->getComponent(),
        'PARAMS' => [
            'GRUPPER_PROPS' => $arParams['GRUPPER_PROPS'],
            'IBLOCK_ID' => $arResult['IBLOCK_ID'],
            'IBLOCK_TYPE' => $arResult['IBLOCK_TYPE'],
            'CHARACTERISTICS' => $arResult['CHARACTERISTICS'],
            'SKU_IBLOCK_ID' => $arParams['SKU_IBLOCK_ID'],
            'OFFER_PROP' => $arResult['OFFER_PROP'],
            'SHOW_HINTS' => $arParams['SHOW_HINTS'],
            'PROPERTIES_DISPLAY_TYPE' => $arParams['PROPERTIES_DISPLAY_TYPE'],
            'USE_SCHEMA' => 'N',
        ],
    ]);?>
    <?$this->EndViewTarget();?>
<?endif;?>

<?php
if ($bUseSchema) {
    $schema = new TSolution\Scheme\Product(
        result: $arResult,
        prices: $prices,
        props: [
            'SKU' => $article,
            'STATUS' => $statusCode,
            'PRICE_VALID_UNTIL' => $discountDateTo,
        ],
        options: [
            'SHOW_BRAND' => (bool)$isShowBrandBlock
        ],
    );

    $templateData['SCHEMA_ORG'] = $schema->getArraySchema();
}
