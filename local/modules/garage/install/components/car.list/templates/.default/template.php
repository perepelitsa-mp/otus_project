<?php

/**
 * Обрабатывает нажатие на карточку автомобиля, открывая модальное окно с историей обращений.
 *
 * @param HTMLElement card Карточка автомобиля.
 * @param string carId Идентификатор автомобиля (data-атрибут).
 * @param string modalContent Модальный элемент.
 * @param HTMLElement modal Содержимое модального окна.
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

\Bitrix\Main\UI\Extension::load(['ui.forms', 'ui.buttons', 'ui.alerts']);
?>

<div id="garage-tab">
    <?php if (!empty($arResult['CARS'])): ?>
        <div class="car-cards-container">
            <?php foreach ($arResult['CARS'] as $car): ?>
                <div class="car-card" data-car-id="<?= htmlspecialchars($car['ID']) ?>">
                    <div class="car-card-header">
                        <h3 class="car-name"><?= htmlspecialchars($car['NAME']) ?></h3>
                    </div>
                    <div class="car-card-body">
                        <p><strong>Модель:</strong> <?= htmlspecialchars($car['PROPERTY_MODEL_VALUE']) ?></p>
                        <p><strong>Год выпуска:</strong> <?= htmlspecialchars($car['PROPERTY_YEAR_VALUE']) ?></p>
                        <p><strong>Цвет:</strong> <?= htmlspecialchars($car['PROPERTY_COLOR_VALUE']) ?></p>
                        <p><strong>Пробег:</strong> <?= htmlspecialchars($car['PROPERTY_MILEAGE_VALUE']) ?> км</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="ui-alert ui-alert-warning">
            <span class="ui-alert-message">Нет автомобилей, связанных с этим клиентом.</span>
        </div>
    <?php endif; ?>

    <button class="ui-btn ui-btn-success" id="add-car-btn">Добавить авто</button>

    <form id="add-car-form" method="post" style="display:none; margin-top: 20px;">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="ENTITY_ID" value="<?= htmlspecialchars($arParams['ENTITY_ID']) ?>">
        <div>
            <label>Название:</label>
            <input type="text" name="CAR_NAME" required>
        </div>
        <div>
            <label>Модель:</label>
            <input type="text" name="CAR_MODEL" required>
        </div>
        <div>
            <label>Год выпуска:</label>
            <input type="number" name="CAR_YEAR" required>
        </div>
        <div>
            <label>Цвет:</label>
            <input type="text" name="CAR_COLOR" required>
        </div>
        <div>
            <label>Пробег (км):</label>
            <input type="number" name="CAR_MILEAGE" required>
        </div>
        <button type="submit" class="ui-btn ui-btn-primary">Сохранить</button>
        <button type="button" class="ui-btn ui-btn-link" id="cancel-add-car">Отмена</button>
    </form>
</div>

<div id="modal-overlay"></div>
<div id="car-modal">
    <div class="modal-content">
        <h3>История обращений</h3>
        <div id="car-modal-content">Загрузка данных...</div>
        <button class="ui-btn ui-btn-primary" id="close-modal">Закрыть</button>
    </div>
</div>

<script>

    /**
     * Открывает модальное окно с историей обращений автомобиля.
     *
     * @param {string} carId Идентификатор автомобиля
     * @returns {void}
     */
    document.querySelectorAll('.car-card').forEach(card => {
        card.addEventListener('click', function () {
            const carId = this.dataset.carId; // Получаем ID авто из data-атрибута
            const modal = document.getElementById('car-modal');
            const modalContent = document.getElementById('car-modal-content');
            const overlay = document.getElementById('modal-overlay');

            // Проверяем наличие carId
            if (!carId) {
                console.error('carId is not defined');
                return;
            }

            // Показать модальное окно и затемнение
            modal.style.display = 'block';
            overlay.style.display = 'block';

            // Показать сообщение о загрузке
            modalContent.innerHTML = `<p>История обращений для автомобиля: <strong>${carId}</strong></p><p>Загрузка данных...</p>`;

            /**
             * Отправляет AJAX-запрос для получения истории обращений автомобиля.
             *
             * @param {string} carId Идентификатор автомобиля
             * @param {HTMLElement} modalContent Элемент модального окна для отображения данных
             * @returns {void}
             */
            fetch('/local/hooks/car_history.php', {
                method: 'POST',
                body: new URLSearchParams({
                    carId: carId, // Передаём ID автомобиля
                    sessid: BX.bitrix_sessid() // CSRF-защита
                })
            })
                .then(response => {
                    console.log('Raw response:', response);
                    return response.text(); // Временно читаем как текст
                })
                .then(text => {
                    console.log('Response text:', text); // Логируем сырой ответ
                    return JSON.parse(text); // Затем парсим как JSON
                })
                .then(data => {
                    if (data.status === 'success' && data.data.length > 0) {

                        /**
                         * Генерирует HTML-код таблицы с историей обращений автомобиля.
                         *
                         * @param {Array} data Массив данных истории обращений
                         * @returns {string} HTML-код таблицы
                         */

                        const historyHtml = `
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Название сделки</th>
                                        <th>Дата</th>
                                        <th>Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.data.map(deal => `
                                        <tr>
                                            <td>${deal.TITLE}</td>
                                            <td>${deal.DATE_CREATE}</td>
                                            <td>${deal.OPPORTUNITY} руб.</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                        modalContent.innerHTML = `<p>История обращений для автомобиля: <strong>${carId}</strong></p>${historyHtml}`;
                    } else if (data.status === 'error') {
                        modalContent.innerHTML = `<p>Ошибка: ${data.message}</p>`;
                    } else {
                        modalContent.innerHTML = `<p>История обращений для автомобиля: <strong>${carId}</strong></p><p>Нет данных для отображения.</p>`;
                    }
                })
                .catch(error => {
                    console.error('Ошибка загрузки истории:', error);
                    modalContent.innerHTML = `<p>Ошибка загрузки данных. Попробуйте позже.</p>`;
                });

        });
    });


    /**
     * Закрывает модальное окно и затемнение.
     *
     * @returns {void}
     */
    document.getElementById('close-modal').addEventListener('click', function () {
        const modal = document.getElementById('car-modal');
        const overlay = document.getElementById('modal-overlay');
        modal.style.display = 'none';
        overlay.style.display = 'none';
    });

    // Закрытие модального окна при клике на затемнение
    document.getElementById('modal-overlay').addEventListener('click', function () {
        const modal = document.getElementById('car-modal');
        const overlay = document.getElementById('modal-overlay');
        modal.style.display = 'none';
        overlay.style.display = 'none';
    });


    document.getElementById('add-car-btn').addEventListener('click', function () {
        document.getElementById('add-car-form').style.display = 'block';
    });

    document.getElementById('cancel-add-car').addEventListener('click', function () {
        document.getElementById('add-car-form').style.display = 'none';
    });

    document.getElementById('add-car-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'addCar');

        fetch('<?= $componentPath ?>/ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.message || 'Ошибка добавления автомобиля.');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при добавлении автомобиля.');
            });
    });
</script>

<style>
    #garage-tab {
        padding: 20px;
        font-family: Arial, sans-serif;
    }

    .car-cards-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 20px;
    }

    .car-card {
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        width: 300px;
        padding: 15px;
        transition: box-shadow 0.2s ease;
        cursor: pointer;
    }

    .car-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .car-card-header {
        border-bottom: 1px solid #ddd;
        margin-bottom: 10px;
    }

    .car-name {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }

    .car-card-body p {
        margin: 5px 0;
        font-size: 14px;
        color: #555;
    }

    #add-car-form {
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        max-width: 400px;
        margin-top: 20px;
    }

    #add-car-form label {
        font-weight: bold;
        margin-bottom: 5px;
        display: block;
    }

    #add-car-form input {
        width: 100%;
        padding: 8px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    #car-modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        width: 400px;
        display: none; /* По умолчанию скрываем модальное окно */
    }

    #car-modal h3 {
        margin-top: 0;
        font-size: 18px;
        color: #333;
    }

    #car-modal #car-modal-content {
        margin-top: 15px;
        font-size: 14px;
        color: #555;
        max-height: 200px;
        overflow-y: auto;
    }

    #car-modal .ui-btn-primary {
        margin-top: 15px;
    }

    /* Затемнение фона при открытом модальном окне */
    #modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none; /* По умолчанию скрываем затемнение */
    }
    .history-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        font-size: 14px;
    }

    .history-table th, .history-table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }

    .history-table th {
        background-color: #f4f4f4;
        font-weight: bold;
        color: #333;
    }

    .history-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .history-table tr:hover {
        background-color: #f1f1f1;
    }
</style>

