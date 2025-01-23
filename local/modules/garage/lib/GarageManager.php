<?php

namespace Garage;

use Bitrix\Main\Loader;
use Bitrix\Crm\ContactTable;
use Bitrix\Crm\CompanyTable;

class GarageManager
{
    /**
     * Получает данные гаража по ID контакта.
     *
     * @param int $contactId ID контакта.
     * @return mixed Данные о гараже.
     */
    public static function getGarageData($contactId)
    {
        Loader::includeModule('crm');

        $contact = ContactTable::getById($contactId)->fetch();
        return $contact['UF_CRM_GARAGE'];
    }

    /**
     * Устанавливает данные гаража для контакта.
     *
     * @param int $contactId ID контакта.
     * @param mixed $carData Данные о машине.
     * @return bool Результат операции.
     */
    public static function setGarageData($contactId, $carData)
    {
        Loader::includeModule('crm');

        $result = ContactTable::update($contactId, [
            'UF_CRM_GARAGE' => $carData,
        ]);

        return $result->isSuccess();
    }
}
