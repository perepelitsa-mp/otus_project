<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Bizproc\Activity\BaseActivity;
use Bitrix\Bizproc\FieldType;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Localization\Loc;
use Bitrix\Bizproc\Activity\PropertiesDialog;

class CBPDadataActivity extends BaseActivity
{
    /**
     * Конструктор активности
     * @param string $name Название активности
     */
    public function __construct($name)
    {
        parent::__construct($name);

        $this->arProperties = [
            'Title' => '',
            'Inn' => '',
            'SaveToField' => '', // Поле для сохранения данных

            // return
            'CompanyData' => null,
        ];

        $this->SetPropertiesTypes([
            'CompanyData' => ['Type' => FieldType::STRING],
        ]);
    }

    /**
     * Возвращает путь к файлу активности
     * @return string
     */
    protected static function getFileName(): string
    {
        return __FILE__;
    }

    /**
     * Основная логика выполнения активности
     * @return ErrorCollection
     */
    protected function internalExecute(): ErrorCollection
    {
        $errors = parent::internalExecute();

        try {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[" . date("Y-m-d H:i:s") . "] Начало выполнения internalExecute" . PHP_EOL, FILE_APPEND);

            // Получение данных из Dadata
            $response = $this->fetchCompanyDataFromDadata($this->Inn);

            $companyName = 'Компания не найдена!';
            if (!empty($response['suggestions'])) {
                $companyName = $response['suggestions'][0]['value'];
            }

            $this->preparedProperties['Text'] = $companyName;

            // Работа с переменными бизнес-процесса
            $rootActivity = $this->GetRootActivity();
            $variableName = "dadata";

            // Выводим все доступные методы rootActivity
            $methods = get_class_methods($rootActivity);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[" . date("Y-m-d H:i:s") . "] Методы rootActivity: " . print_r($methods, true) . PHP_EOL, FILE_APPEND);

            // Проверка наличия метода setVariable
            if (method_exists($rootActivity, 'setVariable')) {
                $rootActivity->setVariable($variableName, $this->preparedProperties['Text']);
                $rootActivity->setVariable("zakazchik", $this->preparedProperties['Text']);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[" . date("Y-m-d H:i:s") . "] Данные успешно сохранены в переменную: {$variableName}" . PHP_EOL, FILE_APPEND);
            } else {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[" . date("Y-m-d H:i:s") . "] Ошибка: Метод setVariable отсутствует у rootActivity" . PHP_EOL, FILE_APPEND);
            }

        } catch (\Exception $e) {
            $errors->setError(new \Bitrix\Main\Error($e->getMessage()));
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[" . date("Y-m-d H:i:s") . "] Ошибка: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        return $errors;
    }







    /**
     * Запрос данных компании из DADATA
     * @param string $inn ИНН компании
     * @return array|null
     */
    protected function fetchCompanyDataFromDadata(string $inn): ?array
    {
        $apiKey = "0c825d0906122684951a7a3d60ee8848289d4344"; // Ваш токен
        $url = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party";

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                    "Authorization: Token {$apiKey}\r\n",
                'content' => json_encode(['query' => $inn]),
            ],
        ]);

        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/log.txt', "[" . date("Y-m-d H:i:s") . "] Ошибка при запросе к Dadata API" . PHP_EOL, FILE_APPEND);
            return null;
        }

        $response = json_decode($result, true);

        return $response;
    }


    /**
     * Карта свойств для отображения в диалоге настройки активности
     * @param PropertiesDialog|null $dialog
     * @return array[]
     */
    public static function getPropertiesDialogMap(?PropertiesDialog $dialog = null): array
    {
        return [
            'Inn' => [
                'Name' => "ИНН",
                'FieldName' => 'inn',
                'Type' => FieldType::STRING,
                'Required' => false,
                'Default' => '',
                'Options' => [],
            ],
            'SaveToField' => [
                'Name' => "Переменная для сохранения данных",
                'FieldName' => 'save_to_field',
                'Type' => FieldType::STRING,
                'Required' => false,
                'Default' => '',
                'Options' => [],
            ],
        ];
    }
}
?>


