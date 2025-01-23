<?php

// Подключение обработчиков
$handlerPath = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/crm/deal/DuplicateCounter/Handler.php';
if (file_exists($handlerPath)) {
    require_once $handlerPath;
} else {
    file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/local/log/debug_init.log',
        "[" . date('Y-m-d H:i:s') . "] Ошибка: файл Handler.php не найден\n",
        FILE_APPEND
    );
}

function writeLog($message)
{
    $logFile = $_SERVER["DOCUMENT_ROOT"] . "/update_inventory.log";
    $dateTime = date("Y-m-d H:i:s");
    $formattedMessage = "[$dateTime] $message\n";
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}

function isProductInCatalog($productId)
{
    $product = CCatalogProduct::GetByID($productId);
    return $product !== false;
}

function registerProductInCatalog($productId)
{
    $fields = [
        "ID" => $productId,
        "QUANTITY" => 0,
    ];
    if (CCatalogProduct::Add($fields)) {
        writeLog("Товар ID $productId зарегистрирован в каталоге");
    } else {
        writeLog("Ошибка регистрации товара ID $productId в каталоге");
    }
}

function updateProductQuantity($productId, $quantity)
{
    $fields = ["QUANTITY" => $quantity];
    if (CCatalogProduct::Update($productId, $fields)) {
        writeLog("Остаток товара ID $productId обновлён: $quantity");
    } else {
        writeLog("Ошибка обновления остатка товара ID $productId");
    }
}

function getAllProductIds($iblockId)
{
    $productIds = [];
    $res = CIBlockElement::GetList(
        [],
        ["IBLOCK_ID" => $iblockId, "ACTIVE" => "Y"],
        false,
        false,
        ["ID", "NAME"]
    );
    while ($item = $res->Fetch()) {
        writeLog("Найден товар: ID = {$item['ID']}, Название = {$item['NAME']}");
        $productIds[] = $item["ID"];
    }
    writeLog("Всего найдено товаров: " . count($productIds));
    return $productIds;
}

function getRandomInventory()
{
    $url = "https://www.random.org/integers/?num=1&min=0&max=10&col=1&base=10&format=plain&rnd=new";
    writeLog("Отправлен запрос к сервису: $url");

    $context = stream_context_create([
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ]
    ]);

    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        $error = error_get_last();
        writeLog("Ошибка: " . $error['message']);
        return null;
    }

    writeLog("Ответ от сервиса: " . trim($result));
    return (int)trim($result);
}

function createPurchaseRequest($iblockId, $productId, $quantity)
{
    writeLog("ID Block: $iblockId");
    writeLog("ID productId: $productId");
    writeLog("ID quantity: $quantity");
    $product = CIBlockElement::GetByID($productId)->GetNext();
    $productName = $product["NAME"];
    global $USER;
    $USER = new CUser;
    $USER->Authorize(1);
    $currentUserId = $USER->GetID();
    $currentDateTime = ConvertTimeStamp(time(), "FULL"); // Текущая дата/время
    writeLog("Скрипт выполняется от имени пользователя ID: $currentUserId");
    // Получаем значение ID для статуса "Создан"
    $statusValue = getEnumPropertyValue($iblockId, "STATUS", "Создан");

    if (!$statusValue) {
        writeLog("Не удалось получить значение для статуса 'Создан'");
        return;
    }

    $el = new CIBlockElement;

    $fields = [
        "IBLOCK_ID" => $iblockId,
        "NAME" => "Закупка: $productName", // Название элемента
        "ACTIVE" => "Y",
        "PROPERTY_VALUES" => [
            "ID_TRADE" => $productId,             // ID товара
            "AMOUNT" => $quantity,               // Количество
            "STATUS" => $statusValue,            // Статус "Создан"
            "OTVET" => $currentUserId,           // Ответственный (текущий пользователь)
            "DATA" => $currentDateTime,          // Дата создания
            "COMMENT" => "Автоматически создан", // Комментарий
        ],
    ];

    if ($requestId = $el->Add($fields)) {
        writeLog("Запрос на закупку для товара ID $productId создан с ID = $requestId");
        sleep(5);
        writeLog("Закончил ожидание.");
        startBusinessProcess(13, $requestId); // В данном месте была ошибка. Случайно вместое требуемого $requestId (ID документа) передвал $productId
    } else {
        writeLog("Ошибка создания запроса на закупку для товара ID $productId: " . $el->LAST_ERROR);
    }
}

/**
 * Получает ID значения перечислимого свойства.
 *
 * @param int $iblockId ID инфоблока.
 * @param string $propertyCode Код свойства.
 * @param string $value Значение свойства.
 * @return int|false ID значения свойства или false, если не найдено.
 */
function getEnumPropertyValue($iblockId, $propertyCode, $value)
{
    $propertyEnums = CIBlockPropertyEnum::GetList(
        [],
        ["IBLOCK_ID" => $iblockId, "CODE" => $propertyCode, "VALUE" => $value]
    );
    if ($enumField = $propertyEnums->GetNext()) {
        return $enumField["ID"];
    }
    return false;
}


/**
 * Запускает бизнес-процесс для элемента, если найдено 0 деталей.
 *
 * @param int $bpId ID бизнес-процесса.
 * @param int $elementId ID элемента.
 */

function startBusinessProcess($bpId, $elementId)
{
    writeLog("Входные парраметры bpId = $bpId и elementId = $elementId");
    $documentIdArray = array('lists', 'BizprocDocument', $elementId);
    $arParameters = array(
        'PARAM1' => $data['PARAM1'] ?? 'Default1',
        'PARAM2' => $data['PARAM2'] ?? 'Default2'
    );
    $arErrorsTmp = array();
    $workflowId = CBPDocument::StartWorkflow(
        $bpId,
        $documentIdArray,
        $arParameters,
        $arErrorsTmp
    );

    if ($workflowId) {
        writeLog("Бизнес-процесс ID $bpId успешно запущен для элемента ID $elementId");
    } else {
        writeLog("Ошибка запуска бизнес-процесса ID $bpId: " . print_r($arErrorsTmp, true));
    }
}

function processInventory()
{
    writeLog("Агент начал выполнение");  // Лог для отладки
    $catalogIblockId = 15;
    $purchaseIblockId = 87;

    writeLog("Запущен скрипт для инфоблока ID = $catalogIblockId");
    $productIds = getAllProductIds($catalogIblockId);
    writeLog("Обработка товара началась");

    if (empty($productIds)) {
        writeLog("Не удалось найти товары в каталоге");
        return;
    }

    foreach ($productIds as $productId) {
        writeLog("Обработка товара ID = $productId");

        if (!isProductInCatalog($productId)) {
            writeLog("Товар ID $productId не зарегистрирован в каталоге, регистрируем...");
            registerProductInCatalog($productId);
        }

        $availability = getRandomInventory();

        if ($availability === null) {
            writeLog("Не удалось обновить товар с ID $productId, сервис недоступен");
            continue;
        }

        updateProductQuantity($productId, $availability);
        if ($availability === 0) {
            writeLog("Остаток товара ID $productId равен 0, создаём запрос на закупку...");
            createPurchaseRequest($purchaseIblockId, $productId, 10);
        }
    }


    writeLog("Обработка всех товаров завершена");
    return "processInventory();";
}

