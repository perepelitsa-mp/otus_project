<?php

// Отключение статистики, агентов и проверки прав для AJAX-запроса
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('NOT_CHECK_PERMISSIONS', 'Y');
define('PUBLIC_AJAX_MODE', true);

// Подключение ядра Bitrix
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

// Проверка валидности сессии Bitrix
if (!check_bitrix_sessid()) {
    // Если сессия не валидна, возвращаем ошибку в формате JSON
    die(json_encode(['status' => 'error', 'message' => 'Invalid session']));
}

file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "REQUEST: " . print_r($_REQUEST, true) . PHP_EOL, FILE_APPEND);

// Получение параметров из запроса с установкой значений по умолчанию
$componentName = $_REQUEST['componentName'] ?? 'garage:car.list'; // Название компонента
$templateName = $_REQUEST['templateName'] ?? ''; // Шаблон компонента
$signedParameters = $_REQUEST['signedParameters'] ?? ''; // Подписанные параметры
$action = $_REQUEST['action'] ?? ''; // Действие, которое требуется выполнить
$params = [];

// Расшифровка подписанных параметров, если они были переданы
if ($signedParameters !== '') {
    try {
        $params = \Bitrix\Main\Component\ParameterSigner::unsignParameters($componentName, $signedParameters);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Unsign Parameters: " . print_r($params, true) . PHP_EOL, FILE_APPEND);
    } catch (\Exception $e) {
        // Если параметры не удалось расшифровать, возвращаем ошибку
        die(json_encode(['status' => 'error', 'message' => 'Invalid signed parameters']));
    }
}

file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "Action: $action" . PHP_EOL, FILE_APPEND);

// Обработка действия в зависимости от его типа
switch ($action) {
    case 'getCars': // Действие для получения списка автомобилей
        global $APPLICATION;

        // Включаем буферизацию вывода для захвата HTML-содержимого компонента
        ob_start();
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "IncludeComponent Params: " . print_r($params, true) . PHP_EOL, FILE_APPEND);
        // Подключаем компонент с переданными параметрами
        $APPLICATION->IncludeComponent(
            $componentName,
            $templateName,
            $params
        );
        // Сохраняем HTML-вывод компонента
        $html = ob_get_clean();
        // Логируем HTML-результат выполнения компонента
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', "HTML Output: " . $html . PHP_EOL, FILE_APPEND);

        // Возвращаем HTML-результат
        echo $html;
        break;

    case 'addCar': // Действие для добавления автомобиля
        $entityId = $params['ENTITY_ID'] ?? $_REQUEST['ENTITY_ID'] ?? null;

        // Проверка наличия ID контакта
        if (!$entityId) {
            die(json_encode(['status' => 'error', 'message' => 'Не указан ID контакта.']));
        }

        // Проверка подключения модуля "Инфоблоки"
        if (!Loader::includeModule('iblock')) {
            die(json_encode(['status' => 'error', 'message' => 'Модуль "Инфоблоки" не подключен.']));
        }

        // Получение ID инфоблока с кодом "cars"
        $iblockId = \Bitrix\Iblock\IblockTable::getList([
            'filter' => ['=CODE' => 'cars'],
        ])->fetch()['ID'];

        // Проверка существования инфоблока
        if (!$iblockId) {
            die(json_encode(['status' => 'error', 'message' => 'Инфоблок "Автомобили" не найден.']));
        }

        // Формирование данных для нового элемента инфоблока
        $carData = [
            'NAME' => $_REQUEST['CAR_NAME'],
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => [
                'CONTACT' => $entityId,
                'MODEL' => $_REQUEST['CAR_MODEL'],
                'YEAR' => $_REQUEST['CAR_YEAR'],
                'COLOR' => $_REQUEST['CAR_COLOR'],
                'MILEAGE' => $_REQUEST['CAR_MILEAGE'],
            ],
        ];

        $el = new \CIBlockElement(); // Экземпляр класса для работы с элементами инфоблоков
        // Попытка добавления элемента
        if ($el->Add($carData)) {
            echo json_encode(['status' => 'success', 'message' => 'Автомобиль добавлен.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Ошибка добавления автомобиля.']);
        }
        break;


    default:
        die(json_encode(['status' => 'error', 'message' => 'Неизвестное действие.']));
}
