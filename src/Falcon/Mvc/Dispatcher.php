<?php

declare(strict_types=1);

namespace Falcon\Mvc;

use Falcon\Di; // Предполагается, что у вас есть класс Falcon\Di
use Falcon\Exception; // Предполагается, что у вас есть базовый класс исключений Falcon\Exception
use Falcon\Mvc\Controller\ControllerBase; // Предполагается, что это ваш базовый класс для контроллеров

/**
 * Диспетчер отвечает за маршрутизацию входящих запросов к соответствующим контроллерам и действиям.
 * Он получает информацию о контроллере, действии и параметрах от Маршрутизатора
 * и вызывает соответствующий метод контроллера.
 */
class Dispatcher
{
    /**
     * @var Di|null Инжектор зависимостей.
     */
    protected ?Di $_dependencyInjector = null;

    /**
     * @var string|null Текущее полное имя класса контроллера (включая пространство имен).
     */
    protected ?string $_controllerName = null;

    /**
     * @var string|null Текущее имя действия.
     */
    protected ?string $_actionName = null;

    /**
     * @var array Параметры, передаваемые в действие.
     */
    protected array $_params = [];

    /**
     * @var ControllerBase|null Экземпляр текущего контроллера.
     */
    protected ?ControllerBase $_activeController = null;

    /**
     * Конструктор Диспетчера.
     *
     * @param Di|null $di Инжектор зависимостей (опционально, можно установить позже).
     */
    public function __construct(?Di $di = null)
    {
        if ($di !== null) {
            $this->setDI($di);
        }
    }

    /**
     * Устанавливает инжектор зависимостей.
     *
     * @param Di $dependencyInjector Объект инжектора зависимостей.
     * @return Dispatcher
     */
    public function setDI(Di $dependencyInjector): Dispatcher
    {
        $this->_dependencyInjector = $dependencyInjector;
        return $this;
    }

    /**
     * Возвращает инжектор зависимостей.
     *
     * @return Di|null
     */
    public function getDI(): ?Di
    {
        return $this->_dependencyInjector;
    }

    /**
     * Устанавливает полное имя класса контроллера, который будет диспетчеризован.
     * Это имя должно включать пространство имен (например, 'App\Controllers\IndexController').
     *
     * @param string $controllerName Полное имя класса контроллера.
     * @return Dispatcher
     */
    public function setControllerName(string $controllerName): Dispatcher
    {
        $this->_controllerName = $controllerName;
        return $this;
    }

    /**
     * Возвращает полное имя класса текущего контроллера.
     *
     * @return string|null
     */
    public function getControllerName(): ?string
    {
        return $this->_controllerName;
    }

    /**
     * Устанавливает имя действия, которое будет выполнено.
     *
     * @param string $actionName Имя действия.
     * @return Dispatcher
     */
    public function setActionName(string $actionName): Dispatcher
    {
        $this->_actionName = $actionName;
        return $this;
    }

    /**
     * Возвращает имя текущего действия.
     *
     * @return string|null
     */
    public function getActionName(): ?string
    {
        return $this->_actionName;
    }

    /**
     * Устанавливает параметры, которые будут переданы в действие.
     *
     * @param array $params Массив параметров.
     * @return Dispatcher
     */
    public function setParams(array $params): Dispatcher
    {
        $this->_params = $params;
        return $this;
    }

    /**
     * Возвращает все параметры, передаваемые в действие.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->_params;
    }

    /**
     * Возвращает отдельный параметр по его позиции или имени.
     *
     * @param int|string $index Позиция параметра (0-based index) или имя.
     * @param mixed $defaultValue Значение, возвращаемое, если параметр не найден.
     * @return mixed
     */
    public function getParam(int|string $index, mixed $defaultValue = null): mixed
    {
        return $this->_params[$index] ?? $defaultValue;
    }

    /**
     * Возвращает активный (текущий) экземпляр контроллера.
     *
     * @return ControllerBase|null
     */
    public function getActiveController(): ?ControllerBase
    {
        return $this->_activeController;
    }

    /**
     * Диспетчеризует запрос, находя и выполняя соответствующий контроллер и действие.
     *
     * @return mixed Результат выполнения действия контроллера.
     * @throws Exception Если инжектор зависимостей не установлен, контроллер не найден, действие не найдено или недоступно.
     */
    public function dispatch(): mixed
    {
        if ($this->_dependencyInjector === null) {
            throw new Exception("Инжектор зависимостей не установлен.");
        }

        // Диспетчер теперь полностью полагается на Router для получения имени контроллера и действия.
        // Имя контроллера уже содержит полное пространство имен.
        $controllerClassName = $this->_controllerName;
        $actionName = $this->_actionName;
        $params = $this->_params;

        if ($controllerClassName === null) {
            throw new Exception("Имя контроллера не установлено для диспетчеризации. Возможно, роутер не нашёл совпадения.");
        }
        if ($actionName === null) {
            throw new Exception("Имя действия не установлено для диспетчеризации. Возможно, роутер не нашёл совпадения.");
        }

        // Проверяем, существует ли класс контроллера
        if (!class_exists($controllerClassName)) {
            throw new Exception("Контроллер '{$controllerClassName}' не найден.");
        }

        // Создаем экземпляр контроллера. Предпочтительно из DI, если он там зарегистрирован.
        // Это позволяет DI автоматически внедрять зависимости в конструктор контроллера.
        try {
            $controller = $this->_dependencyInjector->getShared($controllerClassName);
        } catch (\Throwable $e) {
            // Если сервис не зарегистрирован или произошла ошибка при получении,
            // создаем его напрямую. Это менее предпочтительный сценарий для реального DI.
            $controller = new $controllerClassName();
            // Если контроллер является ControllerBase, передаем ему DI
            if ($controller instanceof ControllerBase) {
                $controller->setDI($this->_dependencyInjector);
            }
        }

        // Убеждаемся, что инстанциированный объект является экземпляром ControllerBase
        if (!($controller instanceof ControllerBase)) {
             throw new Exception("Класс контроллера '{$controllerClassName}' должен быть экземпляром " . ControllerBase::class);
        }

        $this->_activeController = $controller;

        // Вызываем метод onConstruct контроллера, если он был создан напрямую,
        // или если его не вызвал DI при создании. Это гарантирует, что DI уже установлен.
        if (method_exists($controller, 'onConstruct') && $controller->getDI() !== null) {
            $controller->onConstruct();
        }


        // Формируем имя метода действия (например, 'indexAction')
        $actionMethodName = lcfirst($actionName) . 'Action';

        // Проверяем существование метода действия в контроллере
        if (!method_exists($controller, $actionMethodName)) {
            throw new Exception("Действие '{$actionName}' (метод '{$actionMethodName}') не найдено в контроллере '{$controllerClassName}'.");
        }

        // Проверяем доступность метода (должен быть публичным)
        $reflectionMethod = new \ReflectionMethod($controller, $actionMethodName);
        if (!$reflectionMethod->isPublic()) {
            throw new Exception("Действие '{$actionName}' (метод '{$actionMethodName}') в контроллере '{$controllerClassName}' не является публичным.");
        }

        // Вызываем метод действия, передавая параметры
        $result = call_user_func_array([$controller, $actionMethodName], $params);

        return $result;
    }
}