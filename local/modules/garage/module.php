<?php

class garage extends CModule
{
    public $MODULE_ID = 'garage';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/version.php');

        $this->MODULE_ID = 'garage';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'Модуль Гараж';
        $this->MODULE_DESCRIPTION = 'Модуль для управления автомобилями клиентов в CRM.';
    }

}
