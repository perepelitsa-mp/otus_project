<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;
class garage extends CModule
{
    public $MODULE_ID = 'garage';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2025-01-01';
    public $MODULE_NAME = 'Гараж';
    public $MODULE_DESCRIPTION = 'Модуль для управления автомобилями клиентов в CRM';

    /**
     * Установка модуля.
     */
    public function DoInstall()
    {
        global $APPLICATION;

        $this->InstallComponents();
        // Подключаем модуль инфоблоков
        if (!Loader::includeModule('iblock')) {
            $APPLICATION->ThrowException('Не удалось подключить модуль "Инфоблоки"');
            return false;
        }

        // Создаём инфоблок
//        self::createInfoblock();


        // Регистрируем модуль
        RegisterModule($this->MODULE_ID);

        // Очищаем кеш
        self::clearCache();
        EventManager::getInstance()->registerEventHandler(
            'crm',
            'onEntityDetailsTabsInitialized',
            $this->MODULE_ID,
            '\Garage\eventhandlers',
            'onEntityDetailsTabsInitialized'
        );


    }

    /**
     * Установка компонентов.
     */
    public function InstallComponents()
    {
        $source = __DIR__ . '/components';
        $destination = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/garage';

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/install_debug.log', "Начало копирования компонентов\n", FILE_APPEND);

        if (!is_dir($source)) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/install_debug.log', "Папка с компонентами не найдена: $source\n", FILE_APPEND);
            return;
        }

        if (CopyDirFiles($source, $destination, true, true)) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/install_debug.log', "Компоненты успешно скопированы из $source в $destination\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/install_debug.log', "Ошибка при копировании компонентов из $source в $destination\n", FILE_APPEND);
        }
    }

    /**
     * Удаление компонентов.
     */
    public function UninstallComponents()
    {
        $destination = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/garage';
        DeleteDirFilesEx($destination);
    }
    /**
     * Удаление модуля.
     */
    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UninstallComponents();

        // Подключаем модуль инфоблоков
        if (Loader::includeModule('iblock')) {
            // Удаляем инфоблок
            self::deleteInfoblock();
        }

        // Удаляем вкладки
        self::uninstallTabs();

        UnRegisterModule($this->MODULE_ID);

        EventManager::getInstance()->unRegisterEventHandler(
            'crm',
            'onEntityDetailsTabsInitialized',
            $this->MODULE_ID,
            '\Garage\eventhandlers',
            'onEntityDetailsTabsInitialized'
        );


        // Очищаем кеш
        self::clearCache();


    }


    public function InstallTabs()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        // Добавляем вкладку "Гараж" в карточку Контакта
        $contactTabs = \CUserOptions::GetOption('crm.contact.details', 'tabs', []);

        // Проверяем, существует ли вкладка
        $tabExists = false;
        foreach ($contactTabs as $tab) {
            if ($tab['id'] === 'garage') {
                $tabExists = true;
                break;
            }
        }

        if (!$tabExists) {
            $contactTabs[] = [
                'id' => 'garage',
                'name' => 'Гараж',
                'title' => 'Список автомобилей',
                'enabled' => true,
                'sort' => 1000,
                'settings' => [
                    'path' => '/local/modules/garage/admin/contact_garage.php',
                ],
            ];

            \CUserOptions::SetOption('crm.contact.details', 'tabs', $contactTabs);
        }
    }

    public function UninstallTabs()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $contactTabs = \CUserOptions::GetOption('crm.contact.details', 'tabs', []);
        foreach ($contactTabs as $key => $tab) {
            if ($tab['id'] === 'garage') {
                unset($contactTabs[$key]);
            }
        }

        \CUserOptions::SetOption('crm.contact.details', 'tabs', $contactTabs);
    }

    private function createInfoblock()
    {
        $iblockType = 'lists'; // Используем существующий тип "Списки"
        $iblockCode = 'cars';

        // Проверяем, существует ли инфоблок
        $iblockResult = \Bitrix\Iblock\IblockTable::getList([
            'filter' => ['=CODE' => $iblockCode, '=IBLOCK_TYPE_ID' => $iblockType],
        ])->fetch();

        if (!$iblockResult) {
            $iblock = new \CIBlock;
            $iblockId = $iblock->Add([
                'ACTIVE' => 'Y',
                'NAME' => 'Автомобили',
                'CODE' => $iblockCode,
                'LIST_PAGE_URL' => '#SITE_DIR#/garage/index.php?ID=#IBLOCK_ID#',
                'DETAIL_PAGE_URL' => '#SITE_DIR#/garage/detail.php?ID=#ELEMENT_ID#',
                'IBLOCK_TYPE_ID' => $iblockType, // Используем существующий тип "lists"
                'SITE_ID' => ['s1'], // Укажите ваш SITE_ID
                'GROUP_ID' => ['2' => 'R'], // Доступ для всех пользователей
            ]);

            if ($iblockId) {
                self::addInfoblockProperties($iblockId);
            } else {
                global $APPLICATION;
                $APPLICATION->ThrowException('Ошибка создания инфоблока: ' . $iblock->LAST_ERROR);
            }
        }
    }

    private function addInfoblockProperties($iblockId)
    {
        $properties = [
            [
                'NAME' => 'Контакт',
                'CODE' => 'CONTACT',
                'PROPERTY_TYPE' => 'S',
                'USER_TYPE' => 'employee',
            ],
            [
                'NAME' => 'Модель',
                'CODE' => 'MODEL',
                'PROPERTY_TYPE' => 'S',
            ],
            [
                'NAME' => 'Год выпуска',
                'CODE' => 'YEAR',
                'PROPERTY_TYPE' => 'N',
            ],
            [
                'NAME' => 'Цвет',
                'CODE' => 'COLOR',
                'PROPERTY_TYPE' => 'S',
            ],
            [
                'NAME' => 'Пробег (км)',
                'CODE' => 'MILEAGE',
                'PROPERTY_TYPE' => 'N',
            ],
        ];

        foreach ($properties as $property) {
            $ibp = new \CIBlockProperty();
            $ibp->Add([
                'NAME' => $property['NAME'],
                'ACTIVE' => 'Y',
                'SORT' => '100',
                'CODE' => $property['CODE'],
                'PROPERTY_TYPE' => $property['PROPERTY_TYPE'],
                'USER_TYPE' => $property['USER_TYPE'] ?? '',
                'IBLOCK_ID' => $iblockId,
            ]);
        }
    }

    private static function deleteInfoblock()
    {
        $iblockCode = 'cars';
        $iblock = \Bitrix\Iblock\IblockTable::getList([
            'filter' => ['=CODE' => $iblockCode, '=IBLOCK_TYPE_ID' => 'lists'],
        ])->fetch();

        if ($iblock) {
            \CIBlock::Delete($iblock['ID']);
        }
    }

    private function clearCache()
    {
        $cacheDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache';
        $managedCacheDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/managed_cache';

        if (is_dir($cacheDir)) {
            array_map('unlink', glob("$cacheDir/*"));
        }

        if (is_dir($managedCacheDir)) {
            array_map('unlink', glob("$managedCacheDir/*"));
        }
    }




}
