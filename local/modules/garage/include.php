<?php

/**
 * Регистрирует классы для автозагрузки и настраивает обработчики событий.
 */
\Bitrix\Main\Loader::registerAutoloadClasses('garage', [
    'Garage\EventHandlers' => 'lib/eventhandlers.php',
]);

use Bitrix\Main\EventManager;

// Запись в лог-файл для отладки
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_include.log', 'Include.php загружен!' . PHP_EOL, FILE_APPEND | LOCK_EX);

/**
 * Регистрация обработчика события onEntityDetailsTabsInitialized для модуля CRM.
 */
EventManager::getInstance()->addEventHandler(
    'crm',
    'onEntityDetailsTabsInitialized',
    ['\Garage\EventHandlers', 'onEntityDetailsTabsInitialized']
);

