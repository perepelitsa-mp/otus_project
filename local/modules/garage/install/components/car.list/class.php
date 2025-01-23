<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Application;

class GarageCarListComponent extends CBitrixComponent
{
    private $iblockId;

    public function onPrepareComponentParams($params)
    {
        $params['ENTITY_ID'] = (int)$params['ENTITY_ID'];
        return $params;
    }

    public function executeComponent()
    {
        $this->arParams['ENTITY_ID'] = $this->arParams['ENTITY_ID'] ?? null;

        if (!$this->arParams['ENTITY_ID']) {
            ShowError('Не указан ID контакта.');
            return;
        }

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль "Инфоблоки" не подключен.');
            return;
        }

        $this->iblockId = IblockTable::getList([
            'filter' => ['=CODE' => 'cars'],
        ])->fetch()['ID'];

        if (!$this->iblockId) {
            ShowError('Инфоблок "Автомобили" не найден.');
            return;
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', 'Проверка записи в файл' . PHP_EOL);
        $request = Application::getInstance()->getContext()->getRequest();
        if ($request->isAjaxRequest()) {
            $this->processAjaxRequest();
        } else {
            $this->processRequest();
            $this->arResult['CARS'] = $this->getCars();

            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', 'Шаблон вызывается' . PHP_EOL, FILE_APPEND);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug.log', print_r($this->arResult, true) . PHP_EOL, FILE_APPEND);

            $nav = new \Bitrix\Main\UI\PageNavigation("car-list");
            $nav->allowAllRecords(true)
                ->setPageSize(10)
                ->initFromUri();

            $res = \CIBlockElement::GetList(
                ['ID' => 'DESC'],
                ['IBLOCK_ID' => $this->iblockId, 'PROPERTY_CONTACT' => $this->arParams['ENTITY_ID']],
                false,
                ['nPageSize' => $nav->getLimit(), 'iNumPage' => $nav->getCurrentPage()],
                ['ID', 'NAME', 'PROPERTY_MODEL', 'PROPERTY_YEAR', 'PROPERTY_COLOR', 'PROPERTY_MILEAGE']
            );

            $cars = [];
            while ($car = $res->Fetch()) {
                $cars[] = $car;
            }
            $this->arResult['CARS'] = $cars;
            $this->arResult['NAVIGATION'] = $nav;
            $this->includeComponentTemplate();
        }
    }


    private function processAjaxRequest()
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $action = $request->get('action');

        if ($action === 'getCars') {
            $this->arResult['CARS'] = $this->getCars();

            // Буферизируем вывод шаблона
            ob_start();
            $this->includeComponentTemplate();
            $html = ob_get_clean();

            // Возвращаем только HTML
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
        } else {
            // Возвращаем ошибку в формате JSON
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Неизвестное действие.']);
        }

        die(); // Завершаем выполнение
    }


    private function getCars()
    {
        $entityId = $this->arParams['ENTITY_ID'];
        $cars = [];

        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->iblockId, 'PROPERTY_CONTACT' => $entityId],
            false,
            false,
            ['ID', 'NAME', 'PROPERTY_MODEL', 'PROPERTY_YEAR', 'PROPERTY_COLOR', 'PROPERTY_MILEAGE']
        );

        while ($car = $res->Fetch()) {
            $cars[] = $car;
        }

        return $cars;
    }

    private function processRequest()
    {
        $request = Application::getInstance()->getContext()->getRequest();

        if ($request->isPost() && check_bitrix_sessid()) {
            $carData = [
                'NAME' => $request->getPost('CAR_NAME'),
                'PROPERTY_VALUES' => [
                    'CONTACT' => $this->arParams['ENTITY_ID'],
                    'MODEL' => $request->getPost('CAR_MODEL'),
                    'YEAR' => $request->getPost('CAR_YEAR'),
                    'COLOR' => $request->getPost('CAR_COLOR'),
                    'MILEAGE' => $request->getPost('CAR_MILEAGE'),
                ],
                'IBLOCK_ID' => $this->iblockId,
                'ACTIVE' => 'Y',
            ];

            $el = new \CIBlockElement();
            if ($el->Add($carData)) {
                LocalRedirect($request->getRequestUri());
            } else {
                ShowError('Ошибка добавления автомобиля.');
            }
        }
    }
}
