<?php

declare(strict_types=1);

namespace Falcon\Mvc;

use Falcon\Exception;
use Falcon\Mvc\Router\Route;

/**
 * Маршрутизатор отвечает за сопоставление входящих URL-адресов
 * с определенными контроллерами и действиями.
 */
class Router
{
    /**
     * @var array Массив зарегистрированных маршрутов.
     */
    protected array $_routes = [];

    /**
     * @var Route|null Последний совпавший маршрут.
     */
    protected ?Route $_matchedRoute = null;

    /**
     * @var string|null Имя контроллера, извлеченное из маршрута.
     */
    protected ?string $_controllerName = null;

    /**
     * @var string|null Имя действия, извлеченное из маршрута.
     */
    protected ?string $_actionName = null;

    /**
     * @var array Параметры, извлеченные из маршрута.
     */
    protected array $_params = [];

    /**
     * @var string|null Пространство имен по умолчанию для маршрутов.
     */
    protected ?string $_defaultNamespace = null; // Новое свойство

    /**
     * Конструктор маршрутизатора.
     */
    public function __construct()
    {
        // Возможно, здесь можно загрузить маршруты из файла по умолчанию
    }

    /**
     * Устанавливает пространство имен по умолчанию, которое будет добавляться к контроллерам.
     *
     * @param string $namespace Пространство имен (например, 'App\Controllers\').
     * @return Router
     */
    public function setDefaultNamespace(string $namespace): Router
    {
        $this->_defaultNamespace = rtrim($namespace, '\\') . '\\';
        return $this;
    }

    /**
     * Возвращает пространство имен по умолчанию.
     *
     * @return string|null
     */
    public function getDefaultNamespace(): ?string
    {
        return $this->_defaultNamespace;
    }

    /**
     * Добавляет новый маршрут GET-запроса.
     *
     * @param string $pattern Шаблон URL.
     * @param string|array $paths Определение контроллера/действия.
     * @param string|null $name Имя маршрута.
     * @return Route
     */
    public function get(string $pattern, string|array $paths, ?string $name = null): Route
    {
        return $this->addRoute($pattern, $paths, ['GET'], $name);
    }

    /**
     * Добавляет новый маршрут POST-запроса.
     *
     * @param string $pattern Шаблон URL.
     * @param string|array $paths Определение контроллера/действия.
     * @param string|null $name Имя маршрута.
     * @return Route
     */
    public function post(string $pattern, string|array $paths, ?string $name = null): Route
    {
        return $this->addRoute($pattern, $paths, ['POST'], $name);
    }

    /**
     * Добавляет новый маршрут PUT-запроса.
     *
     * @param string $pattern Шаблон URL.
     * @param string|array $paths Определение контроллера/действия.
     * @param string|null $name Имя маршрута.
     * @return Route
     */
    public function put(string $pattern, string|array $paths, ?string $name = null): Route
    {
        return $this->addRoute($pattern, $paths, ['PUT'], $name);
    }

    /**
     * Добавляет новый маршрут DELETE-запроса.
     *
     * @param string $pattern Шаблон URL.
     * @param string|array $paths Определение контроллера/действия.
     * @param string|null $name Имя маршрута.
     * @return Route
     */
    public function delete(string $pattern, string|array $paths, ?string $name = null): Route
    {
        return $this->addRoute($pattern, $paths, ['DELETE'], $name);
    }

    /**
     * Добавляет новый маршрут, поддерживающий все HTTP-методы.
     *
     * @param string $pattern Шаблон URL.
     * @param string|array $paths Определение контроллера/действия.
     * @param string|null $name Имя маршрута.
     * @return Route
     */
    public function add(string $pattern, string|array $paths, ?string $name = null): Route
    {
        return $this->addRoute($pattern, $paths, null, $name); // null означает все методы
    }

    /**
     * Добавляет новый маршрут с указанными HTTP-методами.
     *
     * @param string $pattern Шаблон URL.
     * @param string|array $paths Определение контроллера/действия.
     * @param array|null $methods Массив поддерживаемых HTTP-методов (например, ['GET', 'POST']). Null для всех методов.
     * @param string|null $name Имя маршрута.
     * @return Route
     */
    public function addRoute(string $pattern, string|array $paths, ?array $methods = null, ?string $name = null): Route
    {
        // Передаем дефолтное пространство имен в Route
        $route = new Route($pattern, $paths, $methods, $name, $this->_defaultNamespace);
        $this->_routes[] = $route;
        return $route;
    }

    /**
     * Возвращает все зарегистрированные маршруты.
     *
     * @return array<Route>
     */
    public function getRoutes(): array
    {
        return $this->_routes;
    }

    /**
     * Сопоставляет URI с зарегистрированными маршрутами.
     *
     * @param string $uri Входящий URI для сопоставления.
     * @param string $httpMethod Входящий HTTP-метод.
     * @return bool True, если маршрут найден и сопоставлен, false в противном случае.
     */
    public function handle(string $uri, string $httpMethod): bool
    {
        $this->_matchedRoute = null;
        $this->_controllerName = null;
        $this->_actionName = null;
        $this->_params = [];

        // Удаляем Query String из URI
        $uri = strtok($uri, '?');
        $uri = '/' . trim($uri, '/'); // Нормализуем URI (например, /my/path)

        foreach ($this->_routes as $route) {
            // Проверяем HTTP-метод
            if ($route->getMethods() !== null && !in_array(strtoupper($httpMethod), $route->getMethods())) {
                continue;
            }

            if ($route->match($uri)) {
                $this->_matchedRoute = $route;
                // Получаем полное имя контроллера, включая пространство имен, из маршрута
                $this->_controllerName = $route->getControllerName();
                $this->_actionName = $route->getActionName();
                $this->_params = $route->getParams();
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает последний совпавший маршрут.
     *
     * @return Route|null
     */
    public function getMatchedRoute(): ?Route
    {
        return $this->_matchedRoute;
    }

    /**
     * Возвращает имя контроллера из последнего совпавшего маршрута (включая пространство имен).
     *
     * @return string|null
     */
    public function getControllerName(): ?string
    {
        return $this->_controllerName;
    }

    /**
     * Возвращает имя действия из последнего совпавшего маршрута.
     *
     * @return string|null
     */
    public function getActionName(): ?string
    {
        return $this->_actionName;
    }

    /**
     * Возвращает параметры из последнего совпавшего маршрута.
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
     * Генерирует URL по имени маршрута и параметрам.
     *
     * @param string $name Имя маршрута.
     * @param array $params Ассоциативный массив параметров для заполнения шаблона.
     * @return string Сгенерированный URL.
     * @throws Exception Если маршрут с таким именем не найден или параметры не соответствуют.
     */
    public function generate(string $name, array $params = []): string
    {
        foreach ($this->_routes as $route) {
            if ($route->getName() === $name) {
                return $route->buildUrl($params);
            }
        }
        throw new Exception("Маршрут с именем '{$name}' не найден.");
    }
}