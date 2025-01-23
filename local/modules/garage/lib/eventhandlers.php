<?php
namespace Garage;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Component\ParameterSigner;
use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;
class eventhandlers
{
    /**
     * Обработчик события инициализации вкладок деталей сущности crm .
     *
     * @param Event $event Событие инициализации вкладок.
     * @return EventResult Результат обработки события.
     */
    public static function onEntityDetailsTabsInitialized(Event $event)
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_tabs.log', 'onEntityDetailsTabsInitialized вызван' . PHP_EOL, FILE_APPEND | LOCK_EX);
        $params = $event->getParameters();
        $entityId = $params['entityID'] ?? null;
        $entityTypeID = $params['entityTypeID'] ?? null;
        $tabs = &$params['tabs'];

        if (!$entityId || $entityTypeID != \CCrmOwnerType::Contact) {
            return new EventResult(EventResult::SUCCESS);
        }

        // Параметры компонента
        $componentParams = [
            'ENTITY_ID' => $entityId,
        ];
        $signedParams = ParameterSigner::signParameters('garage:car.list', $componentParams);

        $tabs[] = [
            'id' => 'garage_tab',
            'name' => 'Гараж',
            'loader' => [
                'serviceUrl' => '/bitrix/components/garage/car.list/ajax.php?' . http_build_query([
                        'site_id' => SITE_ID,
                        'sessid' => bitrix_sessid(),
                        'ENTITY_ID' => $entityId,
                        'action' => 'getCars',
                        'signedParameters' => $signedParams
                    ]),
                'componentData' => [
                    'componentName' => 'garage:car.list',
                    'template' => '',
                    'signedParameters' => $signedParams,
                ],
            ],
        ];

        return new EventResult(EventResult::SUCCESS, $params);
    }


}

