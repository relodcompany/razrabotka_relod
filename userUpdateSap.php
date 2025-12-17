<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath(dirname(__FILE__) . '/../../'));
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

////////////////////////////////////

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define('BX_SECURITY_SESSION_VIRTUAL', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// === ЛОГГЕР + НАСТРОЙКИ =========================================
require_once __DIR__ . '/support_lib/Logger.php';

// На всякий случай — подключим ваши вспомогательные классы корректно по пути из текущей папки
require_once __DIR__ . '/support_lib/UserData.php';
require_once __DIR__ . '/support_lib/UserFieldFormatter.php';

$logger = new \Support\Logger([
    'log_dir'     => __DIR__ . '/logs', // создастся автоматически
    'level'       => (getenv('DEBUG') === 'Y' || in_array('--debug', $argv ?? [])) ? 'debug' : 'info',
    'tee_to_file' => true,              // дублировать в файл
    'use_colors'  => null,              // авто-определение TTY
]);

// Если в скрипте раньше вызывался $showMessage("...") — оставим совместимый хелпер:
$showMessage = $logger->bindShowMessageCallable();

// (опционально) SQL-трекинг Bitrix D7 при флаге --trace-db
try {
    if (in_array('--trace-db', $argv ?? [])) {
        $logger->info('Включаю SQL-трекер Bitrix D7');
        $connection = \Bitrix\Main\Application::getInstance()->getConnection();
        if (is_object($connection)) {
            $connection->startTracker();
            $GLOBALS['__LOGGER_SQL_TRACKER_ENABLED__'] = true;
        } else {
            $logger->warn('Не удалось получить соединение с БД.');
        }
    }
} catch (\Throwable $e) {
    $logger->warn('SQL-трекер не доступен: ' . $e->getMessage());
}
// === /ЛОГГЕР =====================================================

use Bitrix\Main\GroupTable;
use UserFieldFormatter as UFF;

while (ob_get_level()) {
    ob_end_flush();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/classes/PHPExcel/IOFactory.php';  //Подключаем библиотеку.

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/console/support_lib/UserFieldFormatter.php';  //Обработка свойств пользователя
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/console/support_lib/UserData.php';
//require_once $_SERVER["DOCUMENT_ROOT"] .'/classes/PHPExcel/IOFactory.php';  //Подключаем библиотеку.

$showMessage("дата и время: ". (new \Bitrix\Main\Type\DateTime()));

$sourcePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/user_update/clients.xlsx';

if (!file_exists($sourcePath)) {
    $showMessage('некоректный путь к файлу ' . $sourcePath);
    die();
}

$groupsRes = GroupTable::getList([
    'filter' => ['ID' => UFF::getGroups()]
])->fetchAll();
if (count($groupsRes) != count(array_unique(UFF::getGroups()))) {
    $showMessage('некоректный набор групп для пользователей');
    die();
}
unset($groupsRes);
/*

Класс chunkReadFilter - класс для фильтрации каталога из exel позволяет загружать и
обрабатывать строки каталога частями, не вызывая большой нагрузки.

*/

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


$startRow = 2;

$inputFileType = 'Excel2007';
$objReader = PHPExcel_IOFactory::createReader($inputFileType);
$chunkSize = 1;
$chunkFilter = new chunkReadFilter();


set_time_limit(0); // Убираем ограничение по времени работы скрипта

$startTime = microtime(true);
$timeDBAll = 0;
$userOb = new CUser();

$allRowCount = 0;
$updatedUsersCount = 0;
$existUsersCount = 0;
$extendedDebug = \Bitrix\Main\Config\Option::get('main', 'extended_debug', 'N');
$users = UserData::loadUSers();

// нормализуем ключи email -> в нижний регистр и без пробелов
$usersByEmail = [];
foreach ($users as $emailKey => $u) {
    $normKey = mb_strtolower(trim((string)$emailKey));
    $usersByEmail[$normKey] = $u;
}

$workTime = 0;


$workTime = 0;


$logger->section('Старт основного цикла обработки');
while (true) {
    $startTimeCycle = microtime(true);
    $chunkFilter->setRows($startRow, $chunkSize);
    $objReader->setReadFilter($chunkFilter);
    $objReader->setReadDataOnly(true);
    // Работа с данными
    $col = [];
    try {
        $objPHPExcel = $objReader->load($sourcePath);
        $objPHPExcel->getActiveSheet()->getRowIterator();
        $rowIterator = $objPHPExcel->getActiveSheet()->getRowIterator($startRow, $chunkSize);
        foreach($rowIterator as $row) {
            $cellIterator = $row->getCellIterator();
            $col=['idx'=>$row->getRowIndex()];
            foreach($cellIterator as $cell) {
                $col[$cell->getColumn()] = $cell->getCalculatedValue();
            }
        }
    }
    catch (Exception $e) {
        break;
    }

// Нормализация телефона для валидации (оставляем только цифры, 8 -> 7)
if (!empty($col['F'])) {
    $phoneDigits = preg_replace('/\D+/', '', (string)$col['F']);
    if (strlen($phoneDigits) >= 10) {
        if ($phoneDigits[0] === '8') { $phoneDigits[0] = '7'; }
        $col['F'] = $phoneDigits; // дальше валидатору уйдёт уже чистая строка
    }
}


// >>> ДОБАВИТЬ: логируем ключевые поля текущей строки
$logger->info("Строка #{$col['idx']}: email={$col['B']} group_raw={$col['C']} card_raw={$col['D']} phone_raw={$col['F']}");


    $allRowCount++;

    $startRow += $chunkSize;

    $emailRaw = isset($col['B']) ? (string)$col['B'] : '';
$emailKey = mb_strtolower(trim($emailRaw));
$user = $usersByEmail[$emailKey] ?? null;


    if (!$user || empty($col)) {
    $logger->warn("Пользователь с email={$col['B']} не найден — пропуск строки #{$col['idx']}");
    continue;
}

    $existUsersCount++;
    $updateFields = [];

// 1) Карта: если валидна — пишем UF_DISCOUNT_CODE и пытаемся определить тип по CardNum
$groupKey = null;
if (UFF::isValidCard($col['D'] ?? null)) {
    $updateFields['UF_DISCOUNT_CODE'] = UFF::formatCard($col['D']);
    $groupKeyFromCard = UFF::groupKeyFromCard($col['D']); // G/S/P где угодно
    if ($groupKeyFromCard !== null) {
        $groupKey = $groupKeyFromCard; // CardNum главнее столбца C
    }
}

// 2) Если по карте тип не определён — используем CardType (столбец C)
if ($groupKey === null && UFF::isValidGroup($col['C'] ?? null)) {
    $groupKey = (int)$col['C'];
}

// 3) Применяем группу, если выбрали
if ($groupKey !== null) {
    $updateFields['GROUP_ID'] = UFF::formatGroup($groupKey, $user['GROUPS']);
}

// 4) Телефон
if (UFF::isValidPhone($col['F'] ?? null)) {
    $updateFields['PERSONAL_PHONE'] = UFF::formatPhone($col['F']);
}

// (опционально) отладка выбора
$logger->debug(
    'Group select: from_card=' . (isset($groupKeyFromCard) ? var_export($groupKeyFromCard, true) : 'null')
    . ' from_C=' . (UFF::isValidGroup($col['C'] ?? null) ? (int)$col['C'] : 'invalid')
    . ' => chosen=' . (isset($groupKey) ? $groupKey : 'null')
);


	
	// >>> ДОБАВИТЬ: что собираемся обновить (по именам полей)
if (!empty($updateFields)) {
    $will = implode(', ', array_keys($updateFields));
    // отдельный лог по группам — часто тут ошибка
    if (isset($updateFields['GROUP_ID'])) {
        $logger->debug('Группы: было=' . json_encode($user['GROUPS'], JSON_UNESCAPED_UNICODE)
            . ' станет=' . json_encode($updateFields['GROUP_ID'], JSON_UNESCAPED_UNICODE));
    }
    $logger->info("Обновляю ID={$user['ID']} email={$col['B']} поля: {$will}");
}

	
    if (!empty($updateFields)) {
        $timeDB = microtime(true);
        $isSuccess = $userOb->Update($user['ID'], $updateFields);
		
        if (!$isSuccess) {
    $logger->error("Ошибка обновления ID={$user['ID']} email={$col['B']}: " . $userOb->LAST_ERROR);
} else {
    $logger->success("Обновлён ID={$user['ID']} email={$col['B']}");
    $updatedUsersCount++;
}

        $timeDBAll += microtime(true) - $timeDB;
    }
    if ($extendedDebug === 'Y') {
        $showMessage("Debug:\n\tДанные: \n\t". implode(', ', $col));
        if (!empty($updateFields)) {
            $showUpdateData = array_map(function ($item) {
                return '[' . implode(',', (array) $item ).']';
            }, $updateFields);
            $showMessage("\tОбновить: \n\t". implode(', ', $showUpdateData));
            unset($showUpdateData);
        }
    }
    // Освобождаем оперативку.
    unset($user);
    unset($col);
    unset($objPHPExcel);

    $workTime += microtime(true) - $startTimeCycle;

    //при долгой обработке нужен реконект к базе
    if ($workTime > 250) {
        $workTime = 0;
        //
        $connection = \Bitrix\Main\Application::getConnection();
        $connection->disconnect();
        $connection->connect();
        $connection->queryExecute("SET NAMES 'utf8'");
        $connection->queryExecute('SET collation_connection = "utf8_unicode_ci"');
        //
        $DB->Disconnect();
        $DB->Connect($DBHost, $DBName, $DBLogin, $DBPassword);
        $DB->Query("SET NAMES 'utf8'");
        $DB->Query('SET collation_connection = "utf8_unicode_ci"');
        $DB->Query("SET wait_timeout=21600"); //6 часов
    }
}

if (!empty($GLOBALS['__LOGGER_SQL_TRACKER_ENABLED__'])) {
    try {
        $connection = \Bitrix\Main\Application::getInstance()->getConnection();
        $tracker = $connection->getTracker();
        $queries = $tracker ? $tracker->getQueries() : [];
        $total = 0.0;
        foreach ($queries as $q) {
            $ms = round($q->getTime() * 1000, 3);
            $total += $q->getTime();
            $logger->debug("[SQL {$ms} ms] " . $q->getSql());
        }
        $logger->info("Всего SQL-времени: " . round($total, 3) . " сек.");
    } catch (\Throwable $e) {
        $logger->warn('Не удалось получить SQL-статистику: ' . $e->getMessage());
    }
}
$logger->section('Готово');


$showMessage("Обработано записей: ". $allRowCount);
$showMessage("Обработано cуществующих записей: ". $existUsersCount);
$showMessage("Обновлено записей: ". $updatedUsersCount);
$showMessage("Время выполнения в секундах: " . (microtime(true) - $startTime));
if ($extendedDebug === 'Y') {
    $showMessage("Время выполнения запросов к DB в секундах: " . ($timeDBAll));
}

$showMessage("The End");
?>