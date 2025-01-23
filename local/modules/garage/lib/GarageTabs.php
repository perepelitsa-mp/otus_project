<?php

namespace Garage;

use Bitrix\Main\Loader;

class GarageTabs
{
    /**
     * Добавляет новую вкладку "Гараж" на страницу контакта в CRM.
     *
     * @param array &$tabs Список вкладок для страницы контакта.
     */
    public static function onCrmContactDetailsTabs(&$tabs)
    {
        $tabs[] = [
            'id' => 'garage',
            'name' => 'Гараж',
            'title' => 'Список автомобилей',
            'enabled' => true,
            'sort' => 100,
            'html' => self::getTabHtml(),
        ];
    }

    /**
     * Получает HTML-содержимое для вкладки "Гараж".
     *
     * @return string Содержимое вкладки в формате HTML.
     */
    private static function getTabHtml()
    {
        ob_start();
        require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/garage/admin/contact_garage.php';
        return ob_get_clean();
    }
}
