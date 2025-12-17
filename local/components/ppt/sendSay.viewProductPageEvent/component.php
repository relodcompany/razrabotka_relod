<? if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
CModule::IncludeModule("blog");

$productId = $arParams['PRODUCT_ID'];
global $USER;

if(!empty($productId)) {
    $result = \Bitrix\Catalog\ProductTable::getList(array(
        'filter' => array('=ID'=>$productId),
        'select' => array('ID','QUANTITY','NAME'=>'IBLOCK_ELEMENT.NAME','CODE'=>'IBLOCK_ELEMENT.CODE', 'DETAIL_TEXT'=>'IBLOCK_ELEMENT.DETAIL_TEXT'),
    ));

    if($product=$result->fetch()) {

        $arResult['PRODUCT_ID'] = $productId;

        $arResult['AVAILABLE'] = 0;
if (!empty($product['QUANTITY']) && $product['QUANTITY'] > 0) {
    $arResult['AVAILABLE'] = 1;
}


    }

    $arPrice = CCatalogProduct::GetOptimalPrice($productId, 1, $USER->GetUserGroupArray());

    if(!empty($arPrice)) {
        $arResult['PRICE'] = $arPrice['PRICE']['PRICE'];
        $arResult['OLD_PRICE'] = $arPrice['PRICE']['PRICE'];
    }

    $imagesUrls = '[';

    $arSelect = Array('PROPERTY_MORE_PHOTO', 'DETAIL_TEXT', 'NAME', 'DETAIL_PAGE_URL', 'PROPERTY_BRAND', "IBLOCK_SECTION_ID");
    $arFilter = Array("IBLOCK_ID"=>IntVal(16), "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y", 'ID' => $productId);
    $res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $imagesUrls .= '"https://'.SITE_SERVER_NAME.CFile::GetPath($arFields["PROPERTY_MORE_PHOTO_VALUE"]).'",';


        $arResult['NAME'] = $arFields['NAME'];
        $arResult['DETAIL_TEXT'] = $arFields['DETAIL_TEXT'];
		// === Готовим описание без HTML для SendSay ===
$rawDetail = (string)$arFields['DETAIL_TEXT'];                  // может быть с HTML
$plain = strip_tags($rawDetail);                                // убрать все теги
$plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // &nbsp; &amp; -> обычные символы
$plain = preg_replace('/\s+/u', ' ', $plain);                   // схлопнуть лишние пробелы/переводы строк
$arResult['DETAIL_TEXT_PLAIN'] = trim($plain);
// === /конец блока ===

        $arResult['DETAIL_PAGE_URL'] = 'https://'.SITE_SERVER_NAME.$arFields['DETAIL_PAGE_URL'];

        $brandId = $arFields["PROPERTY_BRAND_VALUE"];
    }

    $imagesUrls .= ']';

    if(!empty($imagesUrls)) {
        $arResult['IMAGES'] = $imagesUrls;
    }
    
    if(!empty($brandId)) {
        $arSelect2 = Array('NAME');
        $arFilter2 = Array("IBLOCK_ID"=>IntVal(15), "ACTIVE_DATE"=>"Y", "ACTIVE"=>"Y", 'ID' => $brandId);
        $res2 = CIBlockElement::GetList(Array(), $arFilter2, false, false, $arSelect2);

        $ob2 = $res2->GetNextElement();

        $brandFields = $ob2->GetFields();

        $arResult['VENDOR'] = $brandFields['NAME'];
    }

    if(!empty($arFields['IBLOCK_SECTION_ID'])) {
        $arResult['SECTION_ID'] = $arFields['IBLOCK_SECTION_ID'];

        $arSectionFilter = array('IBLOCK_ID' => 16, 'GLOBAL_ACTIVE' => 'Y', 'ID' => $arResult['SECTION_ID']);
        $dbSectionsList = CIBlockSection::GetList(array(), $arSectionFilter, false, array('NAME'));
        $arSectionResult = $dbSectionsList->GetNext();
        
        if(!empty($arSectionResult)) {
            $arResult['SECTION_NAME'] = $arSectionResult['NAME'];
        }
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
