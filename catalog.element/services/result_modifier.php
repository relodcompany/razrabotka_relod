<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

TSolution::getFieldImageData($arResult, array('DETAIL_PICTURE'));

/* big gallery */
if($arParams['SHOW_BIG_GALLERY'] === 'Y'){
	$arResult['BIG_GALLERY'] = array();
	
	if(
		$arParams['BIG_GALLERY_PROP_CODE'] && 
		isset($arResult['PROPERTIES'][$arParams['BIG_GALLERY_PROP_CODE']]) && 
		$arResult['PROPERTIES'][$arParams['BIG_GALLERY_PROP_CODE']]['VALUE']
	){
		foreach($arResult['PROPERTIES'][$arParams['BIG_GALLERY_PROP_CODE']]['VALUE'] as $img){
			$arPhoto = CFile::GetFileArray($img);

			$alt = $arPhoto['DESCRIPTION'] ?: $arPhoto['ALT'] ?: $arResult['NAME'];
			$title = $arPhoto['DESCRIPTION'] ?: $arPhoto['TITLE'] ?: $arResult['NAME'];;

			$arResult['BIG_GALLERY'][] = array(
				'DETAIL' => $arPhoto,
				'PREVIEW' => CFile::ResizeImageGet($img, array('width' => 1500, 'height' => 1500), BX_RESIZE_IMAGE_PROPORTIONAL_ALT, true),
				'THUMB' => CFile::ResizeImageGet($img , array('width' => 60, 'height' => 60), BX_RESIZE_IMAGE_EXACT, true),
				'TITLE' => $title,
				'ALT' => $alt,
			);
		}
	}
}

// top gallery
if (
	isset($arResult['PROPERTIES']['PHOTOPOS']) && 
	$arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] == 'TOP_SIDE'
) {
	$arResult['TOP_GALLERY'] = [];
	if ($arResult['FIELDS']['DETAIL_PICTURE']) {
		$atrTitle = (strlen($arResult['DETAIL_PICTURE']['DESCRIPTION']) ? $arResult['DETAIL_PICTURE']['DESCRIPTION'] : (strlen($arResult['DETAIL_PICTURE']['TITLE']) ? $arResult['DETAIL_PICTURE']['TITLE'] : $arResult['NAME']));
		$atrAlt = (strlen($arResult['DETAIL_PICTURE']['DESCRIPTION']) ? $arResult['DETAIL_PICTURE']['DESCRIPTION'] : (strlen($arResult['DETAIL_PICTURE']['ALT']) ? $arResult['DETAIL_PICTURE']['ALT'] : $arResult['NAME']));

		$arResult['TOP_GALLERY'][] = array(
			'DETAIL' => $arResult['DETAIL_PICTURE'],
			'PREVIEW' => CFile::ResizeImageGet($arResult['DETAIL_PICTURE']['ID'], array('width' => 1500, 'height' => 1500), BX_RESIZE_IMAGE_PROPORTIONAL_ALT, true),
			'THUMB' => CFile::ResizeImageGet($arResult['DETAIL_PICTURE']['ID'] , array('width' => 60, 'height' => 60), BX_RESIZE_IMAGE_EXACT, true),
			'TITLE' => $atrTitle,
			'ALT' => $atrAlt,
		);
	}
	if(
		$arParams['TOP_GALLERY_PROP_CODE'] && 
		isset($arResult['PROPERTIES'][$arParams['TOP_GALLERY_PROP_CODE']]) && 
		$arResult['PROPERTIES'][$arParams['TOP_GALLERY_PROP_CODE']]['VALUE']
	){
		foreach($arResult['PROPERTIES'][$arParams['TOP_GALLERY_PROP_CODE']]['VALUE'] as $img){
			$arPhoto = CFile::GetFileArray($img);
	
			$alt = $arPhoto['DESCRIPTION'] ?: $arPhoto['ALT'] ?: $arResult['NAME'];
			$title = $arPhoto['DESCRIPTION'] ?: $arPhoto['TITLE'] ?: $arResult['NAME'];;
	
			$arResult['TOP_GALLERY'][] = array(
				'DETAIL' => $arPhoto,
				'PREVIEW' => CFile::ResizeImageGet($img, array('width' => 1500, 'height' => 1500), BX_RESIZE_IMAGE_PROPORTIONAL_ALT, true),
				'THUMB' => CFile::ResizeImageGet($img , array('width' => 60, 'height' => 60), BX_RESIZE_IMAGE_EXACT, true),
				'TITLE' => $title,
				'ALT' => $alt,
			);
		}
	}
}

// sef folder to include files
$sefFolder = rtrim($arParams["SEF_FOLDER"] ?? dirname($_SERVER['REAL_FILE_PATH']), '/');

// dops tab
if($arParams['SHOW_DOPS'] === 'Y'){
	$this->SetViewTarget('PRODUCT_DOPS_INFO');
	$APPLICATION->IncludeFile($sefFolder."/index_dops.php", array(), array("MODE" => "html", "NAME" => GetMessage('T_DOPS')));
	$this->EndViewTarget();
}

$arResult['GALLERY_SIZE'] = $arParams['GALLERY_SIZE'];
$arResult['CHARACTERISTICS'] = $arResult['VIDEO'] = $arResult['VIDEO_IFRAME'] = [];

/* docs property code */
$docsProp = $arParams['DETAIL_DOCS_PROP'] ? $arParams['DETAIL_DOCS_PROP'] : 'DOCUMENTS';

if(
	array_key_exists($docsProp, $arResult["DISPLAY_PROPERTIES"]) &&
	is_array($arResult["DISPLAY_PROPERTIES"][$docsProp]) &&
	$arResult["DISPLAY_PROPERTIES"][$docsProp]["VALUE"]
){
	foreach($arResult['DISPLAY_PROPERTIES'][$docsProp]['VALUE'] as $key => $value){
		if(!intval($value)){
			unset($arResult['DISPLAY_PROPERTIES'][$docsProp]['VALUE'][$key]);
		}
	}

	if($arResult['DISPLAY_PROPERTIES'][$docsProp]['VALUE']){
		$arResult['DOCUMENTS'] = array_values($arResult['DISPLAY_PROPERTIES'][$docsProp]['VALUE']);
	}
}

if($arResult['DISPLAY_PROPERTIES']){
	$arResult['CHARACTERISTICS'] = TSolution::PrepareItemProps($arResult['DISPLAY_PROPERTIES']);

	foreach($arResult['DISPLAY_PROPERTIES'] as $PCODE => $arProp){
		if(
			$arProp["VALUE"] ||
			strlen($arProp["VALUE"])
		){
			if($arProp['USER_TYPE'] === 'video') {
				if(count($arProp['PROPERTY_VALUE_ID']) >= 1) {
					foreach($arProp['VALUE'] as $val){
						if($val['path']){
							$arResult['VIDEO'][] = $val;
						}
					}
				}
				elseif($arProp['VALUE']['path']){
					$arResult['VIDEO'][] = $arProp['VALUE'];
				}
			}
			elseif($arProp['CODE'] === 'VIDEO_IFRAME'){
				$arProp['VIDEO_FRAMES'] = TSolution\Video\Iframe::getVideoBlock($arProp['~VALUE']);
				if ($arProp['VIDEO_FRAMES']) {
					$arResult['VIDEO'] = array_merge($arResult['VIDEO'], $arProp['VIDEO_FRAMES']);
				}
			}
			elseif($arProp['CODE'] === 'POPUP_VIDEO'){
				$arResult['POPUP_VIDEO'] = $arProp["VALUE"];
			}
		}
	}
}

// get display properties
$arDetailPageShowProps = \Bitrix\Iblock\Model\PropertyFeature::getDetailPageShowPropertyCodes($arParams['IBLOCK_ID'], array('CODE' => 'Y'));
$arCrossLinkedProps = [
	'LINK_FAQ',
	'LINK_TIZERS',
	'LINK_REVIEWS',
	'LINK_SALE',
	'LINK_ARTICLES',
	'LINK_NEWS',
	'LINK_PROJECTS',
	'LINK_BRANDS',
	'LINK_SERVICES',
	'LINK_GOODS',
	'LINK_STAFF',
];

foreach ($arCrossLinkedProps as $prop) {
	if (
		(in_array($prop, (array)$arDetailPageShowProps) || in_array($prop, $arParams)) && 
		!isset($arResult['DISPLAY_PROPERTIES'][$prop])
	) {
		$arResult['DISPLAY_PROPERTIES'][$prop] = true;
	}
}

// services that allow to buy
$arAllowBuyItems = [];
if ('Y' === ($arResult['PROPERTIES']['ALLOW_BUY']['VALUE_XML_ID'] ?? 'N')) {
	$arAllowBuyItems[$arResult['ID']] =& $arResult;
}

/* fill catalog min price for services that allow to buy */
\Aspro\Premier\Product\Service::fillPrices($arAllowBuyItems, $arParams);

$arResult['CATEGORY_ITEM'] = '';

if ($arResult['SECTION'] && $arResult['SECTION']['PATH']) {
	$arTmpPath = array();
	foreach ($arResult['SECTION']['PATH'] as $arSection) {
		$arTmpPath[] = $arSection['NAME'];
	}
	if ($arTmpPath) {
		$arResult['CATEGORY_ITEM'] = implode('/', $arTmpPath);
	}
} elseif($arResult['IBLOCK_SECTION_ID']) {
	$arSectionsIDs = [];
	$dbRes = CIBlockElement::GetElementGroups($arResult['ID'], true, array('ID'));
	while ($arSection = $dbRes->Fetch()) {
		$arSectionsIDs[$arSection['ID']] = $arSection['ID'];
	}

	if($arSectionsIDs){
		$arSections = TSolution\Cache::CIBLockSection_GetList(
			array(
				'SORT' => 'ASC',
				'NAME' => 'ASC',
				'CACHE' => array(
					'TAG' => TSolution\Cache::GetIBlockCacheTag($arParams['IBLOCK_ID']),
					'GROUP' => array('ID'),
					'MULTI' => 'N',
					'RESULT' => array('NAME')
				)
			),
			array('ID' => $arSectionsIDs),
			false,
			array(
				'ID',
				'NAME'
			)
		);
		if ($arSections) {
			$arResult['CATEGORY_ITEM'] = implode('/', array_values($arSections));
		}
	}
}
