<? if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
CModule::IncludeModule("blog");

$sectionId = $arParams['SECTION_ID'];
global $USER;

if(!empty($sectionId)) {

    $arResult['SECTION_ID'] = $sectionId;

    $arSectionFilter = array('IBLOCK_ID' => 16, 'GLOBAL_ACTIVE' => 'Y', 'ID' => $sectionId);
    $dbSectionsList = CIBlockSection::GetList(array(), $arSectionFilter, false, array('NAME'));
    $arSectionResult = $dbSectionsList->GetNext();

    if(!empty($arSectionResult)) {
        $arResult['SECTION_NAME'] = $arSectionResult['NAME'];
    }

    if(!empty($USER->getEmail())) {
        $arResult['USER_EMAIL'] = $USER->getEmail();
    }
}

// === SENDSAY: парсим GET-параметры из ссылки письма ===
// Формат: ?sendsay_plc=ACCOUNT,issue,letter,member
$arResult['SENDSAY_TRACK'] = ['issue' => null, 'letter' => null, 'member' => null, 'has_all' => false];

if (!empty($_GET['sendsay_plc'])) {
    $parts = explode(',', (string)$_GET['sendsay_plc']);
    // ожидаем минимум 4 элемента: ACCOUNT, issue, letter, member
    if (count($parts) >= 4) {
        // берём ТОЛЬКО цифры, остальное отбрасываем
        $issue  = preg_replace('/\D+/', '', $parts[1]);
        $letter = preg_replace('/\D+/', '', $parts[2]);
        $member = preg_replace('/\D+/', '', $parts[3]);

        if ($issue !== '' && $letter !== '' && $member !== '') {
            $arResult['SENDSAY_TRACK']['issue']  = (int)$issue;
            $arResult['SENDSAY_TRACK']['letter'] = (int)$letter;
            $arResult['SENDSAY_TRACK']['member'] = (int)$member;
            $arResult['SENDSAY_TRACK']['has_all'] = true;
        }
    }
}

// Флаг, можно ли вообще отправлять событие
$hasEmail = !empty($arResult['USER_EMAIL']);
$hasPlc   = !empty($arResult['SENDSAY_TRACK']['has_all']);
$arResult['SENDSAY_CAN_SEND'] = ($hasEmail || $hasPlc);

// Подготовим объект meta для передачи во 2-й аргумент ssecEvent
$meta = [];
if ($hasEmail) {
    $meta['email'] = $arResult['USER_EMAIL'];
}
if ($hasPlc) {
    $meta['issue']  = $arResult['SENDSAY_TRACK']['issue'];
    $meta['letter'] = $arResult['SENDSAY_TRACK']['letter'];
    $meta['member'] = $arResult['SENDSAY_TRACK']['member'];
}
$arResult['SENDSAY_META'] = $meta;
// === /SENDSAY ===


$this->IncludeComponentTemplate();
