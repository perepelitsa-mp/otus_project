<?php

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    die('Модуль инфоблоков не подключён.');
}

// Получение ID контакта
$contactId = (int)$_GET['id'];
if (!$contactId) {
    die('Не указан ID контакта.');
}

$iblockCode = 'cars';
$iblockId = \Bitrix\Iblock\IblockTable::getList([
    'filter' => ['=CODE' => $iblockCode],
])->fetch()['ID'];

$carList = [];
if ($iblockId) {
    $res = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'PROPERTY_CONTACT' => $contactId],
        false,
        false,
        ['ID', 'NAME', 'PROPERTY_MODEL', 'PROPERTY_YEAR', 'PROPERTY_COLOR', 'PROPERTY_MILEAGE']
    );

    while ($car = $res->Fetch()) {
        $carList[] = $car;
    }
}
?>
<div>
    <h3>Автомобили контакта</h3>
    <?php if (!empty($carList)): ?>
        <ul>
            <?php foreach ($carList as $car): ?>
                <li><?= htmlspecialchars($car['NAME']) ?> (<?= htmlspecialchars($car['PROPERTY_MODEL_VALUE']) ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Нет автомобилей, связанных с этим контактом.</p>
    <?php endif; ?>
</div>
