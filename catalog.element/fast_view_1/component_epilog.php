<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arExtensions = ['fancybox', 'alphanumeric', 'stores_amount', 'chip'];

if($templateData["USE_OFFERS_SELECT"]){
	$arExtensions[] = 'select_offer';
	$arExtensions[] = 'select_offer_load';
}

if($templateData['HAS_CHARACTERISTICS']){
	$arExtensions[] = 'hint';
}

TSolution\Extensions::init($arExtensions);


$arEpilogBlocks = new TSolution\Template\Epilog\Blocks([
    'BEFORE_ORDERED' => ['product_analog']
]);

foreach ($arEpilogBlocks->beforeOrdered as $path) {
    include $path;
}
?>

<script type="text/javascript">
var viewedCounter = {
	path: '/bitrix/components/bitrix/catalog.element/ajax.php',
	params: {
		AJAX: 'Y',
		SITE_ID: '<?=SITE_ID?>',
		PRODUCT_ID: '<?=$arResult['ID']?>',
		PARENT_ID: '<?=$arResult['ID']?>',
	}
};
BX.ready(
	BX.defer(function(){
		BX.ajax.post(
			viewedCounter.path,
			viewedCounter.params
		);
	})
);

viewItemCounter('<?=$arResult['ID']?>', '<?=current($arParams['PRICE_CODE'])?>');
</script>
