<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

use Garage\GarageManager;

$contactId = $_GET['CONTACT_ID']; // ID контакта
$garageData = GarageManager::getGarageData($contactId);

if ($garageData) {
    echo "<h2>Информация об автомобиле</h2>";
    echo "Модель: {$garageData}<br>";
} else {
    echo "Данные о гараже не указаны.";
}
