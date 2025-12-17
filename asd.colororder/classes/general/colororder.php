<?php

IncludeModuleLangFile(__FILE__);

class CASDColorOrderUtil {
	public static function ForOnAdminListDisplayHandler(&$list) {

		$arUsers = array();
		$arOrders = array();
		$arNewHeaders = array();

		$list->arVisibleColumns[] = 'ASD_SALE_GROUP';
		$list->aVisibleHeaders = array_merge(array('ASD_SALE_GROUP' => array()), $list->aVisibleHeaders);
		$arNewHeaders['ASD_SALE_GROUP'] = array(
			'id' => 'ASD_SALE_GROUP',
			'content' => '',
			'default' => true,
			'align' => 'center',
		);
		foreach ($list->aHeaders as $k => $arItem) {
			$arNewHeaders[$k] = $arItem;
		}
		$list->aHeaders = $arNewHeaders;

		foreach ($list->aRows as $id => $row) {
			$arUsers[] = $row->arRes['USER_ID'];
			$arOrders[] = $row->arRes['ID'];
		}

		$arColors = CASDColorOrder::GetColors($arOrders);
		if (empty($arColors)) {
		//	return;
		}

		$arGroups = CASDColorOrderDB::GetUsersGroups($arUsers);
		$arOrders = CASDColorOrder::GetOrders($arOrders);

		$popupAction = "javascript:(new BX.CAdminDialog({
						width: 450,
						height: 300,
						resizable: false,
						buttons: [BX.CAdminDialog.btnSave, BX.CAdminDialog.btnCancel],
						content_url: '/bitrix/tools/asd.colororder/edit_marks.php?id=#ID#&bxpublic=Y'})).Show();";

		foreach ($list->aRows as $id => &$row) {

			$arnewActions = array();
			foreach ($row->aActions as $i => $act) {
				$arnewActions[] = $act;
				if ($act['ICON'] == 'print') {
					$popupActionTmp = str_replace('#ID#', $row->arRes['ID'], $popupAction);
					$arnewActions[] = array('ICON' => 'note',
											'TEXT' => GetMessage('ASD_ACTION_POPUP_ACT'),
											'ACTION' => version_compare(SM_VERSION, '11.5.5')>=0 ?  $popupActionTmp : htmlspecialcharsbx($popupActionTmp),
											);
				}
			}
			$row->aActions = $arnewActions;

			$row->aHeadersID = array_merge(array('ASD_SALE_GROUP'), $row->aHeadersID);
			$arNewHeaders = array('ASD_SALE_GROUP' => $list->aHeaders['ASD_SALE_GROUP']);
			foreach ($row->aHeaders as $k => $arItem) {
				$arNewHeaders[$k] = $arItem;
			}
			$row->aHeaders = $arNewHeaders;

			$arColor = array();
			foreach ($arColors as $code => $arClr) {
				$arOrder = $arOrders[$row->arRes['ID']];
				if (in_array(substr($code, 1), (array)$arGroups[$row->arRes['USER_ID']])) {
					$arColor[] = $arClr;
				}
				if ($code == strtolower($row->arRes['STATUS_ID'])) {
					if (preg_match('#<span[^>]+>([^<]+)</span>#is', $row->aFields['STATUS_ID']['view']['value'], $matches)) {
						$arColor[] = array_merge($arClr, array('name' => $matches[1]));
					} else {
						$arColor[] = $arClr;
					}
				}
				if (in_array($code, CASDColorOrder::NeedOrderFields()) && $arOrder[strtoupper($code)]=='Y') {
					$arColor[] = array_merge($arClr, array('comment' => $code=='canceled' ? $arOrder['REASON_CANCELED'] : ''));
				}
				if ($code == 'order_'.$row->arRes['ID']) {
					$arColor = array_merge($arColor, $arClr);
				}
				if ($code=='payed_part' && $arOrder['SUM_PAID']>0 && $arOrder['PAYED']!='Y') {
					$arColor[] = $arClr;
				}
				
				// >>> ВСТАВКА: метка при наличии "Комментариев покупателя" (USER_DESCRIPTION)
                if ($code === 'user_comment') {
                    $userComment = (string)$arOrder['USER_DESCRIPTION'];
                    if (strlen(trim($userComment)) > 0) {
                        // Сократим превью для title
                        if (function_exists('mb_substr')) {
                            $preview = mb_substr(trim($userComment), 0, 120);
                        } else {
                            $preview = substr(trim($userComment), 0, 120);
                        }
                        $preview = htmlspecialcharsbx($preview);

                        // Поддерживаем формат одного цвета и список пресетов
                        if (isset($arClr[0])) {
                            foreach ($arClr as $one) {
                                $one['comment'] = $preview;
                                $arColor[] = $one;
                            }
                        } else {
                            $arColor[] = array_merge($arClr, array('comment' => $preview));
                        }
                    }
                }
                // <<< КОНЕЦ ВСТАВКИ
				
			}
			if (!empty($arColor)) {
				$content = '';
				foreach ($arColor as $arClr) {
					if (strlen($arClr['comment'])) {
						$arClr['name'] .= ' ('.$arClr['comment'].')';
					}
					$content .= '<div style="background-color: '.$arClr['color'].';" class="mark'.($arClr['mark']===true ? ' mark_type_icon' : '').'" title="'.$arClr['name'].'"></div>';
				}
			} else {
				$content = '';
			}
			$row->aFields['ASD_SALE_GROUP'] = array('view' => array('type' => 'html', 'value' => $content));
		}
		CUtil::InitJSCore(array('asd_colororder'));
	}
}