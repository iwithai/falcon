<?php

declare(strict_types=1);

namespace Falcon\Mvc\Controller;

use Falcon\Di;
use Falcon\Http\Response;
use Falcon\Mvc\View;
use Falcon\Mvc\Url;
use Falcon\Mvc\Dispatcher;
use Falcon\Exception; // Добавляем для возможных исключений из Di

/**
 * Базовый класс для всех контроллеров в приложении Falcon MVC.
 * Предоставляет удобный доступ к общим сервисам через инжектор зависимостей.
 */
abstract class ControllerBase
{
    /**
     * @var Di|null Инжектор зависимостей. Кэшируется после первого получения.
     */
    protected ?Di $_dependencyInjector = null;

    /**
     * @var Response|null Объект HTTP-ответа. Кэшируется после первого получения.
     */
    protected ?Response $_response = null;

    /**
     * @var View|null Объект для управления представлениями. Кэшируется после первого получения.
     */
    protected ?View $_view = null;

    /**
     * @var Url|null Объект для генерации URL. Кэшируется после первого получения.
     */
    protected ?Url $_url = null;

    /**
     * @var Dispatcher|null Объект диспетчера. Кэшируется после первого получения.
     */
    protected ?Dispatcher $_dispatcher = null;

    /**
     * Устанавливает инжектор зависимостей для контроллера.
     * Этот метод вызывается Диспетчером.
     *
     * @param Di $di Объект инжектора зависимостей.
     * @return void
     */
    public function setDI(Di $di): void
    {
        $this->_dependencyInjector = $di;
    }

    /**
     * Возвращает инжектор зависимостей.
     * Если инжектор не был установлен через setDI, пытается получить дефолтный.
     *
     * @return Di|null
     */
    public function getDI(): ?Di
    {
        if ($this->_dependencyInjector === null) {
            $this->_dependencyInjector = Di::getDefault();
        }
        return $this->_dependencyInjector;
    }

    /**
     * Возвращает сервис HTTP-ответа.
     * Получает его из DI и кэширует.
     *
     * @return Response
     * @throws Exception Если сервис 'response' не зарегистрирован в DI.
     */
    protected function getResponse(): Response
    {
        if ($this->_response === null) {
            $di = $this->getDI();
            if ($di === null || !$di->hasShared('response')) {
                throw new Exception("Сервис 'response' не доступен в DI.");
            }
            $this->_response = $di->getShared('response');
        }
        return $this->_response;
    }

    /**
     * Возвращает сервис для управления представлениями (View).
     * Получает его из DI и кэширует.
     *
     * @return View
     * @throws Exception Если сервис 'view' не зарегистрирован в DI.
     */
    protected function getView(): View
    {
        if ($this->_view === null) {
            $di = $this->getDI();
            if ($di === null || !$di->hasShared('view')) {
                throw new Exception("Сервис 'view' не доступен в DI.");
            }
            $this->_view = $di->getShared('view');
        }
        return $this->_view;
    }

    /**
     * Возвращает сервис для генерации URL.
     * Получает его из DI и кэширует.
     *
     * @return Url
     * @throws Exception Если сервис 'url' не зарегистрирован в DI.
     */
    protected function getUrl(): Url
    {
        if ($this->_url === null) {
            $di = $this->getDI();
            if ($di === null || !$di->hasShared('url')) {
                throw new Exception("Сервис 'url' не доступен в DI.");
            }
            $this->_url = $di->getShared('url');
        }
        return $this->_url;
    }

    /**
     * Возвращает сервис Диспетчера.
     * Получает его из DI и кэширует.
     *
     * @return Dispatcher
     * @throws Exception Если сервис 'dispatcher' не зарегистрирован в DI.
     */
    protected function getDispatcher(): Dispatcher
    {
        if ($this->_dispatcher === null) {
            $di = $this->getDI();
            if ($di === null || !$di->hasShared('dispatcher')) {
                throw new Exception("Сервис 'dispatcher' не доступен в DI.");
            }
            $this->_dispatcher = $di->getShared('dispatcher');
        }
        return $this->_dispatcher;
    }

    /**
     * Магический метод `__get` для удобного доступа к сервисам через свойства.
     * Позволяет писать `$this->response` вместо `$this->getResponse()`.
     *
     * @param string $property Имя свойства (сервиса).
     * @return mixed
     * @throws Exception Если запрошенное свойство не является известным сервисом или не найдено.
     */
    public function __get(string $property): mixed
    {
        // Преобразуем имя свойства в имя геттера (например, 'response' -> 'getResponse')
        $methodName = 'get' . ucfirst($property);

        // Проверяем, существует ли соответствующий геттер
        if (method_exists($this, $methodName)) {
            return $this->$methodName();
        }

        // Если это не известный сервис и нет геттера, бросаем исключение
        throw new Exception("Неизвестное свойство или сервис '{$property}' в контроллере " . get_class($this));
    }

    /**
     * Вызывается после создания экземпляра контроллера и установки DI,
     * но до выполнения любого действия.
     * Можно использовать для инициализации или проверок.
     *
     * @return void
     */
    public function onConstruct(): void
    {
        // Метод-заглушка для инициализации в дочерних классах
    }
}
