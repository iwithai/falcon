<?php

namespace Falcon\Mvc;

use Falcon\Di;
use Falcon\Http\Request;
use Falcon\Http\Response;

/**
 * Базовый класс для всех контроллеров приложения.
 * Предоставляет доступ к общим сервисам через DI-контейнер.
 */
abstract class Controller
{
    /**
     * @var Di Контейнер внедрения зависимостей
     */
    protected $di;

    /**
     * @var Request Объект HTTP-запроса
     */
    protected $request;

    /**
     * @var Response Объект HTTP-ответа
     */
    protected $response;

    /**
     * @var View Объект представления
     */
    protected $view;

    /**
     * Устанавливает DI-контейнер для контроллера.
     * Вызывается фреймворком после создания экземпляра контроллера.
     *
     * @param Di $di
     */
    public function setDi(Di $di)
    {
        $this->di = $di;
        // Автоматически получаем общие сервисы из DI-контейнера
        $this->request = $di->getShared('request');
        $this->response = $di->getShared('response');
        $this->view = $di->getShared('view');
    }

    /**
     * Возвращает DI-контейнер.
     *
     * @return Di
     */
    public function getDi(): Di
    {
        return $this->di;
    }

    /**
     * Вспомогательный метод для перенаправления пользователя.
     *
     * @param string $url URL для перенаправления
     * @param int $statusCode Код состояния перенаправления (по умолчанию 302 Found)
     */
    protected function redirect($url, $statusCode = 302)
    {
        $this->response->redirect($url, $statusCode);
    }

    /**
     * Вспомогательный метод для отправки JSON-ответа.
     *
     * @param mixed $data Данные для кодирования в JSON
     * @param int $statusCode HTTP-код состояния (по умолчанию 200 OK)
     * @return Response
     */
    protected function jsonResponse($data, $statusCode = 200): Response
    {
        return $this->response->setStatusCode($statusCode)->setJsonContent($data);
    }
}
