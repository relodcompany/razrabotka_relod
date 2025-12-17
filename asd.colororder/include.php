<?
IncludeModuleLangFile(__FILE__);

if (!function_exists('htmlspecialcharsbx')) {
	function htmlspecialcharsbx($string, $flags=ENT_COMPAT) {
		return htmlspecialchars($string, $flags, (defined('BX_UTF')? 'UTF-8' : 'ISO-8859-1'));
	}
}

global $DBType;
CModule::AddAutoloadClasses(
	'asd.colororder',
	$a = array(
		'CASDColorOrderDB' => 'classes/'.$DBType.'/colororder.php',
		'CASDdb' => 'classes/'.$DBType.'/db.php',
		'CASDColorOrderUtil' => 'classes/general/colororder.php',
		'CASDAdminList' => 'classes/general/admin_list.php',
		'CASDAdminForm' => 'classes/general/admin_form.php',
	)
);

class CASDColorOrder {

	public static function NeedOrderFields() {
		return array('canceled', 'payed', 'payed_part', 'allow_delivery');
	}

	public static function GetUsers($arIDs) {
		$arUsers = array();
		if (!empty($arIDs) && is_array($arIDs)) {
			$rsUsers = CUser::GetList($by, $order, $arFilter = array('ID' => implode('|', $arIDs)));
			while ($arUser = $rsUsers->GetNext()) {
				$arUsers[$arUser['ID']] = $arUser;
			}
		}
		return $arUsers;
	}

	public static function GetOrders($arOrders) {
		if (!empty($arOrders) && CModule::IncludeModule('sale')) {
			if (!is_array($arOrders)) {
				$arOrders = array($arOrders);
			}
			
			$rsOrders = CSaleOrder::GetList(
    array(),
    array('ID' => $arOrders),
    false,
    false,
    array('ID','REASON_CANCELED','SUM_PAID','CANCELED','PAYED','PAYED_PART','ALLOW_DELIVERY','USER_DESCRIPTION')
);

			
			$arOrders = array();
			while ($arOrder = $rsOrders->Fetch()) {
				$arOrders[$arOrder['ID']] = $arOrder;
			}
		}
		return $arOrders;
	}

	public static function GetColors($arOrders) {
		$arColors = array();
		if (CModule::IncludeModuleEx('asd.colororder')!=MODULE_DEMO_EXPIRED) {
			$arAllValsGr = unserialize(COption::GetOptionString('asd.colororder', 'color_groups'));
			$arAllValsSt = unserialize(COption::GetOptionString('asd.colororder', 'color_statuses'));
			$rsGroup = CGroup::GetList($by = 'sort', $order = 'asc');
			while ($arGroup = $rsGroup->GetNext()) {
				if (strlen(trim($arAllValsGr['color_group_' . $arGroup['ID']]))) {
					$arColors['g'.$arGroup['ID']] = array('color' => $arAllValsGr['color_group_' . $arGroup['ID']], 'name' => $arGroup['NAME']);
				}
			}
			if (is_array($arAllValsSt)) {
				foreach ($arAllValsSt as $S => $color) {
					if (strlen(trim($color))) {
						$arColors[strtolower(str_replace('color_status_', '', $S))] = array('color' => $color, 'name' => '');
					}
				}
			}
			foreach (self::NeedOrderFields() as $code) {
				$color = COption::GetOptionString('asd.colororder', 'other_color_status_'.$code);
				if (strlen(trim($color))) {
					$arColors[$code] = array('color' => $color, 'name' => GetMessage('OTHER_COLOR_STATUS_'.  strtoupper($code)));
				}
			}
			
			// Комментарии покупателя (USER_DESCRIPTION)
$__col = trim(COption::GetOptionString('asd.colororder', 'other_color_status_user_comment'));
if (strlen($__col)) {
    $arColors['user_comment'] = array(
        'color' => $__col,
        'name'  => (GetMessage('OTHER_COLOR_STATUS_USER_COMMENT') ?: 'Комментарии покупателя')
    );
}


			
		}
		$arColors = array_merge($arColors, CASDColorOrderDB::GetMarks($arOrders));
		return $arColors;
	}

	public static function OnAdminListDisplayHandler(&$list) {
		if ($GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order.php') {
			CASDColorOrderUtil::ForOnAdminListDisplayHandler($list);
		}
	}

	public static function GetJSGcode() {return '';}//deprecated
}

$arJSAsdColororderConfig = array(
	'asd_colororder' => array(
		'css' => '/bitrix/panel/asd.colororder/interface.css',
	),
);
foreach ($arJSAsdColororderConfig as $ext => $arExt) {
	CJSCore::RegisterExt($ext, $arExt);
}

?>