<?php

declare(strict_types=1);

namespace Falcon\Http;

use Falcon\Di; // Предполагается, что у вас есть класс Falcon\Di
use Falcon\Http\Response\Headers;
// use Falcon\Http\Response\Cookies; // Раскомментируйте, если реализуете класс Cookies
use Falcon\Exception; // Предполагается, что у вас есть базовый класс исключений Falcon\Exception

/**
 * Объект HTTP-ответа.
 */
class Response
{

    protected bool $_sent = false;
    protected ?string $_content = null;
    protected ?Headers $_headers = null;
    // protected ?Cookies $_cookies = null; // Раскомментируйте, если реализуете класс Cookies
    protected ?string $_file = null;
    protected array $_statusCodes = [];
    protected ?Di $_dependencyInjector = null;

    /**
     * Конструктор Falcon\Http\Response.
     *
     * @param string|null $content Содержимое тела ответа.
     * @param int|null $code Код состояния HTTP.
     * @param string|null $status Сообщение о состоянии HTTP.
     */
    public function __construct(?string $content = null, ?int $code = null, ?string $status = null)
    {
        // Немедленно инициализируем заголовки, чтобы они были доступны
        $this->_headers = new Headers();

        if ($content !== null) {
            $this->_content = $content;
        }
        if ($code !== null) {
            $this->setStatusCode($code, $status);
        }

        // Инициализируем коды состояния по умолчанию
        $this->_statusCodes = [
            // ИНФОРМАЦИОННЫЕ КОДЫ
            100 => "Continue",
            101 => "Switching Protocols",
            102 => "Processing",
            // КОДЫ УСПЕХА
            200 => "OK",
            201 => "Created",
            202 => "Accepted",
            203 => "Non-Authoritative Information",
            204 => "No Content",
            205 => "Reset Content",
            206 => "Partial Content",
            207 => "Multi-status",
            208 => "Already Reported",
            // КОДЫ ПЕРЕНАПРАВЛЕНИЯ
            300 => "Multiple Choices",
            301 => "Moved Permanently",
            302 => "Found",
            303 => "See Other",
            304 => "Not Modified",
            305 => "Use Proxy",
            306 => "Switch Proxy", // Устаревший
            307 => "Temporary Redirect",
            308 => "Permanent Redirect", // RFC 7538
            // ОШИБКИ КЛИЕНТА
            400 => "Bad Request",
            401 => "Unauthorized",
            402 => "Payment Required",
            403 => "Forbidden",
            404 => "Not Found",
            405 => "Method Not Allowed",
            406 => "Not Acceptable",
            407 => "Proxy Authentication Required",
            408 => "Request Time-out",
            409 => "Conflict",
            410 => "Gone",
            411 => "Length Required",
            412 => "Precondition Failed",
            413 => "Request Entity Too Large",
            414 => "Request-URI Too Large",
            415 => "Unsupported Media Type",
            416 => "Requested range not satisfiable",
            417 => "Expectation Failed",
            418 => "I'm a teapot", // RFC 2324
            421 => "Misdirected Request", // RFC 7540, Section 9.1.2
            422 => "Unprocessable Entity",
            423 => "Locked",
            424 => "Failed Dependency",
            425 => "Too Early", // RFC 8470
            426 => "Upgrade Required",
            428 => "Precondition Required",
            429 => "Too Many Requests",
            431 => "Request Header Fields Too Large",
            451 => "Unavailable For Legal Reasons", // RFC 7725
            // ОШИБКИ СЕРВЕРА
            500 => "Internal Server Error",
            501 => "Not Implemented",
            502 => "Bad Gateway",
            503 => "Service Unavailable",
            504 => "Gateway Time-out",
            505 => "HTTP Version not supported",
            506 => "Variant Also Negotiates",
            507 => "Insufficient Storage",
            508 => "Loop Detected",
            510 => "Not Extended", // RFC 2774
            511 => "Network Authentication Required"
        ];
    }

    /**
     * Устанавливает внутренний инжектор зависимостей.
     *
     * @param Di $dependencyInjector Контейнер DI.
     * @return Response
     */
    public function setDI(Di $dependencyInjector): Response
    {
        $this->_dependencyInjector = $dependencyInjector;
        return $this;
    }

    /**
     * Возвращает внутренний инжектор зависимостей.
     *
     * @return Di
     */
    public function getDI(): Di
    {
        if ($this->_dependencyInjector === null) {
            // Если DI не установлен, пробуем получить его по умолчанию.
            // Убедитесь, что Falcon\DI::getDefault() возвращает экземпляр Di.
            $this->_dependencyInjector = Di::getDefault();
        }
        return $this->_dependencyInjector;
    }

    /**
     * Устанавливает HTTP-код ответа.
     *
     * ```php
     * $response->setStatusCode(404, "Not Found");
     * ```
     *
     * @param int $code Код состояния HTTP.
     * @param string|null $message Пользовательское сообщение о состоянии. Если null, используется сообщение по умолчанию.
     * @return Response
     * @throws Exception Если указан нестандартный код состояния без сообщения.
     */
    public function setStatusCode(int $code, ?string $message = null): Response
    {
        $headers = $this->getHeaders();

        // Удаляем любые существующие заголовки HTTP/x.y
        // (хотя PHP обычно сам справляется с этим, но для совместимости с Phalcon-подобным поведением)
        $currentHeadersRaw = $headers->toArray();
        foreach ($currentHeadersRaw as $key => $value) {
            // Проверяем, является ли заголовок "сырым" заголовком состояния HTTP
            if (is_string($key) && str_starts_with($key, 'HTTP/')) {
                $headers->remove($key);
            }
        }

        // Если сообщение не указано, пытаемся получить его по умолчанию для данного кода состояния.
        if ($message === null) {
            if (!isset($this->_statusCodes[$code])) {
                throw new Exception("Указан нестандартный код состояния без сообщения");
            }
            $message = $this->_statusCodes[$code];
        }

        // Устанавливаем "сырой" заголовок HTTP-статуса
        $headers->setRaw('HTTP/1.1 ' . $code . ' ' . $message);

        /**
         * Также определяем заголовок 'Status' с HTTP-статусом.
         */
        $headers->set('Status', $code . ' ' . $message);

        return $this;
    }

    /**
     * Возвращает код состояния.
     *
     * ```php
     * print_r($response->getStatusCode());
     * ```
     *
     * @return string|null Строка состояния HTTP (например, "200 OK") или null, если не установлено.
     */
    public function getStatusCode(): ?string
    {
        return $this->getHeaders()->get('Status');
    }

    /**
     * Устанавливает объект заголовков для ответа извне.
     *
     * @param Headers $headers Объект заголовков.
     * @return Response
     */
    public function setHeaders(Headers $headers): Response
    {
        $this->_headers = $headers;
        return $this;
    }

    /**
     * Возвращает заголовки, установленные пользователем.
     *
     * @return Headers
     */
    public function getHeaders(): Headers
    {
        // Заголовки инициализируются в конструкторе, поэтому здесь всегда будет объект.
        return $this->_headers;
    }

    /**
     * Устанавливает объект cookies для ответа извне.
     *
     * @param mixed $cookies Объект cookies (например, Falcon\Http\Response\CookiesInterface).
     * @return Response
     */
    public function setCookies($cookies): Response
    {
        $this->_cookies = $cookies;
        return $this;
    }

    /**
     * Возвращает cookies, установленные пользователем.
     *
     * @return mixed Объект, реализующий Falcon\Http\Response\CookiesInterface (если он существует).
     */
    public function getCookies()
    {
        return $this->_cookies;
    }

    /**
     * Устанавливает отдельный HTTP-заголовок.
     *
     * @param string $name Имя заголовка.
     * @param string $value Значение заголовка.
     * @return Response
     */
    public function setHeader(string $name, string $value): Response
    {
        $headers = $this->getHeaders();
        $headers->set($name, $value);
        return $this;
    }

    /**
     * Отправляет "сырой" заголовок в ответ.
     *
     * @param string $header Строка "сырого" заголовка.
     * @return Response
     */
    public function setRawHeader(string $header): Response
    {
        $headers = $this->getHeaders();
        $headers->setRaw($header);
        return $this;
    }

    /**
     * Сбрасывает все установленные заголовки.
     *
     * @return Response
     */
    public function resetHeaders(): Response
    {
        $headers = $this->getHeaders();
        $headers->reset();
        return $this;
    }

    /**
     * Устанавливает заголовок Expires для использования HTTP-кэша.
     *
     * @param \DateTime $datetime Объект DateTime для установки времени истечения срока действия.
     * @return Response
     */
    public function setExpires(\DateTime $datetime): Response
    {
        $date = clone $datetime;

        /**
         * Все времена истечения срока действия отправляются в UTC.
         * Изменяем часовой пояс на UTC.
         */
        $date->setTimezone(new \DateTimeZone('UTC'));

        /**
         * Заголовок 'Expires' устанавливает эту информацию.
         */
        $this->setHeader('Expires', $date->format('D, d M Y H:i:s') . ' GMT');
        return $this;
    }

    /**
     * Отправляет ответ "Не изменено" (Not Modified).
     *
     * @return Response
     */
    public function setNotModified(): Response
    {
        $this->setStatusCode(304, 'Not Modified');
        return $this;
    }

    /**
     * Устанавливает MIME-тип содержимого ответа, опционально кодировку.
     *
     * ```php
     * $response->setContentType('application/pdf');
     * $response->setContentType('text/plain', 'UTF-8');
     * ```
     *
     * @param string $contentType MIME-тип содержимого (например, 'application/json', 'text/html').
     * @param string|null $charset Кодировка символов (например, 'UTF-8'). По умолчанию 'UTF-8'.
     * @return Response
     */
    public function setContentType(string $contentType, ?string $charset = 'UTF-8'): Response
    {
        $headers = $this->getHeaders();
        if ($charset === null) {
            $headers->set('Content-Type', $contentType);
        } else {
            $headers->set('Content-Type', $contentType . '; charset=' . $charset);
        }
        return $this;
    }

    /**
     * Устанавливает пользовательский ETag.
     *
     * ```php
     * $response->setEtag(md5((string)time()));
     * ```
     *
     * @param string $etag Значение ETag.
     * @return Response
     */
    public function setEtag(string $etag): Response
    {
        $headers = $this->getHeaders();
        $headers->set('Etag', $etag);
        return $this;
    }

    /**
     * Перенаправляет по HTTP на другое действие или URL.
     *
     * @param string|null $location Местоположение для перенаправления (URL или путь).
     * @param bool $externalRedirect Указывает, является ли перенаправление внешним URL.
     * @param int $statusCode Код состояния HTTP для перенаправления (например, 301, 302).
     * @return Response
     */
    public function redirect(?string $location = null, bool $externalRedirect = false, int $statusCode = 302): Response
    {
        if ($location === null) {
            $location = '';
        }

        $headerLocation = null;

        if ($externalRedirect) {
            $headerLocation = $location;
        } elseif (str_contains($location, "://")) {
            // Проверяем, содержит ли строка схему (http://, https:// и т.д.)
            if (preg_match("/^[^:\\/?#]++:/", $location)) {
                $headerLocation = $location;
            }
        }

        $dependencyInjector = $this->getDI();

        // Если местоположение не является полным URL, используем сервис URL для его генерации.
        if ($headerLocation === null) {
            if (!$dependencyInjector->has('url')) {
                throw new Exception("Сервис 'url' не найден в контейнере зависимостей.");
            }
            $urlService = $dependencyInjector->getShared('url');
            // Предполагается, что объект URL имеет метод 'get'
            $headerLocation = $urlService->get($location);
        }

        // Если существует сервис 'view', отключаем его, чтобы избежать вывода шаблонов
        if ($dependencyInjector->has('view')) {
            $view = $dependencyInjector->getShared('view');
            if (is_object($view) && method_exists($view, 'disable')) {
                $view->disable();
            }
        }

        /**
         * HTTP-статус по умолчанию 302 (временное перенаправление).
         */
        if ($statusCode < 300 || $statusCode > 308) {
            $statusCode = 302;
            $message = $this->_statusCodes[302];
        } else {
            $message = $this->_statusCodes[$statusCode] ?? '';
        }

        $this->setStatusCode($statusCode, $message);

        /**
         * Изменяем текущее местоположение с помощью заголовка 'Location'.
         */
        $this->setHeader('Location', $headerLocation);
        return $this;
    }

    /**
     * Устанавливает тело HTTP-ответа.
     *
     * ```php
     * $response->setContent("<h1>Hello!</h1>");
     * ```
     *
     * @param string $content Содержимое тела.
     * @return Response
     */
    public function setContent(string $content): Response
    {
        $this->_content = $content;
        return $this;
    }

    /**
     * Устанавливает тело HTTP-ответа. Параметр автоматически преобразуется в JSON.
     *
     * @param mixed $content Содержимое для кодирования в JSON.
     * @param int $jsonOptions Опции для json_encode.
     * @return Response
     */
    public function setJsonContent(mixed $content, int $jsonOptions = 0): Response
    {
        $this->_content = json_encode($content, $jsonOptions);
        $this->setContentType('application/json'); // Устанавливаем Content-Type для JSON
        return $this;
    }

    /**
     * Добавляет строку к телу HTTP-ответа.
     *
     * @param string $content Содержимое для добавления.
     * @return Response
     */
    public function appendContent(string $content): Response
    {
        $this->_content .= $content;
        return $this;
    }

    /**
     * Возвращает тело HTTP-ответа.
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->_content;
    }

    /**
     * Проверяет, был ли ответ уже отправлен.
     *
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->_sent;
    }

    /**
     * Отправляет заголовки клиенту.
     *
     * @return Response
     */
    public function sendHeaders(): Response
    {
        if ($this->_headers !== null) {
            $this->_headers->send();
        }
        return $this;
    }

    /**
     * Отправляет cookies клиенту.
     *
     * @return Response
     */
    public function sendCookies(): Response
    {
        if (is_object($this->_cookies) && method_exists($this->_cookies, 'send')) {
            $this->_cookies->send();
        }
        return $this;
    }

    /**
     * Выводит HTTP-ответ клиенту.
     *
     * @return Response
     * @throws Exception Если ответ уже был отправлен.
     */
    public function send(): Response
    {
        if ($this->_sent) {
            throw new Exception("Ответ уже был отправлен");
        }

        /**
         * Отправляем заголовки.
         */
        $this->sendHeaders();

        /**
         * Отправляем Cookies.
         */
        $this->sendCookies();

        /**
         * Выводим тело ответа.
         */
        $content = $this->_content;
        if ($content !== null) {
            echo $content;
        } else {
            $file = $this->_file;
            if (is_string($file) && strlen($file) > 0 && file_exists($file) && is_readable($file)) {
                readfile($file);
            }
        }

        $this->_sent = true;
        return $this;
    }

    /**
     * Устанавливает прикрепленный файл для отправки в конце запроса.
     * Это устанавливает соответствующие заголовки для загрузки файла.
     *
     * @param string $filePath Путь к файлу.
     * @param string|null $attachmentName Имя файла для загрузки. Если null, используется базовое имя файла.
     * @param bool $attachment Указывает, следует ли отправлять файл как вложение (true) или inline (false).
     * @return Response
     */
    public function setFileToSend(string $filePath, ?string $attachmentName = null, bool $attachment = true): Response
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("Файл для отправки не найден или недоступен для чтения: " . $filePath);
        }

        $baseName = $attachmentName ?? basename($filePath);
        $mimeType = mime_content_type($filePath); // Определяем MIME-тип файла

        $headers = $this->getHeaders();

        // Общие заголовки для передачи файлов
        $headers->setRaw("Content-Description: File Transfer");
        $headers->set("Content-Type", $mimeType ?: "application/octet-stream"); // Используем определенный MIME-тип или обобщенный
        $headers->set("Content-Length", (string) filesize($filePath)); // Отправляем размер файла

        if ($attachment) {
            $headers->set("Content-Disposition", "attachment; filename=\"" . rawurlencode($baseName) . "\"");
        } else {
            $headers->set("Content-Disposition", "inline; filename=\"" . rawurlencode($baseName) . "\"");
        }

        $headers->setRaw("Content-Transfer-Encoding: binary");
        $headers->set("Cache-Control", "must-revalidate, post-check=0, pre-check=0");
        $headers->set("Pragma", "public");

        $this->_file = $filePath;

        return $this;
    }
}
