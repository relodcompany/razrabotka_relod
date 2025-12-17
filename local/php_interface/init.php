<?php
use Bitrix\Sale;

AddEventHandler("main", "OnPageStart", "relodStoreSendsaySource");

function relodStoreSendsaySource()
{
    // Не трогаем CLI и админку
    if (php_sapi_name() === 'cli') {
        return;
    }
    if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    // --- 1. "Человекочитаемое" название выпуска (issue.name) ---
    // В PROScript это обычно [% param.issue.name %],
    // в URL приходит как ?issue_name=...
    if (!empty($_GET['issue_name'])) {
        $_SESSION['REL_SENDSAY_ISSUE_NAME'] = (string)$_GET['issue_name'];
    }

    // --- 2. Явные GET-параметры issue / letter / member (если ты их добавишь в ссылку) ---
    if (!empty($_GET['issue'])) {
        $_SESSION['REL_SENDSAY_ISSUE_ID'] = (string)$_GET['issue'];
    }
    if (!empty($_GET['letter'])) {
        $_SESSION['REL_SENDSAY_LETTER_ID'] = (string)$_GET['letter'];
    }
    if (!empty($_GET['member'])) {
        $_SESSION['REL_SENDSAY_MEMBER_ID'] = (string)$_GET['member'];
    }

    // --- 3. Автоматический параметр sendsay_plc=relod,issue,letter,member ---
    if (!empty($_GET['sendsay_plc'])) {
        $rawPlc = (string)$_GET['sendsay_plc'];
        $_SESSION['REL_SENDSAY_PLC_RAW'] = $rawPlc;

        // Ожидаемый формат: relod,13574,73228690,377150
        // account, issue.id, letter.id, member.id
        $parts = explode(',', $rawPlc);

        // Минимально безопасная проверка: 4 части
        if (count($parts) >= 4) {
            // parts[0] — логин аккаунта, обычно не нужен
            $issueId  = trim($parts[1]);
            $letterId = trim($parts[2]);
            $memberId = trim($parts[3]);

            // Не перезаписываем значения, если до этого уже пришли явные ?issue= / ?letter= / ?member=
            if ($issueId !== '' && empty($_SESSION['REL_SENDSAY_ISSUE_ID'])) {
                $_SESSION['REL_SENDSAY_ISSUE_ID'] = $issueId;
            }
            if ($letterId !== '' && empty($_SESSION['REL_SENDSAY_LETTER_ID'])) {
                $_SESSION['REL_SENDSAY_LETTER_ID'] = $letterId;
            }
            if ($memberId !== '' && empty($_SESSION['REL_SENDSAY_MEMBER_ID'])) {
                $_SESSION['REL_SENDSAY_MEMBER_ID'] = $memberId;
            }
        }
    }

    // --- 4. Резерв: сохраняем первый QUERY_STRING целиком (как у тебя было) ---
    if (!empty($_SERVER['QUERY_STRING']) && empty($_SESSION['REL_SENDSAY_QUERY'])) {
        $_SESSION['REL_SENDSAY_QUERY'] = (string)$_SERVER['QUERY_STRING'];
    }
}





AddEventHandler("main", "OnAfterUserRegister", "sendRegisteredUserDataToSendSay");

function sendRegisteredUserDataToSendSay(&$arFields)
{
    if(!empty($arFields['USER_ID'])) {

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://ssec.sendsay.ru/general/ssec/v100/json/relod',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'[{
                "event_type":28,
                "email":"'.$arFields["EMAIL"].'"
            }]',
            CURLOPT_HTTPHEADER => array(
            'Authorization: sendsay apikey=18Wz7MBu1fLT_L1VYUp9xhKpbv-5RpEn_o0kIlnD8VMHd5d26xA',
            'Content-Type: application/json'
            ),
        ));

        $output = curl_exec($ch);
        curl_close($ch);

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://api.sendsay.ru/general/api/v100/json/relod',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => '{
				"action": "member.set",
				"email": "' . $arFields["EMAIL"] . '",
				"datakey": [
					["base.firstName", "set", "' . $arFields["NAME"] . '"],
					["base.lastName", "set", "' . $arFields["LAST_NAME"] . '"]
				]
			}',
			CURLOPT_HTTPHEADER => array(
			'Authorization: sendsay apikey=18Wz7MBu1fLT_L1VYUp9xhKpbv-5RpEn_o0kIlnD8VMHd5d26xA',
            'Content-Type: application/json'
			),
		));

		$output = curl_exec($ch);
		curl_close($ch);
    }
}

AddEventHandler("main", "OnAfterUserLogin", "sendLoggedInUserDataToSendSay");
function sendLoggedInUserDataToSendSay(&$arFields)
{
    if(!empty($arFields['USER_ID'])) {

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://ssec.sendsay.ru/general/ssec/v100/json/relod',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'[{
                "event_type":29,
                "email":"'.$arFields["LOGIN"].'"
            }]',
            CURLOPT_HTTPHEADER => array(
            'Authorization: sendsay apikey=18Wz7MBu1fLT_L1VYUp9xhKpbv-5RpEn_o0kIlnD8VMHd5d26xA',
            'Content-Type: application/json'
            ),
        ));

        $output = curl_exec($ch);
        curl_close($ch);
    }
}




AddEventHandler("sale", "OnBasketUpdate", "sendAddedBasketItemDataToSendSay");

function sendAddedBasketItemDataToSendSay($ID, $arFields)
{
    global $USER;

    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), SITE_ID);

    if(!empty($basket)) {

        $curlData = '[{';

        if (!empty(count($basket->getQuantityList()))) {
            $counter = 1;

            $products = '[';


            foreach ($basket as $basketItem) {
                 $imagesUrls = '[';

                $arFilter = array("IBLOCK_ID" => IntVal(16), "ACTIVE_DATE" => "Y", "ACTIVE" => "Y", 'ID' => $basketItem->getProductId());
                $res = CIBlockElement::GetList(array(), $arFilter, false, false, array('PROPERTY_BRAND', "IBLOCK_SECTION_ID", "DETAIL_PAGE_URL", "PROPERTY_MORE_PHOTO", "DETAIL_TEXT"));
                while($ob = $res->GetNextElement()) {
                    $productFields = $ob->GetFields();

                    $imagesUrls .= '"https://'.SITE_SERVER_NAME.CFile::GetPath($productFields["PROPERTY_MORE_PHOTO_VALUE"]).'",';
                }
                $imagesUrls = substr($imagesUrls, 0, -1);
                $imagesUrls .= ']';

                $brandName = '';

                if (!empty($productFields['PROPERTY_BRAND_VALUE'])) {
                    $arFilter2 = array("IBLOCK_ID" => IntVal(15), "ACTIVE_DATE" => "Y", "ACTIVE" => "Y", 'ID' => $productFields['PROPERTY_BRAND_VALUE']);
                    $res2 = CIBlockElement::GetList(array(), $arFilter2, false, false, array('NAME'));
                    $ob2 = $res2->GetNextElement();
                    $brandFields = $ob2->GetFields();

                    if (!empty($brandFields['NAME'])) {
                        $brandName = $brandFields['NAME'];
                    }
                }

                if (!empty($productFields['IBLOCK_SECTION_ID'])) {
                    $sectionId = $productFields['IBLOCK_SECTION_ID'];

                    $arSectionFilter = array('IBLOCK_ID' => 16, 'GLOBAL_ACTIVE' => 'Y', 'ID' => $sectionId);
                    $dbSectionsList = CIBlockSection::GetList(array(), $arSectionFilter, false, array('NAME'));
                    $arSectionResult = $dbSectionsList->GetNext();

                    if (!empty($arSectionResult)) {
                        $sectionName = $arSectionResult['NAME'];
                    }
                }

                $products .= '{
                    "id": "' . $basketItem->getProductId() . '",
                    "available": 1,
                    "name": "' . $basketItem->getField('NAME') . '",
                    "price": ' . number_format($basketItem->getPrice(), 1, '.', '') . ',
                    "qnt": ' . $basketItem->getQuantity() . ',
                    "vendor": "' . $brandName . '",
                    "category_id": ' . $sectionId . ',
                    "category": "' . $sectionName . '",
                    "url": "https://'.SITE_SERVER_NAME.$productFields['DETAIL_PAGE_URL'].'",
                    "description": "'.str_replace('\'', '&apos;', str_replace(array("\r", "\n"), '', strip_tags($productFields['DETAIL_TEXT']))).'",
                    "picture": '.$imagesUrls.'
                }';

                if(count($basket->getQuantityList())>$counter) {
                    $products .= ',';
                }

                $counter++;
            }


        }

        $products .= ']';

        $curlData.='"email": "'.$USER->getEmail().'",
                "addr_type": "email",';


        $curlData.='"transaction_sum": '. number_format($basket->getPrice(), 1, '.', '').',
                "items": '.$products.',
                "event_type":3
        }]';


        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://ssec.sendsay.ru/general/ssec/v100/json/relod',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $curlData,
            CURLOPT_HTTPHEADER => array(
                'Authorization: sendsay apikey=18Wz7MBu1fLT_L1VYUp9xhKpbv-5RpEn_o0kIlnD8VMHd5d26xA',
                'Content-Type: application/json'
            ),
        ));

        $output = curl_exec($ch);
        curl_close($ch);
    }
}

AddEventHandler("sale", "OnBasketDelete", "sendDeletedBasketItemDataToSendSay");
function sendDeletedBasketItemDataToSendSay($ID)
{
    global $USER;

    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), SITE_ID);

    if(!empty($basket)) {

        $curlData = '[{';
        $products = '[';

        if (!empty(count($basket->getQuantityList()))) {
            $counter = 1;

            foreach ($basket as $basketItem) {
                 $imagesUrls = '[';

                $arFilter = array("IBLOCK_ID" => IntVal(16), "ACTIVE_DATE" => "Y", "ACTIVE" => "Y", 'ID' => $basketItem->getProductId());
                $res = CIBlockElement::GetList(array(), $arFilter, false, false, array('PROPERTY_BRAND', "IBLOCK_SECTION_ID", "DETAIL_PAGE_URL", "PROPERTY_MORE_PHOTO", "DETAIL_TEXT"));
                while($ob = $res->GetNextElement()) {
                    $productFields = $ob->GetFields();
                    $imagesUrls .= '"https://'.SITE_SERVER_NAME.CFile::GetPath($productFields["PROPERTY_MORE_PHOTO_VALUE"]).'",';
                }
                $imagesUrls = substr($imagesUrls, 0, -1);
                $imagesUrls .= ']';

                $brandName = '';

                if (!empty($productFields['PROPERTY_BRAND_VALUE'])) {
                    $arFilter2 = array("IBLOCK_ID" => IntVal(15), "ACTIVE_DATE" => "Y", "ACTIVE" => "Y", 'ID' => $productFields['PROPERTY_BRAND_VALUE']);
                    $res2 = CIBlockElement::GetList(array(), $arFilter2, false, false, array('NAME'));
                    $ob2 = $res2->GetNextElement();
                    $brandFields = $ob2->GetFields();

                    if (!empty($brandFields['NAME'])) {
                        $brandName = $brandFields['NAME'];
                    }
                }

                if (!empty($productFields['IBLOCK_SECTION_ID'])) {
                    $sectionId = $productFields['IBLOCK_SECTION_ID'];

                    $arSectionFilter = array('IBLOCK_ID' => 16, 'GLOBAL_ACTIVE' => 'Y', 'ID' => $sectionId);
                    $dbSectionsList = CIBlockSection::GetList(array(), $arSectionFilter, false, array('NAME'));
                    $arSectionResult = $dbSectionsList->GetNext();

                    if (!empty($arSectionResult)) {
                        $sectionName = $arSectionResult['NAME'];
                    }
                }

                $products .= '{
                    "id": "' . $basketItem->getProductId() . '",
                    "available": 1,
                    "name": "' . $basketItem->getField('NAME') . '",
                    "price": ' . number_format($basketItem->getPrice(), 1, '.', '') . ',
                    "qnt": ' . $basketItem->getQuantity() . ',
                    "vendor": "' . $brandName . '",
                    "category_id": ' . $sectionId . ',
                    "category": "' . $sectionName . '",
                    "url": "https://'.SITE_SERVER_NAME.$productFields['DETAIL_PAGE_URL'].'",
                    "description": "'.addslashes(str_replace(array("\r", "\n"), '', $productFields['DETAIL_TEXT'])).'",
                    "picture": '.$imagesUrls.'
                }';

                if(count($basket->getQuantityList())>$counter) {
                    $products .= ',';
                }

                $counter++;
            }

        }

        $products .= ']';

        $curlData.='"email": "'.$USER->getEmail().'",
                "addr_type": "email",';


        $curlData.='"transaction_sum": '. number_format($basket->getPrice(), 1, '.', '').',
                "items": '.$products.',
                "event_type":3
        }]';

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://ssec.sendsay.ru/general/ssec/v100/json/relod',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $curlData,
            CURLOPT_HTTPHEADER => array(
                'Authorization: sendsay apikey=18Wz7MBu1fLT_L1VYUp9xhKpbv-5RpEn_o0kIlnD8VMHd5d26xA',
                'Content-Type: application/json'
            ),
        ));

        $output = curl_exec($ch);
        curl_close($ch);
    }
}


AddEventHandler("sale", "OnSaleStatusOrderChange", "sendOrderStatusToSendSay");
function sendOrderStatusToSendSay($order)
{

    if(!empty($order)) {
        $orderSendSayStatus = getSendSayOrderStatusByBitrixStatusId($order->getField("STATUS_ID"));
        if(!empty($orderSendSayStatus)) {

            $curlData = '[{';

            $userInfo = CUser::GetByID($order->getUserId());
            if ($arUser = $userInfo->Fetch()) {
                $curlData .= '"email": "' . $arUser['EMAIL'] . '",
                "addr_type": "email",';
            }

            $curlData .= '"transaction_id": "ro' . $order->getId() . '",
                "transaction_status": '.$orderSendSayStatus.',
                "update": 1,
                "transaction_dt": "' . $order->getDateInsert()->format('Y-m-d H:i:s') . '",
                "transaction_sum": ' . number_format($order->getPrice(), 1, '.', '') . ',
                "event_type":1
            }]';


            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => 'https://ssec.sendsay.ru/general/ssec/v100/json/relod',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $curlData,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: sendsay apikey=18Wz7MBu1fLT_L1VYUp9xhKpbv-5RpEn_o0kIlnD8VMHd5d26xA',
                    'Content-Type: application/json'
                ),
            ));

            $output = curl_exec($ch);
            curl_close($ch);
        }
    }
}

function getSendSayOrderStatusByBitrixStatusId($bitrixOrderStatusId){
    $result = 0;
    switch ($bitrixOrderStatusId) {
        case 'N':
            $result = 1;
            break;
        case 'P':
            $result = 2;
            break;
        case 'DA':
            $result = 3;
            break;
        case 'DB':
        case 'DC':
        case 'DF':
            $result = 7;
            break;
        case 'DS':
            $result = 6;
            break;
        case 'DR':
            $result = 8;
            break;
        case 'F':
            $result = 9;
            break;
        case 'HH':
            $result = 11;
            break;
    }

    return $result;
}

use Bitrix\Main\Loader;

/** Настройки — ваш артикул */
const REL_UNIQ_PROP_ID   = 260;              // ID свойства
const REL_UNIQ_PROP_CODE = 'CML2_ARTICLE';   // символьный код свойства

// Регистрируем обработчики (ядро: модуль "iblock")
AddEventHandler('iblock', 'OnBeforeIBlockElementAdd',    'relodUniqueArticle_OnAdd');
AddEventHandler('iblock', 'OnBeforeIBlockElementUpdate', 'relodUniqueArticle_OnUpdate');

/**
 * Общая проверка: есть ли элемент с таким же артикулом.
 * Возвращает true, если найден дубль.
 */
function relodUniqueArticle_hasDuplicate(int $iblockId, int $selfId, string $article): bool
{
    if ($article === '') { return false; }
    if (!Loader::includeModule('iblock')) { return false; }

    // Ищем в пределах того же инфоблока элемент с тем же значением свойства
    $filter = [
        'IBLOCK_ID'           => $iblockId,
        'CHECK_PERMISSIONS'   => 'N',
        'PROPERTY_'.REL_UNIQ_PROP_CODE => $article, // можно заменить на 'PROPERTY_'.REL_UNIQ_PROP_ID
    ];
    if ($selfId > 0) {
        $filter['!ID'] = $selfId;
    }

    $res = CIBlockElement::GetList([], $filter, false, ['nTopCount' => 1], ['ID']);
    return (bool)$res->Fetch();
}

/** Достаём значение артикула из массива PROPERTY_VALUES в самых частых форматах */
function relodUniqueArticle_extract($propValues): string
{
    $take = null;

    // Приоритет: по ID, затем по CODE
    if (is_array($propValues)) {
        $take = $propValues[REL_UNIQ_PROP_ID]   ?? ($propValues[REL_UNIQ_PROP_CODE] ?? null);
    } else {
        $take = $propValues;
    }

    // Нормализация типичных структур (['n'=>['VALUE'=>'...']])
    if (is_array($take)) {
        foreach ($take as $v) {
            if (is_array($v) && array_key_exists('VALUE', $v)) {
                $val = trim((string)$v['VALUE']);
                if ($val !== '') return $val;
            } else {
                $val = trim((string)$v);
                if ($val !== '') return $val;
            }
        }
        return '';
    }

    return trim((string)$take);
}

/** ADD */
function relodUniqueArticle_OnAdd(array &$arFields)
{
    if (!Loader::includeModule('iblock')) { return true; }

    $iblockId = (int)($arFields['IBLOCK_ID'] ?? 0);
    $article  = relodUniqueArticle_extract($arFields['PROPERTY_VALUES'] ?? null);

    if ($iblockId > 0 && $article !== '' && relodUniqueArticle_hasDuplicate($iblockId, 0, $article)) {
        global $APPLICATION;
        $APPLICATION->throwException('Артикул "'.$article.'" уже используется другим товаром. Значение должно быть уникальным.');
        return false; // прерываем сохранение
    }
    return true;
}

/** UPDATE (когда свойство меняют в форме элемента и оно приходит в PROPERTY_VALUES) */
function relodUniqueArticle_OnUpdate(array &$arFields)
{
    if (!Loader::includeModule('iblock')) { return true; }

    $iblockId = (int)($arFields['IBLOCK_ID'] ?? 0);
    $selfId   = (int)($arFields['ID'] ?? 0);
    $article  = relodUniqueArticle_extract($arFields['PROPERTY_VALUES'] ?? null);

    // Если артикул в этом обновлении не передавали — не проверяем
    if ($iblockId > 0 && $selfId > 0 && $article !== '' && relodUniqueArticle_hasDuplicate($iblockId, $selfId, $article)) {
        global $APPLICATION;
        $APPLICATION->throwException('Артикул "'.$article.'" уже используется другим товаром. Значение должно быть уникальным.');
        return false; // прерываем сохранение
    }
    return true;
}


use Bitrix\Main\EventManager;

EventManager::getInstance()->addEventHandler('main', 'OnEpilog', static function () {
    if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) { return; }

    $self = $_SERVER['PHP_SELF'] ?? '';
    if (strpos($self, '/bitrix/admin/sale_order_view.php') === false
        && strpos($self, '/bitrix/admin/sale_order_detail.php') === false) { return; }

    if (isset($_REQUEST['table_id']) && $_REQUEST['table_id'] === 'table_order_history') { return; }

    $orderId = (int)($_REQUEST['ID'] ?? 0);
    if ($orderId <= 0) { return; }

    if (!\Bitrix\Main\Loader::includeModule('catapulto.delivery')) { return; }

    // Берём и службу из OrdersTable.OPERATOR
    $row = \Ipol\Catapulto\OrdersTable::getList([
        'select' => ['TRACKING_NUMBER','TRACKING_LINK','NUMBER','OPERATOR'],
        'filter' => ['=BITRIX_ID' => $orderId],
        'limit'  => 1,
    ])->fetch();
    if (!$row) { return; }

    $trkNumber = trim((string)$row['TRACKING_NUMBER']); // «внутренний» трек
    $trkLink   = trim((string)$row['TRACKING_LINK']);   // ссылка на catapulto.ru/track/...
    $catNum    = trim((string)$row['NUMBER']);          // Catapulto: CTP#########
    $operator  = mb_strtolower(trim((string)$row['OPERATOR'])); // cdek/cse/...

    // Показываем именно Catapulto-номер как «треκ»
    $displayTrack = ($catNum !== '') ? $catNum : $trkNumber;
    if ($displayTrack === '' && $trkLink === '') { return; }

    // Маппинг OPERATOR -> подпись
    $opTitle = [
        'cdek'           => 'CDEK',
        'cse'            => 'Курьер Сервис Экспресс (CSE/KCE)',
        'kse'            => 'Курьер Сервис Экспресс (CSE/KCE)',
        'kce'            => 'Курьер Сервис Экспресс (CSE/KCE)',
        'dellin'         => 'Деловые Линии',
        'delov'          => 'Деловые Линии',
        'dpd'            => 'DPD',
        'pochta'         => 'Почта России',
        'russianpost'    => 'Почта России',
        'boxberry'       => 'Boxberry',
        'yandex'         => 'Яндекс Доставка',
        'yango'          => 'Яндекс Доставка',
        'iml'            => 'IML',
        'sber'           => 'СберЛогистика',
        'sberlogistics'  => 'СберЛогистика',
    ];

    $courier = 'Не определено';
    if ($operator !== '' && isset($opTitle[$operator])) {
        $courier = $opTitle[$operator];
    } elseif ($operator !== '') {
        // неизвестный оператор — покажем как есть (красиво)
        $courier = mb_strtoupper($operator);
    } else {
        // РЕЗЕРВ: определение по формату номера (если OPERATOR пуст)
        $tr = $displayTrack;

        if ($tr !== '') {
            if (preg_match('/^[A-Z0-9]{3}-\d{9}$/i', $tr)) {
                $courier = 'Курьер Сервис Экспресс (CSE/KCE)';
            } elseif (preg_match('/^\d{2}-\d{11}$/', $tr)) {
                $courier = 'Деловые Линии';
            } elseif (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/i', $tr)) {
                $courier = 'CDEK (междунар., UPU)';
            } elseif (preg_match('/^\d{7,10}$/', $tr)) {
                $courier = 'CDEK (внутренний)';
            } elseif (preg_match('/^[0-9a-f]{32}-udp$/i', $tr)) {
                $courier = 'Яндекс Доставка (другой день)';
            } elseif (preg_match('/^[0-9a-f]{32}$/i', $tr)) {
                $courier = 'Яндекс Доставка (экспресс)';
            } elseif (preg_match('/^\d{14}$/', $tr)) {
                // Контрольная цифра Почты России
                $sumOdd = 0; $sumEven = 0;
                for ($i = 0; $i < 13; $i++) {
                    $d = (int)$tr[$i];
                    if ( (($i + 1) % 2) === 1 ) { $sumOdd += $d; } else { $sumEven += $d; }
                }
                $ctrl = (10 - (($sumOdd * 3 + $sumEven) % 10)) % 10;
                $courier = ($ctrl === (int)$tr[13]) ? 'Почта России' : 'DPD';
            }
        }
    }

    $hx = static function($s){
        $enc = defined('LANG_CHARSET') ? LANG_CHARSET : (defined('SITE_CHARSET') ? SITE_CHARSET : 'UTF-8');
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, $enc);
    };

    $trackLabel = ($displayTrack !== '') ? $hx($displayTrack) : '(нет номера)';
    $linkHtml   = ($trkLink !== '') ? '<a href="'.$hx($trkLink).'" target="_blank" rel="noopener">'.$trackLabel.'</a>' : $trackLabel;
    $suffixCat  = ($catNum !== '' && $catNum !== $displayTrack) ? ' &nbsp;&nbsp;<span style="color:#7a7a7a;">(Catapulto: '.$hx($catNum).')</span>' : '';

    echo '<div id="catapulto-admin-track-panel" style="margin:12px 0 0; padding:10px 12px; background:#fffbe6; border:1px solid #ffe58f; border-radius:6px; font-size:13px; line-height:1.5;">'
        . '<b>Служба доставки:</b> '.$hx($courier).' &nbsp;&nbsp; <b>Трек-номер:</b> '.$linkHtml.$suffixCat
        . '</div>';

    // Поднимаем панель под серую админ-панель
    echo '<script>(function(){var p=document.getElementById("catapulto-admin-track-panel");if(!p)return;var t=document.querySelector(".adm-detail-toolbar");if(t&&t.parentNode){t.parentNode.insertBefore(p,t.nextSibling);}})();</script>';
});


use Bitrix\Main;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\IO\Directory;

// ===== НАСТРОЙКА ЛОГА =====
$logDir  = $_SERVER['DOCUMENT_ROOT'] . '/upload/relod';
if (!Directory::isDirectoryExists($logDir)) {
    Directory::createDirectory($logDir);
}
define('RELOD_CANCEL_LOG', $logDir . '/relod_cancel_status.log');

// Пинг – проверяем, что init.php точно подключился
Debug::writeToFile(['time'=>date('c'), 'hit'=>($_SERVER['REQUEST_URI'] ?? '')], 'init_loaded', RELOD_CANCEL_LOG);

// ===== ОБРАБОТЧИКИ ОТМЕНЫ =====
Loader::includeModule('sale');

class RelodCancelStatus
{
    public static function onCancelD7(Main\Event $event): void
    {
        $order = $event->getParameter('ENTITY');
        if (!($order instanceof \Bitrix\Sale\Order)) return;
        if ($order->getField('CANCELED') !== 'Y') return;
        self::apply((int)$order->getId(), 'd7');
    }

    public static function onCancelLegacy($orderId, $value, $description = ''): void
    {
        if ($value !== 'Y') return;
        self::apply((int)$orderId, 'legacy');
    }

    private static function apply(int $orderId, string $source): void
    {
        try {
            $order = \Bitrix\Sale\Order::load($orderId);
            if (!$order) {
                Debug::writeToFile(['orderId'=>$orderId, 'source'=>$source], 'order_not_found', RELOD_CANCEL_LOG);
                return;
            }

            $currentUserId = (is_object($GLOBALS['USER']) && $GLOBALS['USER']->IsAuthorized()) ? (int)$GLOBALS['USER']->GetID() : 0;
            $isAdmin       = (defined('ADMIN_SECTION') && ADMIN_SECTION === true);

            // По умолчанию считаем служебную отмену (из админки) → CN
            $statusId = 'CN';
            // Если не админка и отменяет владелец заказа → CC
            if (!$isAdmin && $currentUserId > 0 && $currentUserId === (int)$order->getUserId()) {
                $statusId = 'CC';
            }

            $oldStatus = (string)$order->getField('STATUS_ID');
            if ($oldStatus === $statusId) {
                Debug::writeToFile(compact('orderId','source','oldStatus','statusId'), 'already_set', RELOD_CANCEL_LOG);
                return;
            }

            // Меняем статус штатным API и логируем результат
            $res = \CSaleOrder::StatusOrder($orderId, $statusId);
            Debug::writeToFile([
                'orderId'=>$orderId,
                'source'=>$source,
                'oldStatus'=>$oldStatus,
                'newStatus'=>$statusId,
                'result'=>$res
            ], 'status_changed_via_StatusOrder', RELOD_CANCEL_LOG);
        } catch (\Throwable $e) {
            Debug::writeToFile(['orderId'=>$orderId, 'source'=>$source, 'exception'=>$e->getMessage()], 'exception', RELOD_CANCEL_LOG);
        }
    }
}

// Подписки (оба варианта)
Main\EventManager::getInstance()->addEventHandler('sale', 'OnSaleOrderCanceled', ['RelodCancelStatus', 'onCancelD7']);
AddEventHandler('sale', 'OnSaleCancelOrder', ['RelodCancelStatus', 'onCancelLegacy']);