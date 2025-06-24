<?php

namespace Falcon\Http;

use Falcon\Di;
use Falcon\Filter;
use Falcon\Http\Request\File; // Подключаем новый класс File

/**
 * Объект запроса, инкапсулирующий данные HTTP-запроса.
 */
class Request
{

    // ... (без изменений в свойствах Di, Filter, _rawBody, _putDeleteBody) ...
    protected Di $di;
    protected Filter $filter;
    protected ?string $_rawBody = null;
    protected ?array $_putDeleteBody = null;
    protected ?array $_headers = null;

    /**
     * @var array|null Кэш всех загруженных файлов.
     */
    protected ?array $_files = null; // Добавляем новое свойство для кэша файлов

    public function __construct(Di $di)
    {
        $this->di = $di;
        $this->filter = $this->di->getShared('filter');
    }

    // ... (методы getMethod, getUri, getQuery, getPost, get, getRawBody, _getPutDeleteParams, getPut, getDelete) ...
    // Все эти методы остаются без изменений из предыдущей итерации.
    // Я не буду их дублировать здесь, чтобы избежать слишком большого блока кода.
    // Представьте, что они здесь.

    /**
     * Возвращает все HTTP-заголовки запроса в виде ассоциативного массива.
     * Ключи заголовков нормализуются (например, Content-Type, Accept-Encoding).
     *
     * @return array
     */
    public function getHeaders(): array
    {
        if ($this->_headers === null) {
            if (function_exists('getallheaders')) {
                // Предпочтительный способ, если доступен
                $this->_headers = getallheaders();
            } else {
                $headers = [];
                foreach ($_SERVER as $name => $value) {
                    // Переменные HTTP_... являются заголовками
                    if (str_starts_with($name, 'HTTP_')) {
                        // Преобразуем HTTP_ACCEPT_ENCODING в Accept-Encoding
                        $name = str_replace('_', '-', substr($name, 5));
                        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
                        $headers[$name] = $value;
                    }
                    // Content-Type и Content-Length могут быть не с префиксом HTTP_
                    if (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                        $name = str_replace('_', '-', $name);
                        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
                        $headers[$name] = $value;
                    }
                }
                $this->_headers = $headers;
            }
        }
        return $this->_headers;
    }

    /**
     * Возвращает значение конкретного HTTP-заголовка.
     * Если заголовок не найден, возвращает null.
     *
     * @param string $name Имя заголовка (например, 'Content-Type', 'X-Requested-With').
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        $headers = $this->getHeaders();
        // Заголовки могут быть в разном регистре, поэтому нормализуем для поиска.
        $normalizedName = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
        return $headers[$normalizedName] ?? null;
    }

    /**
     * Возвращает все переменные из $_SERVER или конкретную переменную по ключу,
     * опционально фильтруя ее и предоставляя значение по умолчанию.
     *
     * @param string|null $key Ключ переменной в $_SERVER. Если null, возвращает весь $_SERVER.
     * @param string|array|null $filters Имя одного фильтра или массив имен фильтров.
     * @param mixed $default Значение по умолчанию, если ключ не найден.
     * @return mixed
     */
    public function getServer(?string $key = null, string|array|null $filters = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_SERVER;
        }

        $value = $_SERVER[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return $filters ? $this->filter->sanitize($value, $filters) : $value;
    }

    /**
     * Возвращает массив всех загруженных файлов в виде объектов Falcon\Http\Request\File.
     * Обрабатывает как одиночные файлы, так и множественную загрузку.
     * Кэширует результат.
     *
     * @return File[] Массив объектов File.
     */
    public function getFiles(): array
    {
        if ($this->_files === null) {
            $this->_files = [];
            if (!empty($_FILES)) {
                // normalize $_FILES array for easier processing
                $files = self::normalizeFiles($_FILES);

                foreach ($files as $fileData) {
                    // Создаем объект File только для успешно загруженных или частично загруженных файлов
                    // (исключая UPLOAD_ERR_NO_FILE, если только не хотим его явно обрабатывать)
                    if (($fileData['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $this->_files[] = new File($fileData);
                    }
                }
            }
        }
        return $this->_files;
    }

    /**
     * Вспомогательная статическая функция для нормализации массива $_FILES.
     * Преобразует структуру для удобной обработки множественных загрузок.
     *
     * @param array $filesData Исходный массив $_FILES.
     * @return array Нормализованный массив файлов.
     */
    protected static function normalizeFiles(array $filesData): array
    {
        $normalized = [];
        foreach ($filesData as $name => $fileProperties) {
            // Если это массив свойств (например, name, type, tmp_name, error, size - для одного файла)
            if (is_array($fileProperties['name'])) {
                // Это множественная загрузка, нужно развернуть
                foreach ($fileProperties['name'] as $key => $value) {
                    $normalized[] = [
                        'name' => $fileProperties['name'][$key],
                        'type' => $fileProperties['type'][$key],
                        'tmp_name' => $fileProperties['tmp_name'][$key],
                        'error' => $fileProperties['error'][$key],
                        'size' => $fileProperties['size'][$key],
                    ];
                }
            } else {
                // Это одиночная загрузка
                $normalized[] = $fileProperties;
            }
        }
        return $normalized;
    }

    /**
     * Проверяет, существует ли параметр в GET-запросе.
     *
     * @param string $key Ключ параметра.
     * @return bool
     */
    public function hasQuery(string $key): bool
    {
        return isset($_GET[$key]);
    }

    /**
     * Проверяет, существует ли параметр в POST-запросе.
     *
     * @param string $key Ключ параметра.
     * @return bool
     */
    public function hasPost(string $key): bool
    {
        return isset($_POST[$key]);
    }

    /**
     * Проверяет, существует ли параметр в GET или POST (любой из них).
     *
     * @param string $key Ключ параметра.
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->hasQuery($key) || $this->hasPost($key);
    }

    /**
     * Проверяет, были ли загружены файлы в запросе.
     *
     * @return bool
     */
    public function hasFiles(): bool
    {
        return !empty($this->getFiles());
    }

    /**
     * Проверяет наличие конкретного HTTP-заголовка.
     * Заголовки нормализуются для поиска.
     *
     * @param string $name Имя заголовка (например, 'Content-Type').
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        return $this->getHeader($name) !== null;
    }

    /**
     * Проверяет наличие переменной в $_SERVER.
     *
     * @param string $key Ключ переменной в $_SERVER.
     * @return bool
     */
    public function hasServer(string $key): bool
    {
        return isset($_SERVER[$key]);
    }

    /**
     * Проверяет, содержит ли текущий запрос JSON-тело.
     *
     * @return bool
     */
    public function hasJsonRawBody(): bool
    {
        return $this->hasHeader('Content-Type') && str_contains($this->getHeader('Content-Type'), 'application/json');
    }

    /**
     * Проверяет, был ли запрос выполнен через AJAX.
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return ($this->hasHeader('X-Requested-With') &&
                strtolower($this->getHeader('X-Requested-With')) === 'xmlhttprequest');
    }

    /**
     * Возвращает тело JSON-запроса.
     *
     * @return array|null
     */
    public function getJsonRawBody(): ?array
    {
        if ($this->hasJsonRawBody()) {
            return $this->_getPutDeleteParams();
        }
        return null;
    }
}
