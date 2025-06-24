<?php

namespace Falcon\Http\Response;

/**
 * Управляет HTTP-заголовками ответа.
 */
class Headers
{
    /**
     * @var array Хранит фактические заголовки.
     */
    protected array $_headers = [];

    /**
     * Устанавливает заголовок.
     *
     * @param string $name Имя заголовка.
     * @param string $value Значение заголовка.
     * @return Headers
     */
    public function set(string $name, string $value): Headers
    {
        $this->_headers[$name] = $value;
        return $this;
    }

    /**
     * Устанавливает "сырой" заголовок, например, "HTTP/1.1 200 OK".
     *
     * @param string $header Строка "сырого" заголовка.
     * @return Headers
     */
    public function setRaw(string $header): Headers
    {
        // Для "сырых" заголовков мы можем использовать сам заголовок в качестве ключа
        // или уникальный идентификатор, если возможно несколько "сырых" заголовков.
        // Для простоты будем считать, что "сырые" заголовки уникальны по своей строке.
        // Для строк состояния HTTP это обычно уникально.
        $this->_headers[$header] = true; // Значение не имеет значения для "сырых" заголовков
        return $this;
    }

    /**
     * Возвращает значение заголовка по имени.
     *
     * @param string $name Имя заголовка.
     * @return string|null
     */
    public function get(string $name): ?string
    {
        return $this->_headers[$name] ?? null;
    }

    /**
     * Удаляет заголовок по имени.
     *
     * @param string $name Имя заголовка.
     * @return Headers
     */
    public function remove(string $name): Headers
    {
        unset($this->_headers[$name]);
        return $this;
    }

    /**
     * Сбрасывает все заголовки.
     *
     * @return Headers
     */
    public function reset(): Headers
    {
        $this->_headers = [];
        return $this;
    }

    /**
     * Возвращает все заголовки в виде ассоциативного массива.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->_headers;
    }

    /**
     * Отправляет заголовки клиенту.
     *
     * @return Headers
     */
    public function send(): Headers
    {
        if (!headers_sent()) {
            foreach ($this->_headers as $name => $value) {
                if ($value === true) { // Это "сырой" заголовок
                    header($name);
                } else {
                    header("{$name}: {$value}");
                }
            }
        }
        return $this;
    }
}
