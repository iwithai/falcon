<?php

declare(strict_types=1);

namespace Falcon\Mvc\Router;

use Falcon\Exception;

/**
 * Представляет отдельный маршрут в маршрутизаторе.
 */
class Route
{
    /**
     * @var string Шаблон URI маршрута.
     */
    protected string $_pattern;

    /**
     * @var array Карта, указывающая на контроллер и действие (например, ["controller" => "users", "action" => "view"]).
     */
    protected array $_paths;

    /**
     * @var array|null Массив поддерживаемых HTTP-методов.
     */
    protected ?array $_methods;

    /**
     * @var string|null Имя маршрута.
     */
    protected ?string $_name;

    /**
     * @var string|null Пространство имен, установленное для этого конкретного маршрута.
     */
    protected ?string $_namespace = null; // Новое свойство для пространства имен маршрута

    /**
     * @var array Параметры, извлеченные из URI.
     */
    protected array $_params = [];

    /**
     * Конструктор маршрута.
     *
     * @param string $pattern Шаблон URI.
     * @param string|array $paths Определение контроллера/действия.
     * @param array|null $methods Поддерживаемые HTTP-методы.
     * @param string|null $name Имя маршрута.
     * @param string|null $defaultNamespace Пространство имен по умолчанию от Router.
     */
    public function __construct(string $pattern, string|array $paths, ?array $methods = null, ?string $name = null, ?string $defaultNamespace = null)
    {
        $this->_pattern = $pattern;
        $this->_methods = $methods;
        $this->_name = $name;

        // Устанавливаем пространство имен для этого маршрута, если оно указано в $paths
        // или берем из defaultNamespace
        if (is_array($paths) && isset($paths['namespace'])) {
            $this->_namespace = rtrim($paths['namespace'], '\\') . '\\';
            unset($paths['namespace']); // Удаляем, чтобы не конфликтовало с controller/action
        } elseif ($defaultNamespace !== null) {
            $this->_namespace = $defaultNamespace;
        }

        $this->setPaths($paths); // Инициализация controller/action
    }

    /**
     * Устанавливает определение путей (контроллер, действие).
     *
     * @param string|array $paths Определение.
     * @return void
     */
    protected function setPaths(string|array $paths): void
    {
        if (is_string($paths)) {
            // Например, "users::view" или "users"
            $parts = explode('::', $paths);
            $this->_paths['controller'] = $parts[0] ?? null;
            $this->_paths['action'] = $parts[1] ?? 'index';
        } elseif (is_array($paths)) {
            $this->_paths = $paths;
            if (!isset($this->_paths['controller'])) {
                throw new Exception("Определение маршрута должно содержать 'controller'.");
            }
            if (!isset($this->_paths['action'])) {
                $this->_paths['action'] = 'index';
            }
        } else {
            throw new Exception("Неверный формат определения путей для маршрута.");
        }
    }

    /**
     * Возвращает шаблон URI маршрута.
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->_pattern;
    }

    /**
     * Возвращает поддерживаемые HTTP-методы.
     *
     * @return array|null
     */
    public function getMethods(): ?array
    {
        return $this->_methods;
    }

    /**
     * Возвращает имя маршрута.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->_name;
    }

    /**
     * Возвращает полное имя контроллера, включая пространство имен.
     *
     * @return string|null
     */
    public function getControllerName(): ?string
    {
        $controller = $this->_paths['controller'] ?? null;
        if ($controller === null) {
            return null;
        }

        // Если для маршрута явно указано пространство имен, используем его
        if ($this->_namespace !== null) {
            return $this->_namespace . $controller;
        }

        return $controller;
    }

    /**
     * Возвращает имя действия, определенное для маршрута.
     *
     * @return string|null
     */
    public function getActionName(): ?string
    {
        return $this->_paths['action'] ?? null;
    }

    /**
     * Возвращает параметры, извлеченные после сопоставления.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->_params;
    }

    /**
     * Пытается сопоставить URI с шаблоном маршрута.
     *
     * @param string $uri URI для сопоставления.
     * @return bool True, если сопоставление успешно, false в противном случае.
     */
    public function match(string $uri): bool
    {
        $this->_params = [];

        $regexPattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::([^\}]+))?\}/', function ($matches) {
            $name = $matches[1];
            $regex = $matches[2] ?? '[^/]+';

            $this->addParamName($name); // Добавляем имя параметра для отслеживания
            return '(' . $regex . ')';
        }, $this->_pattern);

        $regexPattern = '#^' . $regexPattern . '$#';

        if (preg_match($regexPattern, $uri, $matches)) {
            array_shift($matches);

            $paramNames = array_keys($this->_params); // Получаем имена параметров в том порядке, в котором они были в шаблоне
            if (count($matches) !== count($paramNames)) {
                // Это может произойти, если шаблон содержит необязательные группы,
                // которые мы здесь не обрабатываем явно. Для простоты, пока считаем ошибкой.
                return false;
            }

            foreach ($paramNames as $index => $name) {
                $this->_params[$name] = $matches[$index];
            }

            // Добавляем статические параметры из _paths (кроме controller, action, namespace)
            foreach ($this->_paths as $key => $value) {
                if (!in_array($key, ['controller', 'action'])) { // namespace уже обработан
                    $this->_params[$key] = $value;
                }
            }

            return true;
        }

        return false;
    }
    
    /**
     * Добавляет имя параметра для последующего сопоставления его значения.
     * Используется внутри preg_replace_callback.
     * @param string $name
     */
    protected function addParamName(string $name): void
    {
        $this->_params[$name] = null;
    }

    /**
     * Генерирует URL на основе шаблона маршрута и предоставленных параметров.
     *
     * @param array $params Ассоциативный массив параметров для заполнения шаблона.
     * @return string Сгенерированный URL.
     * @throws Exception Если необходимые параметры отсутствуют.
     */
    public function buildUrl(array $params = []): string
    {
        $url = $this->_pattern;

        $url = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::[^\}]+)?\}/', function ($matches) use (&$params) {
            $name = $matches[1];
            if (!isset($params[$name])) {
                throw new Exception("Необходимый параметр '{$name}' отсутствует для маршрута '{$this->_pattern}'.");
            }
            $value = $params[$name];
            unset($params[$name]);
            return $value;
        }, $url);

        // Добавляем оставшиеся параметры в Query String
        if (!empty($params)) {
            $queryString = http_build_query($params);
            if ($queryString) {
                if (str_contains($url, '?')) {
                    $url .= '&' . $queryString;
                } else {
                    $url .= '?' . $queryString;
                }
            }
        }

        return $url;
    }
}
