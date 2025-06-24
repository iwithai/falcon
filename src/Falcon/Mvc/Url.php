<?php

declare(strict_types=1);

namespace Falcon\Mvc;

/**
 * Класс для генерации URL-адресов в приложении Falcon MVC.
 */
class Url
{

    /**
     * @var string|null Базовый URI для динамических URL-адресов.
     */
    protected ?string $_baseUri = null;

    /**
     * @var string|null Базовый URI для статических ресурсов (CSS, JS, изображения).
     */
    protected ?string $_staticBaseUri = null;

    /**
     * @var string|null Базовый путь для внутренних файлов.
     */
    protected ?string $_basePath = null;

    /**
     * Устанавливает базовый URI для генерации URL-адресов.
     * Если статический базовый URI не установлен, он также устанавливается.
     *
     * @param string $baseUri Базовый URI, например "/my_app/".
     * @return Url
     */
    public function setBaseUri(string $baseUri): Url
    {
        $this->_baseUri = rtrim($baseUri, '/') . '/'; // Гарантируем слэш в конце
        if ($this->_staticBaseUri === null) {
            $this->_staticBaseUri = $this->_baseUri;
        }
        return $this;
    }

    /**
     * Устанавливает статический базовый URI для статических ресурсов.
     *
     * @param string $staticBaseUri Статический базовый URI, например "//static.example.com/".
     * @return Url
     */
    public function setStaticBaseUri(string $staticBaseUri): Url
    {
        $this->_staticBaseUri = rtrim($staticBaseUri, '/') . '/'; // Гарантируем слэш в конце
        return $this;
    }

    /**
     * Возвращает базовый URI. Если он не установлен, пытается определить его автоматически.
     *
     * @return string
     */
    public function getBaseUri(): string
    {
        if ($this->_baseUri !== null) {
            return $this->_baseUri;
        }

        $uri = '/';
        // Попытка определить базовый URI из серверных переменных
        if (isset($_SERVER['SCRIPT_NAME'])) {
            $uri = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
        } elseif (isset($_SERVER['PHP_SELF'])) {
            $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/';
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            // Если PHP-скрипт находится в подпапке, нам нужно отсечь ее
            $scriptName = $_SERVER['SCRIPT_NAME'];
            $requestUri = $_SERVER['REQUEST_URI'];
            $pos = strpos($requestUri, $scriptName);
            if ($pos === 0) {
                $uri = $scriptName;
            } else {
                $pos = strpos($requestUri, dirname($scriptName));
                if ($pos === 0) {
                    $uri = rtrim(dirname($scriptName), '/') . '/';
                }
            }
        }

        // Удаляем двойные слэши и гарантируем начальный и конечный слэш
        $this->_baseUri = '/' . trim($uri, '/') . '/';

        return $this->_baseUri;
    }

    /**
     * Возвращает статический базовый URI. Если не установлен, возвращает динамический базовый URI.
     *
     * @return string
     */
    public function getStaticBaseUri(): string
    {
        if ($this->_staticBaseUri !== null) {
            return $this->_staticBaseUri;
        }
        return $this->getBaseUri();
    }

    /**
     * Устанавливает базовый путь для внутренних файлов (например, для включения файлов).
     *
     * @param string $basePath Базовый путь, например "/var/www/my_app/".
     * @return Url
     */
    public function setBasePath(string $basePath): Url
    {
        $this->_basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return $this;
    }

    /**
     * Возвращает базовый путь.
     *
     * @return string|null
     */
    public function getBasePath(): ?string
    {
        return $this->_basePath;
    }

    /**
     * Генерирует полный URL.
     *
     * @param string|null $uri Строка URI (например, "controller/action" или "/some/path").
     * @param array|null $args Ассоциативный массив аргументов, которые будут преобразованы в строку запроса.
     * @param bool|null $local Является ли URI локальным для приложения. Если null, определяется автоматически.
     * @return string Сгенерированный URL.
     */
    public function get(?string $uri = null, ?array $args = null, ?bool $local = null): string
    {
        $uri = (string) $uri; // Приводим к строке для безопасности
        // Определяем, является ли URI внешним
        if ($local === null) {
            if (str_contains($uri, '://') || str_starts_with($uri, '//')) { // Проверяем на схему или протоколонезависимый URL
                $local = false;
            } else {
                $local = true;
            }
        }

        $finalUri = $uri;

        if ($local) {
            $baseUri = $this->getBaseUri();

            // Избегаем двойных слэшей при объединении
            if ($uri === '' || $uri === '/') {
                $finalUri = $baseUri;
            } elseif (str_starts_with($uri, '/')) {
                $finalUri = rtrim($baseUri, '/') . $uri;
            } else {
                $finalUri = $baseUri . $uri;
            }
        }

        // Добавляем аргументы запроса
        if ($args !== null && !empty($args)) {
            $queryString = http_build_query($args);
            if ($queryString !== '') {
                // Определяем, нужно ли использовать '?' или '&'
                if (str_contains($finalUri, '?')) {
                    $finalUri .= '&' . $queryString;
                } else {
                    $finalUri .= '?' . $queryString;
                }
            }
        }

        return $finalUri;
    }

    /**
     * Генерирует URL для статических ресурсов.
     *
     * @param string|null $uri URI статического ресурса (например, "css/style.css").
     * @return string Сгенерированный статический URL.
     */
    public function getStatic(?string $uri = null): string
    {
        $staticBaseUri = $this->getStaticBaseUri();
        $uri = (string) $uri;

        // Избегаем двойных слэшей
        if ($uri === '' || $uri === '/') {
            return $staticBaseUri;
        } elseif (str_starts_with($uri, '/')) {
            return rtrim($staticBaseUri, '/') . $uri;
        }
        return $staticBaseUri . $uri;
    }

    /**
     * Генерирует полный путь к файлу.
     *
     * @param string|null $path Путь к файлу относительно basePath.
     * @return string Полный путь к файлу.
     */
    public function path(?string $path = null): string
    {
        if ($this->_basePath === null) {
            // Можно бросить исключение или установить значение по умолчанию, если это имеет смысл.
            // Для простоты, если basePath не установлен, мы можем просто вернуть переданный путь.
            // В реальном приложении, возможно, лучше бросить исключение или попытаться определить.
            return (string) $path;
        }
        return $this->_basePath . ltrim((string) $path, DIRECTORY_SEPARATOR);
    }
}
