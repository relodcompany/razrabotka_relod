<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
IncludeModuleLangFile(__FILE__);

function __ShowError($mess) {
	global $APPLICATION, $adminPage, $USER, $adminMenu, $adminChain;
	require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
	ShowError($mess);
	require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
	die();
}

CUtil::InitJSCore(array('core'));
$id = intval($_REQUEST['id']);
$save = isset($_REQUEST['save']);

if ($id>0 && CModule::IncludeModule('asd.colororder') && CModule::IncludeModule('sale')) {
	if ($APPLICATION->GetGroupRight('sale') == 'D') {
		__ShowError(GetMessage('ASD_TOOLS_DENIED'));
	} elseif (CSaleOrder::GetByID($id) && CSaleOrder::CanUserViewOrder($id, $USER->GetUserGroupArray())) {
		if ($save) {
			require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_js.php');
			$arForSave = array();
			$arColors = $_REQUEST['color'];
			$arComments = $_REQUEST['comment'];
			foreach ($arColors as $i => $color) {
				$color = trim($color);
				if (strlen($color)) {
					$arForSave[] = array($color, $arComments[$i]);
				}
			}
			CASDColorOrderDB::SetMarks($id, $arForSave);
			?><script type="text/javascript">
				top.BX.closeWait(); top.BX.WindowManager.Get().AllowClose(); top.BX.WindowManager.Get().Close();
				top.BX.reload();
			</script><?
			require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin_js.php');
			die();
		} else {
			$arMarks = array_pop(CASDColorOrderDB::GetMarks($id));
		}
	} else {
		__ShowError(GetMessage('ASD_TOOLS_DENIED'));
	}
} else {
	__ShowError(GetMessage('ASD_TOOLS_NOT_INST'));
}

$APPLICATION->SetTitle(GetMessage('ASD_TOOLS_TITLE', array('#ID#' => $id)));
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
$APPLICATION->ShowHeadStrings();
$APPLICATION->ShowHeadScripts();
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
<form method="post" action="/bitrix/tools/asd.colororder/edit_marks.php">
	<input type="hidden" name="id" value="<?= $id?>" />
	<input type="hidden" name="save" value="Y" />
	<table width="100%">
		<tr>
			<td><?= GetMessage('ASD_TOOLS_HEAD_COLOR')?></td>
			<td><?= GetMessage('ASD_TOOLS_HEAD_NOTE')?></td>
		</tr>
		<?
		if (!empty($arMarks))
		foreach ($arMarks as $i => $arM):?>
		<tr>
			<td>
				<input type="text" name="color[<?= $i?>]" id="color_<?= $i?>" size="10" value="<?= $arM['color']?>" />
				<?$APPLICATION->IncludeComponent('bitrix:main.colorpicker', "", array(
								'ID' => 'color_'.$i,
								'NAME' => GetMessage('ASD_TOOLS_COLOR_FOR'),
								'SHOW_BUTTON' => 'Y',
								'ONSELECT' => 'OnColorPicker'
							));?>
				<script type="text/javascript">
					var ct = document.getElementById('color_<?= $i?>');
					ct.style['background'] = ct.value;
				</script>
			</td>
			<td><input type="text" name="comment[<?= $i?>]" size="40" value="<?= $arM['name']?>" /></td>
		</tr>
		<?endforeach;?>
		<?for ($j=$i+1; $j<5; $j++):?>
		<tr>
			<td>
				<input type="text" name="color[<?= $j?>]" id="color_<?= $j?>" size="10" value="" />
				<?$APPLICATION->IncludeComponent('bitrix:main.colorpicker', "", array(
								'ID' => 'color_'.$j,
								'NAME' => GetMessage('ASD_TOOLS_COLOR_FOR'),
								'SHOW_BUTTON' => 'Y',
								'ONSELECT' => 'OnColorPicker'
							));?>

			</td>
			<td><input type="text" name="comment[<?= $j?>]" size="40" value="" /></td>
		</tr>
		<?endfor;?>
	</table>
</form>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');