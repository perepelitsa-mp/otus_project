<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Bizproc\Activity\BaseActivity;
use Bitrix\Bizproc\FieldType;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Bizproc\Activity\PropertiesDialog;

class CBPCreateCompanyActivity extends BaseActivity
{
    /**
     * @see parent::__construct()
     * @param string $name Activity name
     */
    public function __construct($name)
    {
        parent::__construct($name);

        $this->arProperties = [
            'Title' => '',
            'Inn' => '',
            'CompanyName' => '',
            'Phone' => '',
            'DealId' => '',

            // return
            'CompanyId' => null,
        ];

        $this->SetPropertiesTypes([
            'CompanyId' => ['Type' => FieldType::INT],
        ]);
    }

    protected static function getFileName(): string
    {
        return __FILE__;
    }

    protected function internalExecute(): ErrorCollection
    {
        $errors = parent::internalExecute();

        if (!Loader::includeModule('crm')) {
            $errors->setError(new \Bitrix\Main\Error("Модуль CRM не подключен."));
            return $errors;
        }

        $inn = trim($this->Inn);
        $companyName = trim($this->CompanyName);
        $phone = trim($this->Phone);
        $dealId = (int) trim($this->DealId);

        if (empty($inn)) {
            $errors->setError(new \Bitrix\Main\Error("ИНН не указан."));
            return $errors;
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "Поиск компании по ИНН: {$inn}" . PHP_EOL, FILE_APPEND);

        // Поиск компании по ИНН
        $companyId = $this->findCompanyByInn($inn);

        if ($companyId) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "Компания найдена, ID: {$companyId}" . PHP_EOL, FILE_APPEND);
        } else {
            // Создаём новую компанию
            $companyId = $this->createCompany($companyName, $inn, $phone);
            if ($companyId) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "Создана новая компания, ID: {$companyId}" . PHP_EOL, FILE_APPEND);
            } else {
                $errors->setError(new \Bitrix\Main\Error("Ошибка создания компании."));
                return $errors;
            }
        }

        $this->preparedProperties['CompanyId'] = $companyId;

        // Если ID сделки не передан — создаём новую сделку
        if ($dealId <= 0) {
            $dealId = $this->createDeal($companyId);
        } else {
            $this->updateDealCustomer($dealId, $companyId);
        }

        return $errors;
    }

    /**
     * Создание новой сделки в CRM
     * @param int $companyId
     * @return int|null
     */
    private function createDeal(int $companyId): ?int
    {
        $fields = [
            'TITLE' => "Сделка для компании ID {$companyId}",
            'COMPANY_ID' => $companyId,
            'STAGE_ID' => 'NEW',
            'OPENED' => 'Y',
            'ASSIGNED_BY_ID' => 1,
        ];

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[CREATE_DEAL] Попытка создать сделку: " . print_r($fields, true) . PHP_EOL, FILE_APPEND);

        $deal = new \CCrmDeal(false);
        $dealId = $deal->Add($fields);

        if (!$dealId) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[CREATE_DEAL_ERROR] Ошибка создания сделки: " . $deal->LAST_ERROR . PHP_EOL, FILE_APPEND);
            return null;
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[CREATE_DEAL_SUCCESS] Сделка создана с ID: {$dealId}" . PHP_EOL, FILE_APPEND);
        return $dealId;
    }


    /**
     * Поиск компании по ИНН в CRM
     * @param string $inn
     * @return int|null
     */
    private function findCompanyByInn(string $inn): ?int
    {
        if (empty($inn)) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[FIND_COMPANY] Ошибка: передан пустой ИНН!" . PHP_EOL, FILE_APPEND);
            return null;
        }

        // Получаем список компаний с заполненным ИНН
        $filter = ['!=UF_CRM_1738289639881' => ''];
        $select = ['ID', 'TITLE', 'UF_CRM_1738289639881'];

        $dbResult = \CCrmCompany::GetList([], $filter, $select);

        while ($company = $dbResult->Fetch()) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[CHECK_COMPANY] Проверяем компанию: ID {$company['ID']}, Название: {$company['TITLE']}, ИНН: {$company['UF_CRM_1738289639881']}" . PHP_EOL, FILE_APPEND);

            if (trim($company['UF_CRM_1738289639881']) === $inn) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[FIND_COMPANY] Найдена компания: ID {$company['ID']}, Название: {$company['TITLE']}, ИНН: {$company['UF_CRM_1738289639881']}" . PHP_EOL, FILE_APPEND);
                return (int) $company['ID'];
            }
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[FIND_COMPANY] Компания с ИНН {$inn} не найдена!" . PHP_EOL, FILE_APPEND);
        return null;
    }

    /**
     * Создание новой компании в CRM
     * @param string $name
     * @param string $inn
     * @param string $phone
     * @return int|null
     */
    private function createCompany(string $name, string $inn, string $phone): ?int
    {
        $fields = [
            'TITLE' => !empty($name) ? $name : "Компания с ИНН {$inn}",
            'UF_CRM_1738289639881' => $inn,
            'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
        ];

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[CREATE_COMPANY] Попытка создать компанию: " . print_r($fields, true) . PHP_EOL, FILE_APPEND);

        $company = new \CCrmCompany(false);
        $result = $company->Add($fields);

        if (!$result) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[CREATE_COMPANY_ERROR] Ошибка создания компании: " . $company->LAST_ERROR . PHP_EOL, FILE_APPEND);
            return null;
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[CREATE_COMPANY_SUCCESS] Компания создана с ID: {$result}" . PHP_EOL, FILE_APPEND);
        return $result;
    }


    /**
     * Обновление поля "Заказчик" в сделке или другом документе CRM
     * @param int $dealId
     * @param int $companyId
     */
    private function updateDealCustomer(int $dealId, int $companyId): void
    {
        $deal = new \CCrmDeal(false);
        $fields = [
            'COMPANY_ID' => $companyId,
        ];

        $result = $deal->Update($dealId, $fields);

        if ($result) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "Обновлена сделка ID: {$dealId}, установлен заказчик: {$companyId}" . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "Ошибка обновления сделки ID: {$dealId}" . PHP_EOL, FILE_APPEND);
        }
    }

    public static function getPropertiesDialogMap(?PropertiesDialog $dialog = null): array
    {
        return [
            'Inn' => [
                'Name' => "ИНН",
                'FieldName' => 'inn',
                'Type' => FieldType::STRING,
                'Required' => true,
                'Default' => '',
                'Options' => [],
            ],
            'CompanyName' => [
                'Name' => "Название компании",
                'FieldName' => 'company_name',
                'Type' => FieldType::STRING,
                'Required' => true,
                'Default' => '',
                'Options' => [],
            ],
            'Phone' => [
                'Name' => "Телефон",
                'FieldName' => 'phone',
                'Type' => FieldType::STRING,
                'Required' => false,
                'Default' => '',
                'Options' => [],
            ],
            'DealId' => [
                'Name' => "ID сделки",
                'FieldName' => 'deal_id',
                'Type' => FieldType::INT,
                'Required' => false,
                'Default' => '',
                'Options' => [],
            ],
        ];
    }


}
