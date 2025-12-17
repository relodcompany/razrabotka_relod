<?
$module_id = 'asd.colororder';
$POST_RIGHT = $APPLICATION->GetGroupRight('main');

if ($POST_RIGHT >= 'R'):

	IncludeModuleLangFile($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/options.php');
	IncludeModuleLangFile(__FILE__);

	$arAllOptions = array();
//	$arAllOptions[] = array('colorize_tr', GetMessage('OPT_COLORIZE_TR').':', array('checkbox'));

	$arGroups = array();
	$rsGroups = CGroup::GetList($by='c_sort', $order='asc');
	while ($arGroup = $rsGroups->GetNext()) {
		if ($arGroup['ID'] != 2) {
			$code = 'color_group_'.$arGroup['ID'];
			$name = '[<a href="/bitrix/admin/group_edit.php?lang='.LANG.'&amp;ID='.$arGroup['ID'].'" title="'.GetMessage('MODULE_EDIT_GROUP').'">'.$arGroup['ID'].'</a>] '.$arGroup['NAME'];
			$arAllOptions[] = array($code, $name.':', array('colorpicker', 8));
		}
	}

	if (Cmodule::IncludeModule('sale')) {
		$rsStatus = CSaleStatus::GetList(array('SORT' => 'ASC', 'NAME' => 'ASC'), array('LID' => LANG));
		while ($arStatus = $rsStatus->GetNext()) {
			$code = 'color_status_'.strtolower($arStatus['ID']);
			$name = '[<a href="/bitrix/admin/sale_status_edit.php?lang='.LANG.'&amp;ID='.$arStatus['ID'].'" title="'.GetMessage('MODULE_EDIT_STATUS').'">'.$arStatus['ID'].'</a>] '.$arStatus['NAME'];
			$arAllOptions[$arStatus['ID']] = array($code, $name.':', array('colorpicker', 8));
		}
	}
	$arAllOptions[] = array('other_color_status_canceled',GetMessage('MODULE_OTHER_COLOR_STATUS_CANCELED').':', array('colorpicker', 8));
	$arAllOptions[] = array('other_color_status_payed',GetMessage('MODULE_OTHER_COLOR_STATUS_PAYED').':', array('colorpicker', 8));
	$arAllOptions[] = array('other_color_status_payed_part',GetMessage('MODULE_OTHER_COLOR_STATUS_PAYED_PART').':', array('colorpicker', 8));
	$arAllOptions[] = array('other_color_status_allow_delivery',GetMessage('MODULE_OTHER_COLOR_STATUS_DELIV').':', array('colorpicker', 8));
	
	// >>> ВСТАВИТЬ ЭТУ СТРОКУ (без вложенных массивов!)
    $arAllOptions[] = array('other_color_status_user_comment',GetMessage('ASD_OPT_OTHER_STATUS_USER_COMMENT').':', array('colorpicker', 8));
    // <<< КОНЕЦ ВСТАВКИ

	$tabControl = new CAdmintabControl('tabControl', array(
															array('DIV' => 'edit1', 'TAB' => GetMessage('MODULE_HEADER_GROUPS'), 'ICON' => ''),
															array('DIV' => 'edit2', 'TAB' => GetMessage('MODULE_HEADER_STATUSES'), 'ICON' => ''),
															array('DIV' => 'edit3', 'TAB' => GetMessage('MODULE_HEADER_OTHER_STATUSES'), 'ICON' => ''),
															));

	if (ToUpper($REQUEST_METHOD) == 'POST' &&
		strlen($Update.$Apply.$RestoreDefaults)>0 &&
		($POST_RIGHT=='W' || $POST_RIGHT=='X') &&
		check_bitrix_sessid())
	{
		if (strlen($RestoreDefaults)>0)
		{
			COption::RemoveOption($module_id);
		}
		else
		{
			$arAllValsGr = array();
			$arAllValsSt = array();
			foreach ($arAllOptions as $arOption)
			{
				$name = $arOption[0];
				if ($arOption[2][0]=='text-list')
				{
					$val = '';
					for ($j=0; $j<count($$name); $j++)
						if (strlen(trim(${$name}[$j])) > 0)
							$val .= ($val <> ''? ',':'').trim(${$name}[$j]);
				}
				elseif ($arOption[2][0]=='doubletext')
				{
					$val = ${$name.'_1'}.'x'.${$name.'_2'};
				}
				elseif ($arOption[2][0]=='selectbox')
				{
					$val = '';
					for ($j=0; $j<count($$name); $j++)
						if (strlen(trim(${$name}[$j])) > 0)
							$val .= ($val <> ''? ',':'').trim(${$name}[$j]);
				}
				else
					$val = $$name;

				if ($arOption[2][0] == 'checkbox' && $val<>'Y')
					$val = 'N';

				if (strpos($name, 'color_group') === 0) {
					$arAllValsGr[$name] = $val;
				} elseif (strpos($name, 'color_status') === 0) {
					$arAllValsSt[$name] = $val;
				} else {
					COption::SetOptionString($module_id, $name, $val);
				}
			}

			COption::SetOptionString($module_id, 'color_groups', serialize($arAllValsGr));
			COption::SetOptionString($module_id, 'color_statuses', serialize($arAllValsSt));

		}

		$Update = $Update.$Apply;

		if (strlen($Update)>0 && strlen($_REQUEST['back_url_settings'])>0)
			LocalRedirect($_REQUEST['back_url_settings']);
		else
			LocalRedirect($APPLICATION->GetCurPage().'?mid='.urlencode($mid).'&lang='.urlencode(LANGUAGE_ID).'&back_url_settings='.urlencode($_REQUEST['back_url_settings']).'&'.$tabControl->ActiveTabParam());
	}

	?>
	<script type="text/javascript">
		function OnColorPicker(val, obj) {
			var ct = document.getElementById(obj.oPar.id);
			if (val != false) {
				ct.value = val;
				ct.style['background'] = val;
			}
		}
	</script>
	<form method="post" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=urlencode($mid)?>&amp;lang=<?=LANGUAGE_ID?>"><?
	$tabControl->Begin();
	$tabControl->BeginNextTab();
	$bFirstSecTab = false;
	$bSecTab = false;
	$arAllValsGr = unserialize(COption::GetOptionString($module_id, 'color_groups'));
	$arAllValsSt = unserialize(COption::GetOptionString($module_id, 'color_statuses'));
	$arAllValsStOth = unserialize(COption::GetOptionString($module_id, 'other_color_statuses'));

	foreach($arAllOptions as $i => $Option):

		$type = $Option[2];
		if (strpos($Option[0], 'color_group') === 0) {
			$val = $arAllValsGr[$Option[0]];
		} elseif (strpos($Option[0], 'color_status') === 0) {
			$val = $arAllValsSt[$Option[0]];
		} elseif (strpos($Option[0], 'color_other_status') === 0) {
			$val = $arAllValsStOth[$Option[0]];

		} else {
			$val = COption::GetOptionString($module_id, $Option[0]);
		}
		if (!$bFirstSecTab && strpos($Option[0], 'color_status')===0) {
			$bFirstSecTab = true;
			$tabControl->BeginNextTab();
		}
		if (!$bSecTab && strpos($Option[0], 'other_color_status')===0) {
			$bSecTab = true;
			$tabControl->BeginNextTab();
		}
		?>
		<tr>
			<td valign="top" width="40%"><?
				if ($type[0]=='checkbox')
					echo '<label for="'.htmlspecialchars($Option[0]).'">'.$Option[1].'</label>';
				else
					echo $Option[1];
		?></td>
		<td valign="middle" width="60%"><?
			if ($type[0] == 'checkbox'):
				?><input type="checkbox" name="<?echo htmlspecialchars($Option[0])?>" id="<?echo htmlspecialchars($Option[0])?>" value="Y"<?if($val == 'Y')echo ' checked="checked"';?> /><?
			elseif ($type[0] == 'text'):
				?><input type="text" size="<?echo $type[1]?>" maxlength="255" value="<?echo htmlspecialchars($val)?>" name="<?echo htmlspecialchars($Option[0])?>" /><?
			elseif ($type[0] == 'doubletext'):
				list($val1, $val2) = explode('x', $val);
				?><input type="text" size="<?echo $type[1]?>" maxlength="255" value="<?echo htmlspecialchars($val1)?>" name="<?echo htmlspecialchars($Option[0].'_1')?>" /><?
				?><input type="text" size="<?echo $type[1]?>" maxlength="255" value="<?echo htmlspecialchars($val2)?>" name="<?echo htmlspecialchars($Option[0].'_2')?>" /><?
			elseif ($type[0] == 'textarea'):
				?><textarea rows="<?echo $type[1]?>" cols="<?echo $type[2]?>" name="<?echo htmlspecialchars($Option[0])?>"><?echo htmlspecialchars($val)?></textarea><?
			elseif ($type[0] == 'colorpicker'):
				?><input type="text" size="<?echo $type[1]?>" maxlength="15" value="<?echo htmlspecialchars($val)?>" name="<?echo htmlspecialchars($Option[0])?>" id="<?echo htmlspecialchars($Option[0])?>" /><?
				$APPLICATION->IncludeComponent('bitrix:main.colorpicker', false, array(
								'ID' => htmlspecialchars($Option[0]),
								'NAME' => GetMessage('MODULE_COLOR_FOR'),
								'SHOW_BUTTON' => 'Y',
								'ONSELECT' => 'OnColorPicker'
							));

					?>
					<script type="text/javascript">
						var ct = document.getElementById('<?=htmlspecialchars($Option[0])?>');
						ct.style['background'] = ct.value;
					</script><?
			elseif ($type[0] == 'text-list'):
				$aVal = explode(",", $val);
				for($j=0; $j<count($aVal); $j++):
					?><input type="text" size="<?echo $type[2]?>" value="<?echo htmlspecialchars($aVal[$j])?>" name="<?echo htmlspecialchars($Option[0]).'[]'?>" /><br /><?
				endfor;
				for($j=0; $j<$type[1]; $j++):
					?><input type="text" size="<?echo $type[2]?>" value="" name="<?echo htmlspecialchars($Option[0]).'[]'?>" /><br /><?
				endfor;
			elseif ($type[0]=='selectbox'):
				$arr = $type[1];
				$arr_keys = array_keys($arr);
				$arVal = explode(',', $val);
				?><select name="<?echo htmlspecialchars($Option[0])?>[]"<?= $type[2]?>><?
					for($j=0; $j<count($arr_keys); $j++):
						?><option value="<?echo $arr_keys[$j]?>"<?if(in_array($arr_keys[$j], $arVal))echo ' selected="selected"'?>><?echo htmlspecialchars($arr[$arr_keys[$j]])?></option><?
					endfor;
					?></select><?
			endif;
			echo $Option[3];?>
		</td>
		<?
	endforeach;

	$tabControl->Buttons();
	?>
	<input <?if ($POST_RIGHT < 'W') echo 'disabled="disabled"' ?> type="submit" name="Update" value="<?=GetMessage('MAIN_SAVE')?>" title="<?=GetMessage('MAIN_OPT_SAVE_TITLE')?>" />
	<input <?if ($POST_RIGHT < 'W') echo 'disabled="disabled"' ?> type="submit" name="Apply" value="<?=GetMessage('MAIN_OPT_APPLY')?>" title="<?=GetMessage('MAIN_OPT_APPLY_TITLE')?>" />
	<?if (strlen($_REQUEST["back_url_settings"]) > 0):?>
		<input <?if ($POST_RIGHT < 'W') echo 'disabled="disabled"' ?> type="button" name="Cancel" value="<?=GetMessage('MAIN_OPT_CANCEL')?>" title="<?=GetMessage('MAIN_OPT_CANCEL_TITLE')?>" onclick="window.location='<?echo htmlspecialchars(CUtil::addslashes($_REQUEST['back_url_settings']))?>'" />
		<input type="hidden" name="back_url_settings" value="<?=htmlspecialchars($_REQUEST["back_url_settings"])?>" />
	<?endif?>
	<input <?if ($POST_RIGHT < 'W') echo 'disabled="disabled"' ?> type="submit" name="RestoreDefaults" title="<?echo GetMessage("MAIN_HINT_RESTORE_DEFAULTS")?>" onclick="confirm('<?echo AddSlashes(GetMessage('MAIN_HINT_RESTORE_DEFAULTS_WARNING'))?>')" value="<?echo GetMessage('MAIN_RESTORE_DEFAULTS')?>" />
	<?=bitrix_sessid_post();?>
	<?$tabControl->End();?>
	</form>

<?endif;?>