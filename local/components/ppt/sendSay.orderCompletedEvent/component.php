<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Loader;
use Bitrix\Sale;

global $USER;

$arResult = [
    'ORDER_ID'           => 0,
    'TRANSACTION_ID'     => '',
    'TRANSACTION_DT'     => '',
    'TRANSACTION_SUM'    => 0,
    'TRANSACTION_STATUS' => 1,
    'ITEMS'              => [],
    'SENDSAY_META'       => [],
    'SENDSAY_CAN_SEND'   => false,
    'SENDSAY_EVENT'      => [],
];

$orderId = isset($arParams['ORDER_ID']) ? (int)$arParams['ORDER_ID'] : 0;
if ($orderId <= 0) {
    $this->IncludeComponentTemplate();
    return;
}

if (!Loader::includeModule('sale')) {
    $this->IncludeComponentTemplate();
    return;
}

// Загружаем заказ
$order = Sale\Order::load($orderId);
if (!$order) {
    $this->IncludeComponentTemplate();
    return;
}

$arResult['ORDER_ID']           = $order->getId();
$arResult['TRANSACTION_ID']     = 'ro' . $order->getId();
$arResult['TRANSACTION_DT']     = $order->getDateInsert()->format('Y-m-d H:i:s');
$arResult['TRANSACTION_SUM']    = (float)number_format($order->getPrice(), 2, '.', '');
// Статус 1 — «заказ оформлен»
$arResult['TRANSACTION_STATUS'] = 1;

// Собираем товары заказа
$basket = $order->getBasket();
if ($basket && count($basket->getQuantityList()) > 0) {
    $iblockLoaded = Loader::includeModule('iblock');

    foreach ($basket as $basketItem) {
        $productId = (int)$basketItem->getProductId();
        if ($productId <= 0) {
            continue;
        }

        $brandName   = '';
        $sectionName = '';
        $sectionId   = 0;

        if ($iblockLoaded) {
            $arFilter = [
                'IBLOCK_ID'   => 16,
                'ACTIVE_DATE' => 'Y',
                'ACTIVE'      => 'Y',
                'ID'          => $productId,
            ];
            $res = \CIBlockElement::GetList([], $arFilter, false, false, ['PROPERTY_BRAND', 'IBLOCK_SECTION_ID']);
            if ($res && ($ob = $res->GetNextElement())) {
                $productFields = $ob->GetFields();

                // Бренд
                if (!empty($productFields['PROPERTY_BRAND_VALUE'])) {
                    $arFilter2 = [
                        'IBLOCK_ID'   => 15,
                        'ACTIVE_DATE' => 'Y',
                        'ACTIVE'      => 'Y',
                        'ID'          => $productFields['PROPERTY_BRAND_VALUE'],
                    ];
                    $res2 = \CIBlockElement::GetList([], $arFilter2, false, false, ['NAME']);
                    if ($res2 && ($ob2 = $res2->GetNextElement())) {
                        $brandFields = $ob2->GetFields();
                        if (!empty($brandFields['NAME'])) {
                            $brandName = $brandFields['NAME'];
                        }
                    }
                }

                // Категория
                if (!empty($productFields['IBLOCK_SECTION_ID'])) {
                    $sectionId = (int)$productFields['IBLOCK_SECTION_ID'];

                    $arSectionFilter = [
                        'IBLOCK_ID'     => 16,
                        'GLOBAL_ACTIVE' => 'Y',
                        'ID'            => $sectionId,
                    ];
                    $dbSectionsList  = \CIBlockSection::GetList([], $arSectionFilter, false, ['NAME']);
                    if ($dbSectionsList && ($arSectionResult = $dbSectionsList->GetNext())) {
                        if (!empty($arSectionResult['NAME'])) {
                            $sectionName = $arSectionResult['NAME'];
                        }
                    }
                }
            }
        }

        $arResult['ITEMS'][] = [
            'id'          => (string)$productId,
            'name'        => (string)$basketItem->getField('NAME'),
            'price'       => (float)number_format($basketItem->getPrice(), 2, '.', ''),
            'qnt'         => (float)$basketItem->getQuantity(),
            'vendor'      => (string)$brandName,
            'category_id' => (int)$sectionId,
            'category'    => (string)$sectionName,
        ];
    }
}

// E-mail покупателя
$email = '';
$userId = (int)$order->getUserId();
if ($userId > 0) {
    $rsUser = \CUser::GetByID($userId);
    if ($rsUser && ($arUser = $rsUser->Fetch()) && !empty($arUser['EMAIL'])) {
        $email = $arUser['EMAIL'];
    }
}

// Метаданные для Sendsay (3-й аргумент ssecEvent)
$meta = [];
if ($email !== '') {
    $meta['email'] = $email;
}

// Достаём из сессии информацию о письме (её кладёт relodStoreSendsaySource)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$issueName = $_SESSION['REL_SENDSAY_ISSUE_NAME'] ?? null;
$issueId   = $_SESSION['REL_SENDSAY_ISSUE_ID'] ?? null;
$letterId  = $_SESSION['REL_SENDSAY_LETTER_ID'] ?? null;
$memberId  = $_SESSION['REL_SENDSAY_MEMBER_ID'] ?? null;
$rawQuery  = $_SESSION['REL_SENDSAY_QUERY'] ?? null;
$rawPlc    = $_SESSION['REL_SENDSAY_PLC_RAW'] ?? null;

// Если нужно, пробрасываем issue/letter/member в meta
if (!empty($issueId)) {
    $meta['issue'] = (int)$issueId;
}
if (!empty($letterId)) {
    $meta['letter'] = (int)$letterId;
}
if (!empty($memberId)) {
    $meta['member'] = (int)$memberId;
}

$arResult['SENDSAY_META'] = $meta;

// Можно отправлять событие только если есть товары и email
$arResult['SENDSAY_CAN_SEND'] = (!empty($arResult['ITEMS']) && $email !== '');

// Готовим объект события целиком
$event = [
    'transaction_id'     => $arResult['TRANSACTION_ID'],
    'transaction_dt'     => $arResult['TRANSACTION_DT'],
    'transaction_sum'    => $arResult['TRANSACTION_SUM'],
    'transaction_status' => $arResult['TRANSACTION_STATUS'],
    'items'              => $arResult['ITEMS'],
];

// Доп. атрибуты cp1..cp5
if (!empty($issueName)) {
    $event['cp1'] = (string)$issueName;
} elseif (!empty($rawQuery)) {
    $event['cp1'] = (string)$rawQuery;
}
if (!empty($issueId)) {
    $event['cp2'] = (string)$issueId;
}
if (!empty($letterId)) {
    $event['cp3'] = (string)$letterId;
}
if (!empty($memberId)) {
    $event['cp4'] = (string)$memberId;
}
if (!empty($rawPlc)) {
    $event['cp5'] = (string)$rawPlc;
}

$arResult['SENDSAY_EVENT'] = $event;

$this->IncludeComponentTemplate();
