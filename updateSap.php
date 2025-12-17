<?php
// На PHP 8.2 у старого PHPExcel много E_DEPRECATED и динамических свойств.
// Чтобы не падало при чтении xlsx — приглушим уведомления.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 1);

$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath(dirname(__FILE__) . '/../../'));
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];
define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
global $USER;
if (!is_object($USER)) { $USER = new CUser(); }
$modifiedBy = (method_exists($USER,'GetID') && (int)$USER->GetID() > 0) ? (int)$USER->GetID() : 1;

$updatedCount = 0; // у тебя инкремент идёт, но не было инициализации

use Bitrix\Catalog\VatTable;
use Bitrix\Catalog\Model\Product as CatalogProductModel;
use Bitrix\Catalog\StoreProductTable;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;

// Обновляет остаток на складе: если запись есть — update, если нет — add
function setStoreAmount(int $productId, int $storeId, $amount): void
{
    $row = \Bitrix\Catalog\StoreProductTable::getRow([
        'filter' => ['=PRODUCT_ID' => $productId, '=STORE_ID' => $storeId],
        'select' => ['ID'],
    ]);

    if ($row && (int)$row['ID'] > 0) {
        $res = \Bitrix\Catalog\StoreProductTable::update((int)$row['ID'], [
            'AMOUNT' => (float)$amount,
        ]);
    } else {
        $res = \Bitrix\Catalog\StoreProductTable::add([
            'PRODUCT_ID' => $productId,
            'STORE_ID'   => $storeId,
            'AMOUNT'     => (float)$amount,
        ]);
    }

    if (!$res->isSuccess()) {
        echo "ERROR: StoreProductTable (PRODUCT_ID={$productId}, STORE_ID={$storeId}): "
           . implode('; ', $res->getErrorMessages()) . "\n";
    }
}

function getVatIdByRate(float $rate): int
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $res = VatTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'select' => ['ID','RATE']
        ]);
        while ($r = $res->fetch()) {
            $map[(float)$r['RATE']] = (int)$r['ID'];
        }
    }
    return $map[$rate] ?? 0; // 0 = «Без НДС», если ставка не найдена
}

function log2($msg) {
    $line = date('Y-m-d H:i:s ') . $msg . PHP_EOL;
    echo $line;
    file_put_contents("logSap.txt", "\xEF\xBB\xBF".$line, FILE_APPEND);
}

// slug для ItemGroup: пробелы И запятые -> "_", всё остальное без изменений; регистр -> нижний
function slug_keep_spaces_only(string $s): string {
    $s = trim($s);
    $s = mb_strtolower($s);
    // заменяем любые последовательности из пробелов и/или запятых на один "_"
    // примеры: "A, B" -> "a_b", "A ,B" -> "a_b", "A ,  B" -> "a_b", "A   B" -> "a_b"
    return preg_replace('/[,\s]+/u', '_', $s);
}


/**
 * Возвращает data class HL-справочника для свойства-справочника (USER_TYPE=directory)
 * по коду свойства и ID инфоблока.
 */
function getDirectoryDataClassByProperty(int $iblockId, string $propertyCode)
{
    $prop = CIBlockProperty::GetList([], ['IBLOCK_ID'=>$iblockId, 'CODE'=>$propertyCode])->Fetch();
    if (!$prop || $prop['USER_TYPE'] !== 'directory') {
        log2("ERROR: Свойство {$propertyCode} не является directory или не найдено");
        return null;
    }
    $table = $prop['USER_TYPE_SETTINGS']['TABLE_NAME'] ?? null;
    if (!$table) {
        log2("ERROR: У свойства {$propertyCode} нет TABLE_NAME");
        return null;
    }
    $hl = HLBT::getList(['filter' => ['=TABLE_NAME' => $table]])->fetch();
    if (!$hl) {
        log2("ERROR: HL-блок для {$propertyCode} с таблицей {$table} не найден");
        return null;
    }
    $entity = HLBT::compileEntity($hl);
    return $entity->getDataClass();
}

/**
 * Гарантирует наличие элемента в HL-справочнике свойства (directory) с данным UF_XML_ID.
 * Если элемента нет — создаёт. Возвращает UF_XML_ID (строку), которую и надо передавать в SetPropertyValuesEx.
 */
function ensureDirectoryValue(int $iblockId, string $propertyCode, string $humanName, string $xmlId)
{
    $dataClass = getDirectoryDataClassByProperty($iblockId, $propertyCode);
    if (!$dataClass) return null;

    $row = $dataClass::getRow([
        'filter' => ['=UF_XML_ID' => $xmlId],
        'select' => ['ID', 'UF_XML_ID', 'UF_NAME']
    ]);

    if (!$row) {
        $add = $dataClass::add([
            'UF_NAME'   => $humanName,
            'UF_XML_ID' => $xmlId,
        ]);
        if ($add->isSuccess()) {
            log2("HL {$propertyCode}: создан элемент '{$humanName}' (UF_XML_ID={$xmlId})");
        } else {
            log2("ERROR: HL {$propertyCode} add: ".implode('; ', $add->getErrorMessages()));
            return null;
        }
    }
    return $xmlId; // directory принимает именно UF_XML_ID
}

/**
 * Достаём описание свойства по коду.
 */
function getIblockPropertyByCode(int $iblockId, string $code)
{
    return CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
}

/**
 * Гарантирует наличие значения СПИСКА (ENUM) у свойства.
 * Если такого значения нет — создаёт его. Возвращает ID enum-значения.
 */
function ensureEnumValue(int $iblockId, string $propertyCode, string $humanName, string $xmlId)
{
    $prop = getIblockPropertyByCode($iblockId, $propertyCode);
    if (!$prop || $prop['PROPERTY_TYPE'] !== 'L') {
        log2("ERROR: Свойство {$propertyCode} не список (L) или не найдено");
        return null;
    }

    // Ищем по XML_ID
    $enum = CIBlockPropertyEnum::GetList(
        ['SORT' => 'ASC'],
        ['IBLOCK_ID' => $iblockId, 'PROPERTY_ID' => $prop['ID'], 'XML_ID' => $xmlId]
    )->Fetch();

    if ($enum) {
        return (int)$enum['ID'];
    }

    // Создаём новое значение списка
    $enumId = (new CIBlockPropertyEnum)->Add([
        'PROPERTY_ID' => $prop['ID'],
        'VALUE'       => $humanName,  // как в Excel
        'XML_ID'      => $xmlId,      // наш slug (строчные + _)
        'DEF'         => 'N',
        'SORT'        => 500,
    ]);

    if ($enumId) {
        log2("ENUM {$propertyCode}: создан '{$humanName}' (XML_ID={$xmlId}, ID={$enumId})");
        return (int)$enumId;
    }

    log2("ERROR: ENUM {$propertyCode} add failed (value='{$humanName}', xml_id='{$xmlId}')");
    return null;
}

/**
 * Универсально готовим значение свойства:
 * - если directory → возвращаем UF_XML_ID,
 * - если список (L) → возвращаем ID enum.
 * Возвращает ['mode'=>'directory'|'enum', 'value'=>mixed] или null.
 */
function ensurePropertyValueAuto(int $iblockId, string $propertyCode, string $humanName, string $slug)
{
    $prop = getIblockPropertyByCode($iblockId, $propertyCode);
    if (!$prop) {
        log2("ERROR: Свойство {$propertyCode} не найдено");
        return null;
    }

    // directory (HL-справочник) → возвращаем UF_XML_ID
    if (($prop['USER_TYPE'] ?? '') === 'directory') {
        $xml = ensureDirectoryValue($iblockId, $propertyCode, $humanName, $slug);
        return $xml !== null ? ['mode' => 'directory', 'value' => $xml] : null;
    }

    // список (ENUM) → возвращаем ID значения списка
    if (($prop['PROPERTY_TYPE'] ?? '') === 'L') {
        $enumId = ensureEnumValue($iblockId, $propertyCode, $humanName, $slug);
        return $enumId !== null ? ['mode' => 'enum', 'value' => $enumId] : null;
    }

    log2(
        "ERROR: Свойство {$propertyCode} ни directory, ни список " .
        "(type=" . ($prop['PROPERTY_TYPE'] ?? 'null') .
        ", user_type=" . ($prop['USER_TYPE'] ?? 'null') . ")"
    );
    return null;
}



@ini_set('memory_limit', '-1');
echo "Начало работы скрипта\r\n";
echo "=== DEBUG: старт скрипта ===\n";
echo "=== DEBUG: старт updateSap.php ===\r\n";
//Ставим флаг что обновления остатков
if(\Bitrix\Main\Loader::includeModule('askaron.settings')){

    $arUpdateFields = ["UF_UPDATE_STOCK" => 1];

    $obSettings = new \CAskaronSettings;
    $updateConf = $obSettings->Update($arUpdateFields);

    if(!$updateConf){
        echo $obSettings->LAST_ERROR.PHP_EOL;
    } else {
        echo '(lock) Заблокирована очередь отправки изменений в SAP '.date('d.m.Y H:i:s').PHP_EOL;
    }
    unset($arUpdateFields, $obSettings, $updateConf);
}
while (ob_get_level()) {
    ob_end_flush();
}
require_once '../../classes/PHPExcel/IOFactory.php';  //Подключаем библиотеку.
set_time_limit(0); // Убираем ограничение по времени работы скрипта
ini_set('max_execution_time', 0); // Убираем ограничение по времени работы скрипта
ignore_user_abort(true); // При отключении клиента, скрипт продолжает работу.
$time_start_full_script = microtime_float();

$arQuestionedISBNs = [
    "x0000328",
    "x00-00000124",
    "x00-00000143",
    "x00-00000121",
    "x00-00000122",
    "x00-00000123",
    "x00-00000142",
];

class chunkReadFilter implements PHPExcel_Reader_IReadFilter
{
    private $_startRow = 0;
    private $_endRow = 0;

    public function setRows($startRow, $chunkSize) {
        $this->_startRow    = $startRow;
        $this->_endRow      = $startRow + $chunkSize;
    }

    public function readCell($column, $row, $worksheetName = '') {
        if (($row == 1) || ($row >= $this->_startRow && $row < $this->_endRow)) {
            return true;
        }
        return false;
    }
}


// Запускаем скрипт.
CModule::IncludeModule('catalog');
CModule::IncludeModule('iblock');
CModule::IncludeModule('highloadblock');


$entity_data_class = GetEntityDataClass(27);
$rsData = $entity_data_class::getList(array(
    'select' => array('*')
));
echo "Наполняем группы\r\n";
$arGroupHL = [];
while($el = $rsData->fetch()){
    $arGroupHL[str2url(trim($el['UF_NAME']))] = $el;
}
echo "DEBUG: выбрано групп из HL-блока: " . count($arGroupHL) . "\r\n";
echo "Читаем файл\r\n";
$intSKUIBlock = 3; // ID инфоблока предложений (должен быть торговым каталогом)
$arIblockFilter = [16];
$arCatalog = CCatalog::GetByID($intSKUIBlock);
if (!$arCatalog)
    return;
$intProductIBlock = $arCatalog['PRODUCT_IBLOCK_ID']; // ID инфоблока товаров
$export_file = '../../upload/stock_update/stock.xlsx';
// $export_file = __DIR__.'/stock.xlsx';
$src_file = '../../upload/stock_update/_stock.xlsx';
// $src_file = __DIR__.'/_stock.xlsx';
copy($export_file, $src_file);
if(file_exists("logSap_14.txt"))
    unlink("logSap_14.txt");
if(file_exists("out_14.txt"))
    unlink("out_14.txt");
foreach(range(13, 0) as $i) {
    if(file_exists("logSap_$i.txt"))
        rename("logSap_$i.txt", "logSap_" . ($i+1) . ".txt");
    if(file_exists("out_$i.txt"))
        rename("out_$i.txt", "out_" . ($i+1) . ".txt");
}
if(file_exists("logSap.txt"))
    rename("logSap.txt", "logSap_0.txt");
if(file_exists("out.txt"))
    rename("out.txt", "out_0.txt");

$intSKUProperty = $arCatalog['SKU_PROPERTY_ID']; // ID свойства в инфоблоке предложений типа "Привязка к товарам (SKU)"

$PRICE_TYPE_ID = 1; // ID - базовой цены, настраивается в админке битрикс;

$startRow = 2;
$inputFileType = 'Excel2007';
$objReader = PHPExcel_IOFactory::createReader($inputFileType);
$worksheetData = $objReader->listWorksheetInfo($src_file);
$totalRows = $worksheetData[0]['totalRows'];
settype($totalRows,'int');
$chunkSize = 1150;
$chunkFilter = new chunkReadFilter();
$vatIncluded = 'Y'; //Включен НДС в стоимость

echo "Начинаем чтение\r\n";
try {
    $iter = 0;
    while ($startRow <= $totalRows) {
        // if($iter == 1) break;
        echo "Читаем чанк\r\n";
        if ($startRow + $chunkSize > $totalRows) {
            $endRow = $totalRows;
            $chunkSize = $totalRows - $startRow + 1;
        }
        else
            $endRow = $startRow + $chunkSize;
        $chunkFilter->setRows($startRow, $chunkSize);
        $objReader->setReadFilter($chunkFilter);
        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($src_file);
        $arrFullProduct = [];
        $arrISBN = [];
        // Работа с данными
        $rowIterator = $objPHPExcel->getActiveSheet()->getRowIterator($startRow, $endRow+1);
        $i = 0;
        foreach ($rowIterator as $row) {
            $cellIterator = $row->getCellIterator();
            $col = ['idx' => $row->getRowIndex()];
            foreach ($cellIterator as $cell) {
                $col[$cell->getColumn()] = $cell->getCalculatedValue();
            }
            if($col['A']===null) continue;
            $arrFullProduct[] = $col;
        }
        $arISBNs = [];
        for ($stringInFile = 0; $stringInFile < count($arrFullProduct); $stringInFile++) {
//                $arrFullProduct[$stringInFile]['index'] = $stringInFile;
            if ($arrFullProduct[$stringInFile]["A"] != '') {
                $curIsbn = trim($arrFullProduct[$stringInFile]["A"]);
                $arISBNs[$stringInFile] = $curIsbn;
                $arrISBN[] = $curIsbn;
                $arrISBNOrigin[] = $curIsbn;
            }
        }

//            $arISBNs = array_column($arrFullProduct, "A", 'index');
        $arFound = [];

        $time_start = microtime_float();

        echo "Запрос к БД за товарами по ISBN\r\n";
        $arSelect = ["ID", "IBLOCK_ID", "NAME", "PROPERTY_CML2_ARTICLE"];
        $arFilter = ["=PROPERTY_CML2_ARTICLE" => $arrISBN, 'IBLOCK_ID' => $arIblockFilter];
        $result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
        $totalCount = $result->SelectedRowsCount();
		echo "DEBUG: найдено товаров по фильтру: $totalCount\r\n";
        $time_end = microtime_float();
        $time = $time_end - $time_start;
        echo "Запрос отработал, перебираем сами товары\r\n";
        $content = "Запрос на 1150 товаров к каталогу релод выполняется за $time секунд\r\n";
        file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $content, FILE_APPEND);

        unset($time_start, $time_end, $time, $content);

        $isDebugISBNsArrayPrinted = false;
        while ($ob = $result->GetNext()) {
        echo "DEBUG: начинаем обработку товара ID={$ob['ID']} (ISBN={$ob['PROPERTY_CML2_ARTICLE_VALUE']})\r\n";
        echo "DEBUG: объект товара:\n";
		print_r($ob);
		
            $PRODUCT_ID = intval($ob["ID"]); // id товара
            $PRODUCT_NAME = $ob["NAME"];
            $PRODUCT_ISBN = trim($ob["PROPERTY_CML2_ARTICLE_VALUE"]);

            if (!in_array($PRODUCT_ISBN, $arrISBNOrigin)) continue;

            $time_start = microtime_float();
            $isbn = $PRODUCT_ISBN;
            //Собственно поиск

            //Собственно поиск
            $result_index = array_filter($arrFullProduct, function ($innerArray) {
                global $isbn;
                return in_array($isbn, $innerArray);    //Поиск по всему массиву
                //return ($innerArray[0] == $needle); //Поиск по первому значению
            });

            //Результат
            $key_index2 = key($result_index);

            $isLoggingRequired = false;

            $key_index = array_search($isbn, $arISBNs);

            if(!in_array($PRODUCT_ISBN, $arFound)) {
                $arFound[] = $PRODUCT_ISBN;
            }
            else {
                $msg = "Элемент с ISBN " . $ob['PROPERTY_CML2_ARTICLE'] . " встречен в выборке из базы повторно \r\n";
                $msg .= "Объект БД: " . print_r($ob, true) . "\r\n";
                echo $msg;
                file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $msg, FILE_APPEND);
            }

            if($arrFullProduct[$key_index]['A']!==$PRODUCT_ISBN) {
                $ar = " (выше)";
                if(!$isDebugISBNsArrayPrinted) {
                    $isDebugISBNsArrayPrinted = true;
                    $ar = print_r($arISBNs, true);
                    $ar.= "\r\nЗапрос: " . print_r($arrISBN, true);
                }
                if($key_index===false)
                    $msg = "Новый поиск ошибся на ISBN " . $ob['PROPERTY_CML2_ARTICLE'] . ", предсказал false (".$arrFullProduct[0]['A'].") по массиву " . $ar ."\r\n";
                else
                    $msg = "Новый поиск ошибся на ISBN " . $ob['PROPERTY_CML2_ARTICLE'] . ", предсказал ".$key_index." (".$arrFullProduct[$key_index]['A'].") по массиву " . $ar ."\r\n";
                echo $msg;
                file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $msg, FILE_APPEND);
            }
            if($arrFullProduct[$key_index2]['A']!==$PRODUCT_ISBN) {
                $ar = " (выше)";
                if(!$isDebugISBNsArrayPrinted) {
                    $isDebugISBNsArrayPrinted = true;
                    $ar = print_r($arISBNs, true);
                    $ar.= "\r\nЗапрос: " . print_r($arrISBN, true);
                }
                if($key_index2===false)
                    $msg = "Старый поиск ошибся на ISBN " . $ob['PROPERTY_CML2_ARTICLE'] . ", предсказал false (".$arrFullProduct[0]['A'].") по массиву " . $ar ."\r\n";
                else
                    $msg = "Старый поиск ошибся на ISBN " . $ob['PROPERTY_CML2_ARTICLE'] . ", предсказал " . $key_index2 . " (".$arrFullProduct[$key_index2]['A'].") по массиву " . $ar ."\r\n";

                echo $msg;
                file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $msg, FILE_APPEND);
            }

            if ($key_index === false) {
                $ar = " (выше)";
                if(!$isDebugISBNsArrayPrinted) {
                    $isDebugISBNsArrayPrinted = true;
                    $ar = print_r($arISBNs, true);
                    $ar.= "\r\nЗапрос: " . print_r($arrISBN, true);
                }
                $content = "Не найдено ничего по " . $isbn . ", итерация " . $iter . ", старый индекс " . $key_index2 . "\r\n";
                $content .= "Объект БД: " . print_r($ob, true) . "\r\n";
                $content .= "Поиск по: " . $ar . "\r\n";
                $content .= "Поскольку ничего не найдено, пропускаем цикл \r\n";

                echo $content;
                file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $content, FILE_APPEND);
                continue;
            }
            if (in_array($isbn, $arQuestionedISBNs))
                $isLoggingRequired = true;

            $quantity      = intval(trim($arrFullProduct[$key_index]["F"])); // склад 2 (например, Северянин)
$quantity_shop = intval(trim($arrFullProduct[$key_index]["E"])); // склад 3 (магазин)
$quantity_ptv  = intval(trim($arrFullProduct[$key_index]["J"]));

$time_start = microtime_float();

/* --- ЧИТАЕМ ВСЕ ПОЛЯ ИЗ EXCEL ОДИН РАЗ --- */
$price        = floatval(str_replace(',', '.', trim($arrFullProduct[$key_index]["C"])));
$priceWithNDS = floatval(str_replace(',', '.', trim($arrFullProduct[$key_index]["D"])));
$deactivate   = trim($arrFullProduct[$key_index]["G"]);      // G = Deactivate
$vatRate      = (int)trim($arrFullProduct[$key_index]["B"]); // B = НДС %
$nds          = ($vatRate === 10) ? getVatIdByRate(10) : getVatIdByRate(20);

$groupName    = trim((string)$arrFullProduct[$key_index]["H"]); // «как в Excel»
$groupSlug    = slug_keep_spaces_only($groupName);              // только пробелы -> "_"
if ($groupName === 'OUP Academic $') {                          // оставляем ваш спец-кейс
    $groupSlug = 'oup_academic_dollar';
}

$originName   = trim((string)$arrFullProduct[$key_index]["I"]); // «как в Excel»
$originSlug   = str2url($originName);                           // символьный код

$sale         = trim((string)$arrFullProduct[$key_index]["K"]); // K = sale
$ptvCount     = (int)$quantity_ptv;                             // J = PTV_qty
/* ------------------------------------------ */


if ($nds === 0) {
    log2("WARN VAT: ставка {$vatRate}% не найдена для ID={$PRODUCT_ID}");
}
log2("READ id={$PRODUCT_ID} isbn={$PRODUCT_ISBN} qty={$quantity}/{$quantity_shop} ptv={$ptvCount} deact={$deactivate} sale={$sale}");
/* ------------------------------------------ */



// === Новая логика по свойству ID=677 ===
// 1) Читаем значение свойства ID=677 у текущего товара
$prop677Raw = '';
$pr677 = CIBlockElement::GetProperty($ob['IBLOCK_ID'], $PRODUCT_ID, ['sort' => 'asc'], ['ID' => 677]);
if ($r677 = $pr677->Fetch()) {
    // Берём текст либо из VALUE_ENUM (если список), либо из VALUE (строка/чекбокс)
    $prop677Raw = (string)($r677['VALUE_ENUM'] ?: $r677['VALUE'] ?: '');
}

// 2) Проверяем, что в свойстве именно "y" (без учёта регистра)
$flag677Y = (strcasecmp(trim($prop677Raw), 'y') === 0);

// 3) Все количества из Excel нулевые?
$allQtyZero = ($quantity <= 0 && $quantity_shop <= 0 && $quantity_ptv <= 0);

// 4) Признак "виртуального режима" по вашему правилу
$isDigital = ($flag677Y && $allQtyZero);

// 5) Считаем итоговое количество
if ($isDigital) {
    // Склады и так нули (из Excel), фиксируем общее количество 999
    // (на случай мусора из Excel принудительно обнулим переменные)
    $quantity      = 0;
    $quantity_shop = 0;
    $quantity_ptv  = 0;
    $totalQuantity = 999;
    echo "DEBUG: товар ID={$PRODUCT_ID} — свойство 677='y' и все qty=0, ставим totalQuantity=999\r\n";
} else {
    $totalQuantity = $quantity + $quantity_shop;
}

// Цифровой товар: включаем "Есть в наличии" (ID=121, enum=408) и ATR_DIGITAL='y' (ID=673)
if ($isDigital && (int)$totalQuantity === 999) {
    CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, $ob['IBLOCK_ID'], [
        121            => 408,   // "Есть в наличии"
        'ATR_DIGITAL'  => 'y',   // ID=673 — свойство "Цифровой товар"
    ]);
    echo "DEBUG: [ID=121] В наличии => 'Есть в наличии' (enum=408) и ATR_DIGITAL='y' для товара ID={$PRODUCT_ID}\r\n";
}



            $time_end = microtime_float();
$time = $time_end - $time_start;

if ($isLoggingRequired) {
    $content  = "Подготовка переменных для товара с $isbn происходит за $time секунд\r\n";
    $content .= "Переменные товара: " . print_r($arrFullProduct[$key_index], true) . "\r\n";
    $content .= "Количество: " . print_r($arrFullProduct[$key_index]["F"], true) . ", " . $quantity . "\r\n";
    $content .= "Количество магазин: " . print_r($arrFullProduct[$key_index]["E"], true) . ", " . $quantity_shop . "\r\n";
    $content .= "Количество 2: " . print_r($arrFullProduct[$key_index]["J"], true) . ", " . $quantity_ptv . "\r\n";
    $content .= "Цена: " . print_r($arrFullProduct[$key_index]["C"], true) . ", " . $price . "\r\n";
    $content .= "Цена с НДС: " . print_r($arrFullProduct[$key_index]["D"], true) . ", " . $priceWithNDS . "\r\n";
    $content .= "Деактивация: " . print_r($arrFullProduct[$key_index]["G"], true) . ", " . $deactivate . "\r\n";
    $content .= "НДС: " . print_r($arrFullProduct[$key_index]["B"], true) . ", " . $nds . "\r\n";
    $content .= "Группа: " . print_r($arrFullProduct[$key_index]["H"], true) . ", " . $group . "\r\n";
    $content .= "Источник: " . print_r($arrFullProduct[$key_index]["I"], true) . ", " . $origin . "\r\n";
    $content .= "Скидка: " . print_r($arrFullProduct[$key_index]["K"], true) . ", " . $sale . "\r\n";
    $content .= "Индекс: " . $key_index . ", итерация " . $iter . "\r\n";
    file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $content, FILE_APPEND);
}
unset($time_start, $time_end, $time, $content);


//                echo "Обновляем товар с ISBN = " . $PRODUCT_ISBN;
//                echo "<br /><br />";

            $arFields = [
    "PRODUCT_ID" => $PRODUCT_ID,
    "CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
    "PRICE" => $priceWithNDS,
    "CURRENCY" => "RUB"
];
$time_start = microtime_float();
$res = CPrice::GetList([], ["PRODUCT_ID" => $PRODUCT_ID, "CATALOG_GROUP_ID" => $PRICE_TYPE_ID]);

global $APPLICATION; // нужен для ошибок

if ($arr = $res->Fetch()) {
    echo "DEBUG: CPrice::Update({$arr["ID"]}, "; print_r($arFields); echo ")\n";
    $res_price = CPrice::Update($arr["ID"], $arFields);
    if (!$res_price) {
        $e = $APPLICATION->GetException();
        echo "ERROR: CPrice::Update вернул false: ";
        if ($e) echo $e->GetString();
        else echo "Неизвестная ошибка.";
        echo "\n";
    } else {
        echo "DEBUG: Цена обновлена успешно!\n";
    }
} else {
    echo "DEBUG: CPrice::Add("; print_r($arFields); echo ")\n";
    $res_price = CPrice::Add($arFields);
    if (!$res_price) {
        $e = $APPLICATION->GetException();
        echo "ERROR: CPrice::Add вернул false: ";
        if ($e) echo $e->GetString();
        else echo "Неизвестная ошибка.";
        echo "\n";
    } else {
        echo "DEBUG: Цена добавлена успешно!\n";
    }
}

            if ($res_price) {
//                    echo " Цена у товара с ISBN = " . $PRODUCT_ISBN . " обновилась до " . $priceWithNDS;
//                    echo "<br /><br />";
            }

            $time_end = microtime_float();
            $time = $time_end - $time_start;

            if ($isLoggingRequired) {
                $content = "У товара c $isbn обновляется цена за $time секунд\r\n";
                file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $content, FILE_APPEND);
            }

            unset($time_start, $time_end, $time, $content);


            $time_start = microtime_float();

// 1) Остатки по складам
setStoreAmount($PRODUCT_ID, 2, $quantity);       // склад 2
setStoreAmount($PRODUCT_ID, 3, $quantity_shop);  // склад 3

// 2) Общее количество + НДС (для цифровых у вас $totalQuantity = 999)
$qtyToSet = isset($totalQuantity)
    ? (int)$totalQuantity
    : (int)($quantity + $quantity_shop);

$updRes = CatalogProductModel::update($PRODUCT_ID, [
    'QUANTITY'     => $qtyToSet,        // <-- правильно: ставим $qtyToSet
    'VAT_INCLUDED' => $vatIncluded,     // 'Y' / 'N'
    'VAT_ID'       => $nds,             // 2 или 3
]);

if (!$updRes->isSuccess()) {
    $errs = implode('; ', $updRes->getErrorMessages());
    echo "ERROR: Product::update failed for ID={$PRODUCT_ID}: {$errs}\r\n";
}


$time_end = microtime_float();
$time = $time_end - $time_start;

if ($isLoggingRequired) {
    $content = "У товара c $isbn обновляются остатки и параметры товара за $time секунд\r\n";
    file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $content, FILE_APPEND);
}

unset($time_start, $time_end, $time, $content);



            $time_start = microtime_float();

            $ATR_STOCK_STATUS = 'false';
if ($isDigital || $quantity > 0 || $quantity_shop > 0) {
    $ATR_STOCK_STATUS = 'true';
} elseif ($quantity == 0 && $quantity_shop == 0 && $quantity_ptv > 0) {
    $ATR_STOCK_STATUS = 'only_ptv';
}

// ATR_GROUP остаётся directory
$xmlGroup = ensureDirectoryValue($ob['IBLOCK_ID'], 'ATR_GROUP', $groupName, $groupSlug);

// ATR_ORIGIN может быть directory ИЛИ список — разрулим автоматически
$originVal = ensurePropertyValueAuto($ob['IBLOCK_ID'], 'ATR_ORIGIN', $originName, $originSlug);

$enumPTV = 631; // ПТВ 
$enumSALE = 470; // Распродажа

// 0) Найдём enum-ID по XML_ID (без хардкода), на всякий случай с фолбэком по названию
$__getHitEnumIds = function(int $iblockId, string $propCode, array $needXml): array {
    $result = [];
    $prop = CIBlockProperty::GetList([], ['IBLOCK_ID'=>$iblockId,'CODE'=>$propCode])->Fetch();
    if ($prop && (int)$prop['ID'] > 0) {
        $rs = CIBlockPropertyEnum::GetList(['SORT'=>'ASC'], ['PROPERTY_ID'=>$prop['ID']]);
        while ($e = $rs->Fetch()) {
            $eid = (int)$e['ID'];
            $xml = (string)$e['XML_ID'];
            $val = (string)$e['VALUE'];
            foreach ($needXml as $key => $want) {
                if (!isset($result[$key])) {
                    if (strcasecmp($xml, $want) === 0) {
                        $result[$key] = $eid;
                    }
                }
            }
            // запасной путь: если XML_ID не совпал, но в VALUE есть «Уценка»
            if (!isset($result['PTV']) && mb_stripos($val, 'уценк') !== false) {
                $result['PTV'] = $eid;
            }
            // запасной путь для SALE по тексту
            if (!isset($result['SALE']) && mb_stripos($val, 'распрод') !== false) {
                $result['SALE'] = $eid;
            }
        }
    }
    return $result;
};

$ids = $__getHitEnumIds($ob['IBLOCK_ID'], 'HIT', ['SALE'=>'SALE','PTV'=>'PTV']);
$enumSALE = (int)($ids['SALE'] ?? 0);
$enumPTV  = (int)($ids['PTV']  ?? 0);

// для наглядного лога соберём карту enumId => [XML_ID, VALUE]
$__hitEnumMap = [];
if ($enumSALE || $enumPTV) {
    $prop = CIBlockProperty::GetList([], ['IBLOCK_ID'=>$ob['IBLOCK_ID'],'CODE'=>'HIT'])->Fetch();
    if ($prop && (int)$prop['ID']>0) {
        $rs = CIBlockPropertyEnum::GetList(['SORT'=>'ASC'], ['PROPERTY_ID'=>$prop['ID']]);
        while ($e = $rs->Fetch()) {
            $__hitEnumMap[(int)$e['ID']] = ['XML_ID'=>$e['XML_ID'], 'VALUE'=>$e['VALUE']];
        }
    }
}
$__lab = function($enumId) use ($__hitEnumMap) {
    $e = $__hitEnumMap[$enumId] ?? null;
    return $enumId . ($e ? " [{$e['XML_ID']}: {$e['VALUE']}]" : '');
};

// 1) текущее состояние HIT: PROPERTY_VALUE_ID => enumId
$hitExistingPv2Enum = [];   // [pvId => enumId]
$hitExistingEnums   = [];   // [enumId => true]
$prHit = CIBlockElement::GetProperty($ob['IBLOCK_ID'], $PRODUCT_ID, ['sort'=>'asc'], ['CODE'=>'HIT']);
while ($r = $prHit->Fetch()) {
    $eId = (int)$r['VALUE_ENUM_ID'];
    if ($eId > 0) {
        $pvId = (int)$r['PROPERTY_VALUE_ID'];
        $hitExistingPv2Enum[$pvId] = $eId;
        $hitExistingEnums[$eId]    = true;
    }
}
$logBefore = [];
foreach ($hitExistingPv2Enum as $pvId => $eId) { $logBefore[] = "{$pvId}=>".$__lab($eId); }
log2("HIT BEFORE id={$PRODUCT_ID}: ".($logBefore ? implode(', ', $logBefore) : '— нет значений —')." (SALE={$enumSALE}, PTV={$enumPTV})");

// 2) признаки из Excel
$ptvCount = (int)$quantity_ptv;                          // J
$isSale   = (strcasecmp(trim((string)$sale), 'S') === 0); // K

// 3) план изменений ТОЛЬКО для этих двух флагов
$toRemove = [];
$toAdd    = [];

if ($enumSALE) {
    if ($isSale) { if (empty($hitExistingEnums[$enumSALE])) $toAdd[] = $enumSALE; }
    else         { if (!empty($hitExistingEnums[$enumSALE])) $toRemove[] = $enumSALE; }
}
if ($enumPTV) {
    if ($ptvCount > 0) { if (empty($hitExistingEnums[$enumPTV])) $toAdd[] = $enumPTV; }
    else               { if (!empty($hitExistingEnums[$enumPTV])) $toRemove[] = $enumPTV; }
}

log2("HIT PLAN  id={$PRODUCT_ID}: ADD=[".implode(', ', array_map($__lab, $toAdd))."] REMOVE=[".implode(', ', array_map($__lab, $toRemove))."]");

// 4) ПОЛНЫЙ набор для записи в нужном формате: ['pvId'=>['VALUE'=>enumId], 'nX'=>['VALUE'=>enumId]]
$hitToWrite = [];
foreach ($hitExistingPv2Enum as $pvId => $enumId) {
    if (in_array($enumId, $toRemove, true)) continue;        // снимаем только нужные
    $hitToWrite[$pvId] = ['VALUE' => $enumId];               // сохраняем всё остальное как было
}
foreach ($toAdd as $enumId) {
    $hitToWrite['n'.uniqid()] = ['VALUE' => $enumId];        // добавляем новые
}

$logWrite = [];
foreach ($hitToWrite as $k => $v) { $logWrite[] = "{$k}=>".$__lab((int)$v['VALUE']); }
log2("HIT WRITE id={$PRODUCT_ID}: ".($logWrite ? implode(', ', $logWrite) : 'false (очистка)'));

// 5) запись (одним вызовом вместе с прочими свойствами)
$propsToSet = [
    'ATR_STOCK_STATUS' => $ATR_STOCK_STATUS,
    711                => $ptvCount,
    'HIT'              => $hitToWrite ?: false,
];
if ($xmlGroup !== null)  { $propsToSet['ATR_GROUP']  = $xmlGroup; }
if ($originVal !== null) { $propsToSet['ATR_ORIGIN'] = $originVal['value']; }

CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, $ob['IBLOCK_ID'], $propsToSet);

// 6) контрольное перечитывание
$afterPv2Enum = [];
$pr2 = CIBlockElement::GetProperty($ob['IBLOCK_ID'], $PRODUCT_ID, ['sort'=>'asc'], ['CODE'=>'HIT']);
while ($rr = $pr2->Fetch()) {
    $eId = (int)$rr['VALUE_ENUM_ID'];
    if ($eId > 0) $afterPv2Enum[(int)$rr['PROPERTY_VALUE_ID']] = $eId;
}
$logAfter = [];
foreach ($afterPv2Enum as $pvId => $eId) { $logAfter[] = "{$pvId}=>".$__lab($eId); }
log2("HIT AFTER  id={$PRODUCT_ID}: ".($logAfter ? implode(', ', $logAfter) : '— нет значений —'));



//                echo "У товара с ISNB = " . $PRODUCT_ISBN . " обновились/добавились свойства:<br />ATR_GROUP - " . $group . "<br />ATR_ORIGIN - " . $origin;
//                echo "<br /><br />";

            $time_end = microtime_float();
            $time = $time_end - $time_start;
            if ($isLoggingRequired) {
                $content = "У товара c $isbn обновляются свойства group и origin за $time секунд\r\n";
                file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $content, FILE_APPEND);
            }
            unset($time_start, $time_end, $time, $content);
			
			

            // ЕДИНАЯ логика активности по колонке G
// Приводим G к верхнему регистру и убираем пробелы
$deactivateFlag = strtoupper(trim((string)$deactivate)); // из колонки G

// По флагу 'Y' — всегда выключаем
if ($deactivateFlag === 'Y') {
    $shouldBeActive = false;
} else {
    // Иначе включаем, если есть цена/остатки/ПТВ или цифровой с 999
    $hasAvailability   = ($priceWithNDS > 0) || ($quantity > 0) || ($quantity_shop > 0) || ($quantity_ptv > 0);
    $isDigitalAvailable = ($isDigital && (int)$totalQuantity === 999);
    $shouldBeActive = ($hasAvailability || $isDigitalAvailable);
}

$el = new CIBlockElement;
$res = $el->Update($PRODUCT_ID, [
    "MODIFIED_BY" => $modifiedBy,
    "ACTIVE"      => $shouldBeActive ? "Y" : "N",
], false, false);

if ($res) {
    echo "DEBUG: товар ID={$PRODUCT_ID} ".($shouldBeActive ? "АКТИВИРОВАН" : "ДЕАКТИВИРОВАН")." по G={$deactivateFlag}\r\n";
} else {
    echo "ERROR: не удалось обновить ACTIVE у товара ID={$PRODUCT_ID}. LAST_ERROR: {$el->LAST_ERROR}\r\n";
}



            if ($deactivate == "Y" || ($deactivate == "N" && $quantity_ptv == 0)) {
                $time_start = microtime_float();
                $productList = [$PRODUCT_ID];
                $offersExist = CCatalogSKU::getExistOffers($productList); //Метод возвращает признак наличия торговых предложений для массива товаров из одного или нескольких инфоблоков.
                $intOfferID = [];
                if (!$offersExist[$PRODUCT_ID]) {
                } else {
                    $arSelect = ["ID"];
                    $arFilter = ["PROPERTY_PTV_ON" => $PRODUCT_ID];
                    $res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
                    while ($obOffer = $res->GetNext()) {
    $intOfferID[] = (int)$obOffer["ID"];
}
                    if (!empty($intOfferID)) {
                        $arSelect = ["ID"];
                        $arFilter = ['IBLOCK_ID' => 3, 'ID' => $intOfferID];
                        $res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
                        while ($obOffer = $res->GetNext()) {
    CIBlockElement::Delete($obOffer["ID"]);
}
                    }
                }

                $time_end = microtime_float();
                $time = $time_end - $time_start;
                if ($isLoggingRequired) {
                    $content = "У Товара c $isbn нет ПТВ, а если есть, то они удалены за $time секунд\r\n";
                    file_put_contents("logSap.txt", "\xEF\xBB\xBF" . $content, FILE_APPEND);
                }

                unset($time_start, $time_end, $time, $content);
            }

            $time_start = microtime_float();
            // Освобождаем оперативку.
//                unset($arrFullProduct[$key_index], $isbn, $quantity, $quantity_ptv, $price, $priceWithNDS, $nds, $group, $origin, $sale, $deactivate, $key_index, $result_index);
            $time_end = microtime_float();
            $time = $time_end - $time_start;
//      $content="Очистка оперативной памяти за текущую итерацию прошла за $time секунд\r\n-----------------------------------------------------\r\n\r\n";
//      file_put_contents("logSap.txt", "\xEF\xBB\xBF".$content, FILE_APPEND);
            unset($time_start, $time_end, $time, $content);
        }
        unset($arrFullProduct, $objPHPExcel, $arrISBN);
        $startRow += $chunkSize;
        $iter++;

    }
}
catch(Exception $e) {

    $content="Ошибка " . print_r($e, true) . " \r\n";
    echo $content;
    file_put_contents("logSap.txt", "\xEF\xBB\xBF".$content, FILE_APPEND);
}
echo "\n=== DEBUG: скрипт завершён ===\n";
echo "The End".PHP_EOL;

//снимаем флаг обновления остатков
if(\Bitrix\Main\Loader::includeModule('askaron.settings')){
    $arUpdateFields = ["UF_UPDATE_STOCK" => 0];

    $obSettings = new \CAskaronSettings;
    $updateConf = $obSettings->Update($arUpdateFields);

    if(!$updateConf){
        echo $obSettings->LAST_ERROR.PHP_EOL;
    } else {
        echo '(lock) Разблокирована очередь отправки изменений в SAP '.date('d.m.Y H:i:s').PHP_EOL;
    }
    unset($arUpdateFields, $obSettings, $updateConf);
}
$time_end_full_script = microtime_float();
$time_full_script = $time_end_full_script - $time_start_full_script;
$content="Время работы скрипта проходит за $time_full_script секунд\r\n";
file_put_contents("logSap.txt", "\xEF\xBB\xBF".$content, FILE_APPEND);
unset($time_start_full_script, $time_end_full_script, $time_full_script, $content);
function rus2translit($string) {
    $converter = array(
        'а' => 'a',   'б' => 'b',   'в' => 'v',
        'г' => 'g',   'д' => 'd',   'е' => 'e',
        'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
        'и' => 'i',   'й' => 'y',   'к' => 'k',
        'л' => 'l',   'м' => 'm',   'н' => 'n',
        'о' => 'o',   'п' => 'p',   'р' => 'r',
        'с' => 's',   'т' => 't',   'у' => 'u',
        'ф' => 'f',   'х' => 'h',   'ц' => 'c',
        'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
        'ь' => '\'',  'ы' => 'y',   'ъ' => '\'',
        'э' => 'e',   'ю' => 'yu',  'я' => 'ya',

        'А' => 'A',   'Б' => 'B',   'В' => 'V',
        'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
        'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
        'И' => 'I',   'Й' => 'Y',   'К' => 'K',
        'Л' => 'L',   'М' => 'M',   'Н' => 'N',
        'О' => 'O',   'П' => 'P',   'Р' => 'R',
        'С' => 'S',   'Т' => 'T',   'У' => 'U',
        'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
        'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
        'Ь' => '\'',  'Ы' => 'Y',   'Ъ' => '\'',
        'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
    );
    return strtr($string, $converter);
}
function str2url($str) {
    // переводим в транслит
    $str = rus2translit($str);
    // в нижний регистр
    $str = strtolower($str);
    // заменям все ненужное нам на "-"
    $str = preg_replace('~[^-a-z0-9_]+~u', '_', $str);
    // удаляем начальные и конечные '-'
    $str = trim($str, "_");
    return $str;
}
// Функция для проверки страны происхождения
function checkOrigin($origin) {
    $origin = mb_strtolower($origin);
    switch ($origin) {
        case 'австралия':
            return 68;
            break;
        case 'австрия':
            return 69;
            break;
        case 'белоруссия':
            return 70;
            break;
        case 'бельгия':
            return 71;
            break;
        case 'болгария':
            return 72;
            break;
        case 'германия':
            return 73;
            break;
        case 'греция':
            return 74;
            break;
        case 'дания':
            return 75;
            break;
        case 'индия':
            return 76;
            break;
        case 'исландия':
            return 77;
            break;
        case 'испания':
            return 78;
            break;
        case 'италия':
            return 79;
            break;
        case 'канада':
            return 80;
            break;
        case 'китай':
            return 81;
            break;
        case 'нидерланды':
            return 82;
            break;
        case 'норвегия':
            return 83;
            break;
        case 'россия':
            return 84;
            break;
        case 'соединенное королевство':
            return 85;
            break;
        case 'сша':
            return 86;
            break;
        case 'франция':
            return 87;
            break;
        case 'швейцария':
            return 88;
            break;
    }
}
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function GetEntityDataClass($HlBlockId) {
    if (empty($HlBlockId) || $HlBlockId < 1)
    {
        return false;
    }
    $hlblock = HLBT::getById($HlBlockId)->fetch();
    $entity = HLBT::compileEntity($hlblock);
    $entity_data_class = $entity->getDataClass();
    return $entity_data_class;
}
