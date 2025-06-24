<?php

namespace Falcon;

/**
 * Простой класс для фильтрации данных, вдохновленный Phalcon\Filter.
 * Позволяет очищать значения с помощью встроенных и пользовательских фильтров.
 */

class Filter
{

    // Константы для встроенных фильтров
    const FILTER_EMAIL = 'email';
    const FILTER_ABSINT = 'absint';
    const FILTER_INT = 'int';
    const FILTER_INT_CAST = 'int!'; // Явное приведение к int
    const FILTER_STRING = 'string';
    const FILTER_FLOAT = 'float';
    const FILTER_FLOAT_CAST = 'float!'; // Явное приведение к float
    const FILTER_ALPHANUM = 'alphanum';
    const FILTER_TRIM = 'trim';
    const FILTER_STRIPTAGS = 'striptags';
    const FILTER_LOWER = 'lower';
    const FILTER_UPPER = 'upper';
    const FILTER_SAVE_HTML = 'html!'; // Сохраняет HTML, но удаляет опасные элементы (пока не реализован глубокий sanitize)
    const FILTER_URL = 'url'; // Добавим для полноты, как в Phalcon

    /**
     * @var array Массив зарегистрированных пользовательских фильтров.
     */
    protected array $_filters = [];

    /**
     * Добавляет пользовательский фильтр.
     * Обработчик фильтра должен быть объектом с методом `filter(value)`.
     *
     * @param string $name Имя фильтра.
     * @param object $handler Обработчик фильтра (объект, содержащий метод `filter`).
     * @return $this
     * @throws \Exception Если обработчик не является объектом.
     */
    public function add(string $name, object $handler): self
    {
        // В Phalcon здесь ожидается объект, у которого есть метод filter.
        // Если это Closure, то она должна быть обернута в объект или иметь __invoke().
        // Для простоты, пока будем ожидать объект с методом filter().
        if (!method_exists($handler, 'filter')) {
            throw new \Exception("Filter handler must have a 'filter' method.");
        }

        $this->_filters[$name] = $handler;
        return $this;
    }

    /**
     * Очищает значение с помощью указанного фильтра или набора фильтров.
     *
     * @param mixed $value Исходное значение.
     * @param string|array $filters Имя одного фильтра или массив имен фильтров.
     * @param bool $noRecursive Если true, не применять фильтры рекурсивно к массивам.
     * @return mixed Отфильтрованное значение.
     * @throws \Exception Если фильтр не поддерживается.
     */
    public function sanitize(mixed $value, string|array $filters, bool $noRecursive = false): mixed
    {
        // Если фильтров несколько, применяем их последовательно.
        if (is_array($filters)) {
            // Если значение пустое и применяются фильтры, возвращаем как есть.
            // Это может быть спорным моментом, зависит от желаемого поведения.
            // Phalcon возвращает исходное значение.
            if (!$value && !empty($filters)) { // Проверяем, что $filters не пустой, чтобы избежать бесконечного цикла для пустого $value
                return $value;
            }

            foreach ($filters as $filter) {
                /**
                 * Если значение для фильтрации является массивом,
                 * мы применяем фильтры рекурсивно, если не указано $noRecursive.
                 */
                if (is_array($value) && !$noRecursive) {
                    $arrayValue = [];
                    foreach ($value as $itemKey => $itemValue) {
                        $arrayValue[$itemKey] = $this->_sanitize($itemValue, $filter);
                    }
                    $value = $arrayValue;
                } else {
                    $value = $this->_sanitize($value, $filter);
                }
            }
            return $value;
        }

        // Если фильтр один и значение - массив, применяем фильтр к каждому элементу массива.
        if (is_array($value) && !$noRecursive) {
            $sanitizedValue = [];
            foreach ($value as $itemKey => $itemValue) {
                $sanitizedValue[$itemKey] = $this->_sanitize($itemValue, $filters);
            }
            return $sanitizedValue;
        }

        // Если фильтр один и значение не массив, просто применяем его.
        return $this->_sanitize($value, $filters);
    }

    /**
     * Внутренняя обертка для применения конкретного фильтра.
     *
     * @param mixed $value Исходное значение.
     * @param string $filter Имя фильтра.
     * @return mixed Отфильтрованное значение.
     * @throws \Exception Если фильтр не поддерживается.
     */
    protected function _sanitize(mixed $value, string $filter): mixed
    {
        // Применяем пользовательский фильтр, если он зарегистрирован
        if (isset($this->_filters[$filter])) {
            $filterObject = $this->_filters[$filter];
            // Вызываем метод filter() на объекте обработчика
            return $filterObject->filter($value);
        }

        // Применяем встроенные фильтры по имени
        switch ($filter) {
            case self::FILTER_EMAIL:
                // Удаляем одиночные кавычки, т.к. filter_var может некорректно обрабатывать их.
                // FILTER_SANITIZE_EMAIL также удаляет символы, недопустимые в email.
                return filter_var(str_replace("'", "", (string) $value), FILTER_SANITIZE_EMAIL);

            case self::FILTER_INT:
                // FILTER_SANITIZE_NUMBER_INT удаляет все, кроме цифр, + и -.
                return filter_var($value, FILTER_SANITIZE_NUMBER_INT);

            case self::FILTER_INT_CAST:
                // Явное приведение к int
                return (int) $value;

            case self::FILTER_ABSINT:
                // Абсолютное целое число
                return abs((int) $value);

            case self::FILTER_STRING:
                // FILTER_SANITIZE_STRING (устарел в PHP 8.1, но все еще работает)
                // Удаляет или кодирует специальные символы.
                // В новых версиях PHP рекомендуется использовать htmlspecialchars или strip_tags.
                // Для простоты пока оставим, но в будущем лучше пересмотреть.
                // Возможно, здесь стоит использовать strip_tags() или не применять ничего,
                // а полагаться на htmlspecialchars при выводе.
                return filter_var($value, FILTER_SANITIZE_STRING); // Или (string) $value;

            case self::FILTER_FLOAT:
                // FILTER_SANITIZE_NUMBER_FLOAT удаляет все, кроме цифр, +,- и десятичных разделителей.
                return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, ["flags" => FILTER_FLAG_ALLOW_FRACTION]);

            case self::FILTER_FLOAT_CAST:
                // Явное приведение к float (doubleval - это алиас floatval)
                return (float) $value;

            case self::FILTER_ALPHANUM:
                // Удаляет все, кроме букв (A-Z, a-z) и цифр (0-9).
                return preg_replace("/[^A-Za-z0-9]/", '', (string) $value);

            case self::FILTER_TRIM:
                // Удаляет пробелы (или другие символы) с начала и конца строки.
                return is_string($value) ? trim($value) : $value;

            case self::FILTER_STRIPTAGS:
                // Удаляет HTML и PHP теги из строки.
                return is_string($value) ? strip_tags($value) : $value;

            case self::FILTER_SAVE_HTML:
                // Этот фильтр обычно используется для "очистки" HTML, разрешая безопасные теги.
                // Здесь пока просто trim. В более сложной реализации можно использовать библиотеку вроде HTML Purifier.
                return is_string($value) ? trim($value) : $value;

            case self::FILTER_URL:
                // Очищает URL. Удаляет недопустимые символы.
                return filter_var($value, FILTER_SANITIZE_URL);

            case self::FILTER_LOWER:
                // Преобразует строку в нижний регистр. Проверяет наличие mbstring.
                if (function_exists('mb_strtolower')) {
                    return mb_strtolower((string) $value);
                }
                return strtolower((string) $value);

            case self::FILTER_UPPER:
                // Преобразует строку в верхний регистр. Проверяет наличие mbstring.
                if (function_exists('mb_strtoupper')) {
                    return mb_strtoupper((string) $value);
                }
                return strtoupper((string) $value);

            default:
                // Если фильтр не найден, выбрасываем исключение.
                throw new \Exception("Sanitize filter '" . $filter . "' is not supported");
        }
    }

    /**
     * Возвращает массив зарегистрированных пользовательских фильтров.
     *
     * @return array
     */
    public function getFilters(): array
    {
        return $this->_filters;
    }
}
