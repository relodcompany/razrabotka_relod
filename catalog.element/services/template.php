<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$this->setFrameMode(true);

use Bitrix\Main\Localization\Loc,
	TSolution\Product\Service;

Loc::loadMessages(__FILE__);

$bOrderViewBasket = $arParams['ORDER_VIEW'];
$bCanBuy = Service::getCanBuy($arResult);
$dataItem = $bOrderViewBasket ? TSolution::getDataItem($arResult) : false;
$bOrderButton = $arResult['PROPERTIES']['FORM_ORDER']['VALUE_XML_ID'] == 'YES';
$bAskButton = $arResult['PROPERTIES']['FORM_QUESTION']['VALUE_XML_ID'] == 'YES';
$bTopImg = $bTopSideImg = false;
$basketURL = (strlen(trim($arTheme['ORDER_VIEW']['DEPENDENT_PARAMS']['URL_BASKET_SECTION']['VALUE'])) ? trim($arTheme['ORDER_VIEW']['DEPENDENT_PARAMS']['URL_BASKET_SECTION']['VALUE']) : '');

$bTopDate = $arResult['DISPLAY_ACTIVE_FROM'] || strlen($arResult['DISPLAY_PROPERTIES']['DATA']['VALUE']);
$topDate = strlen($arResult['DISPLAY_PROPERTIES']['DATA']['VALUE']) ? $arResult['DISPLAY_PROPERTIES']['DATA']['VALUE'] : $arResult['DISPLAY_ACTIVE_FROM'];

// preview image
$bIcon = false;
$nImageID = is_array($arResult['PREVIEW_PICTURE']) ? $arResult['PREVIEW_PICTURE']['ID'] : $arResult['PREVIEW_PICTURE'];
if(!$nImageID){
	if($nImageID = $arResult['DISPLAY_PROPERTIES']['ICON']['VALUE']){
		$bIcon = true;
	}
}
$imageSrc = ($nImageID ? CFile::getPath($nImageID) : SITE_TEMPLATE_PATH.'/images/svg/noimage_content.svg');

/*set array props for component_epilog*/
$templateData = array(
	'ORDER' => $bOrderViewBasket,
	'ORDER_BTN' => ($bOrderButton || $bCanBuy  || $bAskButton),
	'PREVIEW_PICTURE' => $arResult['PREVIEW_PICTURE'],
	'TAGS' => $arResult['TAGS'],
	'SECTIONS' => $arResult['PROPERTIES']['SECTION']['VALUE'],
	'H3_GOODS' => $arResult['PROPERTIES']['H3_GOODS']['VALUE'],
	'FILTER_URL' => $arResult['PROPERTIES']['FILTER_URL']['VALUE'],
	'MAP' => $arResult['PROPERTIES']['MAP']['VALUE'],
	'MAP_DOP_INFO' => $arResult['PROPERTIES']['INFO']['~VALUE'],
	'FAQ' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_FAQ')),
	'TIZERS' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_TIZERS')),
	'REVIEWS' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_REVIEWS')),
	'BRANDS' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_BRANDS')),
	'STAFF' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_STAFF')),
	'SALE' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_SALE')),
	'PROJECTS' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_PROJECTS')),
	'ARTICLES' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_ARTICLES')),
	'NEWS' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_ARTICLES')),
	'SERVICES' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_SERVICES')),
	'GOODS' => TSolution\Functions::getCrossLinkedItems($arResult, array('LINK_GOODS', 'LINK_GOODS_FILTER')),
);
?>
<?if($arResult['CATEGORY_ITEM']):?>
	<meta itemprop="category" content="<?=$arResult['CATEGORY_ITEM'];?>" />
<?endif;?>
<?if($arResult['DETAIL_PICTURE']):?>
	<meta itemprop="image" content="<?=$arResult['DETAIL_PICTURE']['SRC'];?>" />
<?endif;?>
<meta itemprop="name" content="<?=$arResult['NAME'];?>" />
<link itemprop="url" href="<?=$arResult['DETAIL_PAGE_URL'];?>" />

<?// top banner?>
<?$templateData['BANNER_TOP_ON_HEAD'] = false;?>
<?if($arResult['DETAIL_PICTURE']):?>
	<?
	// single detail image
	$templateData['BANNER_TOP_ON_HEAD'] = isset($arResult['PROPERTIES']['PHOTOPOS']) && $arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] == 'TOP_ON_HEAD';

	$atrTitle = (strlen($arResult['DETAIL_PICTURE']['DESCRIPTION']) ? $arResult['DETAIL_PICTURE']['DESCRIPTION'] : (strlen($arResult['DETAIL_PICTURE']['TITLE']) ? $arResult['DETAIL_PICTURE']['TITLE'] : $arResult['NAME']));
	$atrAlt = (strlen($arResult['DETAIL_PICTURE']['DESCRIPTION']) ? $arResult['DETAIL_PICTURE']['DESCRIPTION'] : (strlen($arResult['DETAIL_PICTURE']['ALT']) ? $arResult['DETAIL_PICTURE']['ALT'] : $arResult['NAME']));

	$bTopImg = (strpos($arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'], 'TOP') !== false);
	$templateData['IMG_TOP_SIDE'] = isset($arResult['PROPERTIES']['PHOTOPOS']) && $arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] == 'TOP_SIDE';
	?>

	<?if (!$templateData['IMG_TOP_SIDE']):?>
		<?if ($bTopImg):?>
			<?if ($templateData['BANNER_TOP_ON_HEAD']):?>
				<?$this->SetViewTarget('side-over-title');?>
			<?else:?>
				<?$this->SetViewTarget('top_section_filter_content');?>
			<?endif;?>
		<?endif;?>
		
		<?TSolution\Functions::showBlockHtml([
			'FILE' => '/images/detail_single.php',
			'PARAMS' => [
				'TYPE' => $arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'],
				'URL' => $arResult['DETAIL_PICTURE']['SRC'],
				'ALT' => $atrAlt,
				'TITLE' => $atrTitle,
				'TOP_IMG' => $bTopImg
			],
		])?>

		<?if ($bTopImg):?>
			<?$this->EndViewTarget();?>
		<?endif;?>
	<?endif;?>
<?endif;?>

<?ob_start();?>
	<div class="btn btn-default btn-wide btn-lg <?=(($bOrderButton || $bCanBuy ) && $bAskButton) ? 'btn-transparent-bg' : '';?> animate-load" data-event="jqm" data-param-id="<?=TSolution::getFormID("aspro_".TSolution::solutionName."_question");?>" data-autoload-need_product="<?=TSolution::formatJsName($arResult['NAME'])?>" data-name="question">
		<span><?=htmlspecialcharsbx(TSolution::GetFrontParametrValue('EXPRESSION_FOR_ASK_QUESTION'))?></span>
	</div>
<?$askButtonHtml = ob_get_clean()?>

<?ob_start();?>
	<button type="button" class="btn btn-default btn-wide btn-lg animate-load" 
		data-event="jqm" 
		data-param-id="<?=TSolution::getFormID($arParams['FORM_ID_ORDER_SERVISE']);?>" data-autoload-need_product="<?=TSolution::formatJsName($arResult['NAME'])?>" 
		data-autoload-service="<?=\TSolution::formatJsName($arResult["NAME"]);?>" 
		data-autoload-sale="<?=\TSolution::formatJsName($arResult["NAME"]);?>" 
		data-name="order_project"
	>
		<span><?=(strlen($arParams['S_ORDER_SERVISE']) ? $arParams['S_ORDER_SERVISE'] : Loc::getMessage('S_ORDER_SERVISE'))?></span>
	</button>
<?$orderButtonHtml = ob_get_clean()?>
<?
// discount value
$bSaleNumber = strlen($arResult['DISPLAY_PROPERTIES']['SALE_NUMBER']['VALUE']);
// dicount counter
$bDiscountCounter = ($arResult['ACTIVE_TO'] && in_array('ACTIVE_TO', $arParams['FIELD_CODE']));
?>

<?if ($bSaleNumber || $bDiscountCounter):?>
	<div class="top-meta">
		<div class="line-block line-block--20 line-block--16-vertical line-block--flex-wrap">
			<?if ($bSaleNumber || $bDiscountCounter):?>
				<div class="line-block__item">
					<div class="line-block line-block--8 line-block--16-vertical line-block--flex-wrap">
						<?if ($bSaleNumber):?>
							<div class="line-block__item">
								<div class="top-meta__discount sale-list__item-sticker-value sticker__item sticker__item--sale rounded-x">
									<?=$arResult['DISPLAY_PROPERTIES']['SALE_NUMBER']['VALUE'];?>
								</div>
							</div>
						<?endif;?>
						<?if ($bDiscountCounter):?>
							<?TSolution\Functions::showDiscountCounter([
								'WRAPPER' => true,
								'WRAPPER_CLASS' => 'line-block__item',
								'TYPE' => 'block',
								'ITEM' => $arResult,
								'ICONS' => true
							]);?>
						<?endif;?>
					</div>
				</div>
			<?endif;?>
		</div>
	</div>
<?endif;?>

<?$bTopInfo = false;?>
<?if (
	$arResult['DISPLAY_PROPERTIES']['TASK_PROJECT']['~VALUE']['TEXT'] && 
	($bOrderButton || $bCanBuy  || $bAskButton || $arResult["CHARACTERISTICS"])
):?>
	<?$bTopInfo = true;?>
	<?$templateData['ORDER_BTN'] = false?>
<?endif;?>

<?if ($templateData['IMG_TOP_SIDE']):?>
	<?$this->SetViewTarget('top_section_filter_content');?>
		<div class="maxwidth-theme">
			<div class="outer-rounded-x bordered top-info">
				<div class="flexbox flexbox--direction-row ">
					<div class="top-info__picture flex-1">
						<div class="owl-carousel owl-carousel--color-dots owl-carousel--nav-hover-visible owl-bg-nav owl-carousel--light owl-carousel--button-wide" data-plugin-options='{"items": "1", "autoplay" : false, "autoplayTimeout" : "3000", "smartSpeed":1000, "dots": true, "dotsContainer": false, "nav": true, "loop": false, "index": true, "margin": 0}'>
							<?foreach($arResult['TOP_GALLERY'] as $arPhoto):?>
								<div class="top-info__picture-item">
									<a href="<?=$arPhoto['DETAIL']['SRC']?>" class="top-info__link fancy" data-fancybox="big-gallery" target="_blank" title="<?=$arPhoto['TITLE']?>">
										<span class="top-info__img" style="background-image: url(<?=$arPhoto['PREVIEW']['src']?>)"></span>
									</a>
								</div>
							<?endforeach;?>
						</div>
					</div>

					<?if ($bTopInfo):?>
						<div class="top-info__text flex-1">
							<div class="top-info__text-inner">
								<?if ($arResult['DISPLAY_PROPERTIES']['TASK_PROJECT']['~VALUE']['TEXT']):?>
									<div class="top-info__task">
										<?if ($bTopDate):?>
											<div class="font_13 color_999">
												<?=$topDate?>
											</div>
										<?endif;?>
										<div class="font_18 color_222 font_large top-info__task-value">
											<?=$arResult['DISPLAY_PROPERTIES']['TASK_PROJECT']['~VALUE']['TEXT']?>
										</div>
									</div>
								<?endif;?>
								
								<?if ($arResult['CHARACTERISTICS'] || ($bOrderButton || $bCanBuy  || $bAskButton)):?>
									<div class="line-block line-block--align-normal line-block--40 top-info__bottom">
										<?if ($arResult['CHARACTERISTICS']):?>
											<div class="line-block__item flex-1">
												<div class="properties list">
													<?
													$cntChars = count($arResult['CHARACTERISTICS']);
													$j = 0;
													?>
													<?foreach($arResult['CHARACTERISTICS'] as $code => $arProp):?>
														<?if($j < $arParams['VISIBLE_PROP_COUNT']):?>
															<div class="properties__item">
																<div class="properties__title font_13 color_999">
																	<?=$arProp['NAME']?>
																	<?if($arProp["HINT"] && $arParams["SHOW_HINTS"]=="Y"):?>
																		<div class="hint hint--down">
																			<span class="hint__icon rounded bg-theme-hover border-theme-hover bordered"><i>?</i></span>
																			<div class="tooltip"><?=$arProp["HINT"]?></div>
																		</div>
																	<?endif;?>
																</div>
																<div class="properties__value color_222 font_15 font_short">
																	<?if(is_array($arProp["DISPLAY_VALUE"]) && count($arProp["DISPLAY_VALUE"]) > 1):?>
																		<?=implode(', ', $arProp["DISPLAY_VALUE"]);?>
																	<?elseif($code == 'SITE'):?>
																		<?$valProp = preg_replace('#(http|https)(://)|((\?.*)|(\/\?.*))#', '', $arProp['VALUE']);?>
																		<!--noindex-->
																		<a class="dark_link" href="<?=(strpos($arProp['VALUE'], 'http') === false ? 'http://' : '').$arProp['VALUE'];?>" rel="nofollow" target="_blank">
																			<?=$valProp?>
																		</a>
																		<!--/noindex-->
																	<?else:?>
																		<?=$arProp["DISPLAY_VALUE"];?>
																	<?endif;?>
																</div>
															</div>
															<?$j++;?>
														<?endif;?>
													<?endforeach;?>
												</div>

												<?if($cntChars > $arParams['VISIBLE_PROP_COUNT']):?>
													<div class="more-char-link">
														<span class="choise dotted colored pointer" data-block="char"><?=Loc::getMessage('MORE_CHAR_BOTTOM');?></span>
													</div>
												<?else:?>
													<?$arResult['CHARACTERISTICS'] = [];?>
												<?endif;?>
											</div>
										<?endif;?>

										<?if ($bOrderButton || $bAskButton):?>
											<div class="line-block__item flex-1 buttons-block">
												<?if ($bOrderButton):?>
													<div>
														<?=$orderButtonHtml;?>
													</div>
												<?endif;?>

												<?if ($bAskButton):?>
													<div>
														<?=$askButtonHtml;?>
													</div>
												<?endif;?>
											</div>
										<?endif;?>
									</div>
								<?endif;?>
							</div>
						</div>
					<?endif;?>
				</div>
			</div>
		</div>
	<?$this->EndViewTarget();?>
<?elseif($bTopInfo):?>
	<?$this->SetViewTarget('top_detail_content');?>
		<?$class = 'bordered grey-bg';?>
		<div class="detail-info-wrapper <?=($templateData['SECTION_BNR_CONTENT'] || $bTopImg ? 'detail-info-wrapper--with-img' : '');?>">
			<?if (
				$arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] != 'TOP' &&
				$arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] != 'TOP_ON_HEAD' &&
				!$templateData['SECTION_BNR_CONTENT']
			):?>
				<div class="maxwidth-theme">
				<?$class .= ' outer-rounded-x'?>
			<?endif;?>

		<div class="<?=$class?>">
			<?if(
				$templateData['SECTION_BNR_CONTENT'] || 
				(
					$arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] != 'TOP_CONTENT' && 
					$bTopImg
				)
			):?>
				<div class="maxwidth-theme">
			<?endif;?>

			<div class="detail-info">
				<div class="line-block line-block--align-normal line-block--40">
					<?if ($arResult['DISPLAY_PROPERTIES']['TASK_PROJECT']['~VALUE']['TEXT'] || $arResult["CHARACTERISTICS"] ):?>
						<div class="line-block__item detail-info__inner flex-grow-1">
							<?if ($bTopDate):?>
								<div class="detail-info__date font_13 color_999">
									<?=$topDate?>
								</div>
							<?endif;?>

							<?if ($arResult['DISPLAY_PROPERTIES']['TASK_PROJECT']['~VALUE']['TEXT']):?>
								<div class="detail-info__text font_18 color_222 font_large">
									<?=$arResult['DISPLAY_PROPERTIES']['TASK_PROJECT']['~VALUE']['TEXT']?>
								</div>
							<?endif;?>

							<?if($arResult['CHARACTERISTICS']):?>
								<div class="detail-info__chars">
									<div class="properties list detail-info__chars-inner">
										<div class="line-block line-block--align-normal">
											<?
											$cntChars = count($arResult['CHARACTERISTICS']);
											$j = 0;
											?>
											<?foreach($arResult['CHARACTERISTICS'] as $code => $arProp):?>
												<?if($j < $arParams['VISIBLE_PROP_COUNT']):?>
													<div class="line-block__item col-lg-3 col-md-4 col-sm-6 detail-info__chars-item">
														<div class="properties__title font_13 color_999">
															<?=$arProp['NAME']?>
															<?if($arProp["HINT"] && $arParams["SHOW_HINTS"]=="Y"):?>
																<div class="hint hint--down">
																	<span class="hint__icon rounded bg-theme-hover border-theme-hover bordered"><i>?</i></span>
																	<div class="tooltip"><?=$arProp["HINT"]?></div>
																</div>
															<?endif;?>
														</div>
														<div class="properties__value color_222 font_15 font_short">
															<?if(is_array($arProp["DISPLAY_VALUE"]) && count($arProp["DISPLAY_VALUE"]) > 1):?>
																<?=implode(', ', $arProp["DISPLAY_VALUE"]);?>
															<?elseif($code == 'SITE'):?>
																<?$valProp = preg_replace('#(http|https)(://)|((\?.*)|(\/\?.*))#', '', $arProp['VALUE']);?>
																<!--noindex-->
																<a class="dark_link" href="<?=(strpos($arProp['VALUE'], 'http') === false ? 'http://' : '').$arProp['VALUE'];?>" rel="nofollow" target="_blank">
																	<?=$valProp?>
																</a>
																<!--/noindex-->
															<?else:?>
																<?=$arProp["DISPLAY_VALUE"];?>
															<?endif;?>
														</div>
													</div>
													<?$j++;?>
												<?endif;?>
											<?endforeach;?>
										</div>
									</div>

									<?if($cntChars > $arParams['VISIBLE_PROP_COUNT']):?>
										<div class="more-char-link">
											<span class="choise dotted colored pointer" data-block="char"><?=Loc::getMessage('MORE_CHAR_BOTTOM');?></span>
										</div>
									<?else:?>
										<?$arResult['CHARACTERISTICS'] = [];?>
									<?endif;?>
								</div>
							<?endif;?>
						</div>
					<?endif;?>
					<?if ($bOrderButton || $bAskButton):?>
						<div class="line-block__item detail-info__btns buttons-block">
							<?if ($bOrderButton):?>
								<div>
									<?=$orderButtonHtml;?>
								</div>
							<?endif;?>
							<?if ($bAskButton):?>
								<div>
									<?=$askButtonHtml;?>
								</div>
							<?endif;?>
						</div>
					<?endif;?>
				</div>
			</div>

			<?if(
				$templateData['SECTION_BNR_CONTENT'] || 
				(
					$arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] != 'TOP_CONTENT' && 
					$bTopImg
				)
			):?>
				</div>
			<?endif;?>
		</div>

			<?if (
				$arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] != 'TOP' &&
				$arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'] != 'TOP_ON_HEAD' &&
				!$templateData['SECTION_BNR_CONTENT']
			):?>
				</div>
			<?endif;?>
		</div>
	<?$this->EndViewTarget();?>
<?endif;?>

<?if (($bOrderButton || $bCanBuy  || $bAskButton) && !$bTopInfo):?>
	<?$this->SetViewTarget('PRODUCT_ORDER_SALE_INFO');?>
		<div class="order-info-block" itemprop="offers" itemscope itemtype="http://schema.org/Offer" data-id="<?=$arResult['ID']?>"<?=($dataItem ? ' data-item="'.$dataItem.'"' : '')?>>
			<div class="line-block line-block--gap line-block--align-normal line-block--flex-wrap line-block--row-gap line-block--row-gap-24 line-block--col-gap line-block--col-gap-0">
				<div class="line-block__item flex-1">
					<div class="height-100 flexbox flexbox--justify-between">
						<?$APPLICATION->IncludeComponent(
							'bitrix:main.include',
							'',
							array(
								'AREA_FILE_SHOW' => 'page',
								'AREA_FILE_SUFFIX' => 'ask',
								'EDIT_TEMPLATE' => ''
							)
						);?>
					</div>
				</div>	
				<div class="line-block__item order-info-btns flexbox flexbox--align-end flexbox--align-start-to-991 gap">
						<?
						$prices = (new TSolution\Product\Prices(
							$arResult,
							$arParams,
							[
								'PRICE_FONT' => 20,
							]
						))->show();
						?>
						<div class="order-info-btn line-block line-block--gap line-block--gap-12 line-block--align-normal">
							<?if ($bOrderButton || $bCanBuy ):?>
								<?
								$arBtnConfig = [
									'ITEM' => $arResult,
									'ITEM_ID' => $arResult['ID'],
									'BASKET_URL' => $basketURL,
									'BASKET' => $bOrderViewBasket,
									'ORDER_BTN' => $bOrderButton,
									'BTN_CLASS' => 'btn-lg btn-wide',
									'BTN_ORDER_CLASS' => 'btn-lg btn-wide min_width--300',
									'BTN_IN_CART_CLASS' => 'btn-lg btn-wide',
									'BTN_CALLBACK_CLASS' => 'btn-transparent-border',
									'DETAIL_PAGE' => true,
									'SHOW_COUNTER' => false,
									'SHOW_MORE' => false,
									'CATALOG_IBLOCK_ID' => $arResult['IBLOCK_ID'],
									'ORDER_FORM_ID' => $arParams['FORM_ID_ORDER_SERVISE'] ? $arParams['FORM_ID_ORDER_SERVISE'] : 'aspro_'.TSolution::solutionName.'_order_services',
									'CONFIG' => array_merge(
										TSolution\Product\Basket::getConfig(),
										[
											'EXPRESSION_ORDER_BUTTON' => $arParams['EXPRESSION_SERVICE_ORDER_BUTTON'] ?: Loc::getMessage('S_ORDER_SERVISE'),
											'BUYMISSINGGOODS' => 'ORDER',
											'BUYNOPRICEGGOODS' => 'ORDER',
										],
									),
									'TOTAL_COUNT' => $bCanBuy ? PHP_INT_MAX : 0,
									'HAS_PRICE' => $prices->isCatalogFilled() && $prices->isGreaterThanZero(),
									'EMPTY_PRICE' => $prices->isEmpty(),
								];
								
								$arBasketConfig = TSolution\Product\Basket::getOptions($arBtnConfig);
								?>
								<?if ($bAskButton):?>
									<div class="line-block__item">
										<button class="btn btn-default btn-lg btn-transparent-bg animate-load btn-wide"
											data-param-id="<?=\TSolution::getFormID("aspro_".TSolution::solutionName."_question");?>" 
											data-name="question" 
											data-event="jqm" 
											data-autoload-need_product="<?=\TSolution::formatJsName($arResult['NAME'])?>"
											title="<?=Loc::getMessage('QUESTION_FORM_TITLE')?>"
										>
											<span>?</span>
										</a>
									</div>
								<?endif;?>
								<div class="line-block__item">
									<?=$arBasketConfig['HTML']?>
								</div>
							<?elseif ($bAskButton):?>
								<div class="line-block__item">
									<?=$askButtonHtml;?>
								</div>
							<?endif;?>
						</div>
				</div>
			</div>
		</div>
	<?$this->EndViewTarget();?>
<?endif;?>

<?$templateData['PREVIEW_TEXT'] = boolval(strlen($arResult['PREVIEW_TEXT']) && !$templateData['SECTION_BNR_CONTENT']);?>

<?if (boolval(strlen($arResult['PREVIEW_TEXT'])) && boolval(strlen($arResult['PROPERTIES']['ANONS']['VALUE'])) && $templateData['SECTION_BNR_CONTENT']) {
	$templateData['PREVIEW_TEXT'] = true;
}?>

<?if (
	$templateData['PREVIEW_TEXT'] && 
	(in_array($arResult['PROPERTIES']['PHOTOPOS']['VALUE_XML_ID'], ['LEFT', 'RIGHT']))
):?>
	<div class="introtext">
		<?if($arResult['PREVIEW_TEXT_TYPE'] == 'text'):?>
			<p><?=$arResult['PREVIEW_TEXT'];?></p>
		<?else:?>
			<?=$arResult['PREVIEW_TEXT'];?>
		<?endif;?>
	</div>
	<?unset($templateData['PREVIEW_TEXT']);?>
<?endif;?>

<?// detail description?>
<?$templateData['DETAIL_TEXT'] = boolval(strlen($arResult['DETAIL_TEXT']));?>
<?if($templateData['DETAIL_TEXT'] || $templateData['PREVIEW_TEXT']):?>
	<?$this->SetViewTarget('PRODUCT_DETAIL_TEXT_INFO');?>
		<div class="content" itemprop="description">
			<?if ($templateData['PREVIEW_TEXT']):?>
				<div class="introtext">
					<?if($arResult['PREVIEW_TEXT_TYPE'] == 'text'):?>
						<p><?=$arResult['PREVIEW_TEXT'];?></p>
					<?else:?>
						<?=$arResult['PREVIEW_TEXT'];?>
					<?endif;?>
				</div>
			<?endif?>
			<?if ($templateData['DETAIL_TEXT']):?>
				<?=$arResult['DETAIL_TEXT'];?>
			<?endif;?>
		</div>
	<?$this->EndViewTarget();?>
<?endif;?>

<?// props content?>
<?$templateData['SHOW_CHARACTERISTICS'] = !!$arResult['CHARACTERISTICS'];?>
<?if ($arResult['CHARACTERISTICS']):?>
	<?$this->SetViewTarget('PRODUCT_PROPS_INFO');?>
	<?TSolution\Functions::showBlockHtml([
		'FILE' => '/chars.php',
		'PARENT_COMPONENT' => $this->getComponent(),
		'PARAMS' => [
			'GRUPPER_PROPS' => $arParams['GRUPPER_PROPS'],
			'IBLOCK_ID' => $arResult['IBLOCK_ID'],
			'IBLOCK_TYPE' => $arResult['IBLOCK_TYPE'],
			'CHARACTERISTICS' => $arResult['CHARACTERISTICS'],
			'SKU_IBLOCK_ID' => '',
			'OFFER_PROP' => [],
			'SHOW_HINTS' => $arParams['SHOW_HINTS'],
			'PROPERTIES_DISPLAY_TYPE' => $arParams['PROPERTIES_DISPLAY_TYPE'],
		],
	]);?>
	<?$this->EndViewTarget();?>
<?endif;?>

<?// files?>
<?$templateData['DOCUMENTS'] = boolval($arResult['DOCUMENTS']);?>
<?if ($templateData['DOCUMENTS']):?>
	<?$this->SetViewTarget('PRODUCT_FILES_INFO');?>
		<?TSolution\Functions::showBlockHtml([
			'FILE' => '/documents.php',
			'PARAMS' => [
				'ITEMS' => $arResult['DOCUMENTS']
			],
		]);?>
	<?$this->EndViewTarget();?>
<?endif;?>

<?// big gallery?>
<?$templateData['BIG_GALLERY'] = boolval($arResult['BIG_GALLERY']);?>
<?if($arResult['BIG_GALLERY']):?>
	<?$this->SetViewTarget('PRODUCT_BIG_GALLERY_INFO');?>
		<?TSolution\Functions::showGallery($arResult['BIG_GALLERY'], [
			'CONTAINER_CLASS' => 'gallery-detail font_13',
		]);?>
	<?$this->EndViewTarget();?>
<?endif;?>

<?// video?>
<?$templateData['VIDEO'] = boolval($arResult['VIDEO']);
$bOneVideo = count($arResult['VIDEO']) == 1;?>
<?if($arResult['VIDEO']):?>
	<?$this->SetViewTarget('PRODUCT_VIDEO_INFO');?>
		<?TSolution\Functions::showBlockHtml([
			'FILE' => 'video/detail_video_block.php',
			'PARAMS' => [
				'VIDEO' => $arResult['VIDEO'],
			],
		])?>
	<?$this->EndViewTarget();?>
<?endif;?>