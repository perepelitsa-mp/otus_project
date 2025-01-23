<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
Loader::includeModule('crm');

$entityId = 'CRM_DEAL'; // Для сущности "Сделка"
$res = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId]);

while ($field = $res->Fetch()) {
    echo "Field Name: " . $field['FIELD_NAME'] . "<br>";
    echo "Field ID: " . $field['ID'] . "<br>";
    echo "<br>";
}

