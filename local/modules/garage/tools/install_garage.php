<?php

use Bitrix\Main\Loader;

Loader::includeModule('crm');

// Добавление пользовательского поля для Контактов
$fieldManager = \Bitrix\Crm\Service\Container::getInstance()->getUserFieldManager();
$fieldManager->add([
    'ENTITY_ID' => 'CRM_CONTACT',
    'FIELD_NAME' => 'UF_CRM_GARAGE',
    'USER_TYPE_ID' => 'string',
    'EDIT_FORM_LABEL' => ['ru' => 'Автомобиль', 'en' => 'Car'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Автомобиль', 'en' => 'Car'],
]);

echo 'Поля успешно добавлены!';
