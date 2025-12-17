<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
global $APPLICATION;

$arExtensions = ['fancybox', 'detail'];

if ($arParams["USE_SHARE"] || $arParams["USE_RSS"]) {
  $arExtensions[] = 'item_action';
  $arExtensions[] = 'share';
}

if ($templateData['BANNER_CHAR_PHOTOS']) {
  $arExtensions[] = 'banner_char';
}

if ($templateData['SHOW_CHARACTERISTICS']){
	$arExtensions[] = 'hint';
}

// can order?
$bOrderViewBasket = $templateData["ORDER"];

// use tabs?
$bUseDetailTabs = $arParams['USE_DETAIL_TABS'] === 'Y';
if (TSolution::getFrontParametrValue('SHOW_PROJECTS_MAP_DETAIL') == 'N') {
  unset($templateData['MAP']);
}

// blocks order
if (
  !$bUseDetailTabs &&
  array_key_exists('DETAIL_BLOCKS_ALL_ORDER', $arParams) &&
  $arParams["DETAIL_BLOCKS_ALL_ORDER"]
) {
  $arBlockOrder = explode(",", $arParams["DETAIL_BLOCKS_ALL_ORDER"]);
} else {
  $arBlockOrder = explode(",", $arParams["DETAIL_BLOCKS_ORDER"]);
  $arTabOrder = explode(",", $arParams["DETAIL_BLOCKS_TAB_ORDER"]);
}

\TSolution\Banner\Transparency::setHeaderClasses($templateData);
\TSolution\Functions::replaceDetailParams($arParams, ['PROPERTY_CODE' => 'PROPERTY_CODE']);
\TSolution\Extensions::init($arExtensions);
?>
<div class="services-detail__bottom-info flexbox gap gap--48">
	<?$arEpilogBlocks = new TSolution\Template\Epilog\Blocks([
		'TABS' => $arTabOrder ?? [],
		'ORDERED' => $arBlockOrder,
		'AFTER_ORDERED' => ['tags'],
	], __DIR__);?>
	
	<?foreach ($arEpilogBlocks->ordered as $path) {
		include_once $path;
	}?>
	
	<?foreach ($arEpilogBlocks->afterOrdered as $path) {
		include_once $path;
	}?>
</div>