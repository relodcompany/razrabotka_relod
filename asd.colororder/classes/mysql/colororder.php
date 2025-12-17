<?php

class CASDColorOrderDB {

	const mysqlError = 'MySQL error in class CASDColorOrderDB on line ';

	public static function GetUsersGroups($arIDs) {
		$arGroups = array();
		if (!empty($arIDs) && is_array($arIDs)) {
			$arIDs = array_map('intval', $arIDs);
			$rsGr = $GLOBALS['DB']->Query("SELECT
												*
											FROM
												b_user_group
											WHERE
												USER_ID IN (" . implode(',', $arIDs) . ") AND
												(DATE_ACTIVE_FROM IS NULL OR DATE_ACTIVE_FROM<=NOW()) AND
												(DATE_ACTIVE_TO IS NULL OR DATE_ACTIVE_TO>NOW());", true, self::mysqlError.__LINE__);
			while ($arGr = $rsGr->Fetch()) {
				if (!isset($arGroups[$arGr['USER_ID']])) {
					$arGroups[$arGr['USER_ID']] = array();
				}
				$arGroups[$arGr['USER_ID']][] = $arGr['GROUP_ID'];
			}
		}
		return $arGroups;
	}

	public static function GetMarks($arOrders) {
		$arMarks = array();
		if (!empty($arOrders)) {
			if (!is_array($arOrders)) {
				$arOrders = array($arOrders);
			}
			$arOrders = array_map('intval', $arOrders);
			$rsMarks = $GLOBALS['DB']->Query('SELECT * FROM b_asd_colorrder WHERE ORDER_ID IN('.implode(',', $arOrders).');', true, self::mysqlError.__LINE__);
			while ($arMark = $rsMarks->GetNext()) {
				if (!isset($arMarks['order_'.$arMark['ORDER_ID']])) {
					$arMarks['order_'.$arMark['ORDER_ID']] = array();
				}
				$arMarks['order_'.$arMark['ORDER_ID']][] = array('color' => $arMark['COLOR'],
																'name' => $arMark['COMMENT'],
																'mark' => (strlen(trim($arMark['COMMENT']))>0));
			}
		}
		return $arMarks;
	}

	public static function SetMarks($id, $arForSave) {
		if ($id > 0) {
			$id = intval($id);
			if (!is_array($arForSave)) {
				$arForSave = array($arForSave);
			}
			$GLOBALS['DB']->Query('DELETE FROM b_asd_colorrder WHERE ORDER_ID='.$id, true, self::mysqlError.__LINE__);
			foreach ($arForSave as $arMark) {
				$GLOBALS['DB']->Insert('b_asd_colorrder', array(
					'ORDER_ID' => $id,
					'COLOR' => '"'.$GLOBALS['DB']->ForSQL($arMark[0]).'"',
					'COMMENT' => '"'.$GLOBALS['DB']->ForSQL($arMark[1]).'"',
				), self::mysqlError.__LINE__);
			}
		}
	}

	public static function GetPresets() {
		return $GLOBALS['DB']->Query('SELECT * FROM b_asd_colorpreset;', true, self::mysqlError.__LINE__);
	}

	public static function GetPresetByID($ID) {
		return $GLOBALS['DB']->Query('SELECT * FROM b_asd_colorpreset WHERE ID='.intval($ID).';', true, self::mysqlError.__LINE__);
	}
}