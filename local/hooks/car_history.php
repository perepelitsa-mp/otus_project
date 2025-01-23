<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;

/**
 * Основной скрипт для получения истории сделок, связанных с автомобилем.
 * Обрабатывает POST-запросы и возвращает JSON-ответ с данными сделок.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'message' => 'Invalid request method']));
}

// Начало выполнения скрипта
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Script started" . PHP_EOL, FILE_APPEND);

// Логируем запрос
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "REQUEST: " . print_r($_REQUEST, true) . PHP_EOL, FILE_APPEND);

global $USER;
$USER->IsAuthorized();
$USER->Authorize(1);
if (!$USER->IsAuthorized()) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Error: Admin authorization failed" . PHP_EOL, FILE_APPEND);
    die(json_encode(['status' => 'error', 'message' => 'Не удалось авторизоваться под администратором.']));
}

file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Authorized as admin. User ID: " . $USER->GetID() . PHP_EOL, FILE_APPEND);
$carId = $_REQUEST['carId'] ?? null;

/**
 * Проверяем наличие ID автомобиля
 * @param string|null $carId Идентификатор автомобиля
 */
if (!$carId) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Error: carId not provided" . PHP_EOL, FILE_APPEND);
    die(json_encode(['status' => 'error', 'message' => 'Не указан ID автомобиля.']));
}
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "CRM module check start" . PHP_EOL, FILE_APPEND);

// Подключаем модуль CRM
if (!Loader::includeModule('crm')) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Error: CRM module not loaded" . PHP_EOL, FILE_APPEND);
    die(json_encode(['status' => 'error', 'message' => 'Модуль CRM не подключен.']));
}
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "CRM module loaded successfully" . PHP_EOL, FILE_APPEND);

// Проверяем наличие пользовательского поля
$dealFields = \CCrmDeal::GetFields();
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Deal fields: " . print_r($dealFields, true) . PHP_EOL, FILE_APPEND);

$dealUserFields = $GLOBALS['USER_FIELD_MANAGER']->GetUserFields('CRM_DEAL');
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Deal user fields: " . print_r($dealUserFields, true) . PHP_EOL, FILE_APPEND);

if (!array_key_exists('UF_CRM_1736305350', $dealUserFields)) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Error: UF_CRM_1736305350 field not found" . PHP_EOL, FILE_APPEND);
    die(json_encode(['status' => 'error', 'message' => 'Пользовательское поле UF_CRM_1736305350 отсутствует.']));
}

// Выполняем запрос
$history = [];
$res = \CCrmDeal::GetListEx(
    ['DATE_CREATE' => 'DESC'],
    [
        'UF_CRM_1736305350' => $carId,
        'CATEGORY_ID' => 1
    ],
    false,
    false,
    ['ID', 'TITLE', 'DATE_CREATE', 'OPPORTUNITY'],
    ['CHECK_PERMISSIONS' => 'N']
);

if (!$res) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Error: Query returned no results" . PHP_EOL, FILE_APPEND);
    die(json_encode(['status' => 'error', 'message' => 'Запрос вернул пустой результат.']));
}

// Формируем историю сделок
while ($deal = $res->Fetch()) {
    $history[] = [
        'ID' => $deal['ID'],
        'TITLE' => $deal['TITLE'],
        'DATE_CREATE' => $deal['DATE_CREATE'],
        'OPPORTUNITY' => $deal['OPPORTUNITY']
    ];
}

// Проверяем данные
if (empty($history)) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "No deals found for carId: $carId in category 1" . PHP_EOL, FILE_APPEND);
    die(json_encode(['status' => 'success', 'data' => [], 'message' => 'Нет данных.']));
}

// Формируем ответ
$response = [
    'status' => 'success',
    'data' => $history
];

// Логируем ответ
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Response: " . json_encode($response) . PHP_EOL, FILE_APPEND);

// Возвращаем ответ
echo json_encode($response);
exit;
