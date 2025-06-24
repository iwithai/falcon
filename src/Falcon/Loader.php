<?php

declare(strict_types=1);

namespace Falcon;

/**
 * Базовый автозагрузчик PSR-4 для фреймворка Falcon.
 */
class Loader
{
    /**
     * @var array Массив префиксов неймспейсов и соответствующих базовых директорий.
     */
    protected static array $_namespaces = [];

    /**
     * Регистрирует автозагрузчик в SPL.
     *
     * @return void
     */
    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Добавляет базовую директорию для неймспейса.
     *
     * @param string $namespace Префикс неймспейса (например, 'Falcon\\').
     * @param string $baseDir Базовая директория для файлов класса (например, '/path/to/src/Falcon/').
     * @param bool $prepend Если true, добавляет директорию в начало стека (для приоритета).
     * @return void
     */
    public static function addNamespace(string $namespace, string $baseDir, bool $prepend = false): void
    {
        $namespace = trim($namespace, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!isset(self::$_namespaces[$namespace])) {
            self::$_namespaces[$namespace] = [];
        }

        if ($prepend) {
            array_unshift(self::$_namespaces[$namespace], $baseDir);
        } else {
            self::$_namespaces[$namespace][] = $baseDir;
        }
    }

    /**
     * Реализует логику автозагрузки.
     *
     * @param string $className Полное имя класса с неймспейсом.
     * @return bool True, если класс загружен, false в противном случае.
     */
    public static function autoload(string $className): bool
    {
        $className = ltrim($className, '\\');
        $filePath = '';
        $namespace = $className;

        while (($pos = strrpos($namespace, '\\')) !== false) {
            $namespace = substr($className, 0, $pos + 1);
            $relativeClass = substr($className, $pos + 1);
            $filePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (isset(self::$_namespaces[$namespace])) {
                foreach (self::$_namespaces[$namespace] as $baseDir) {
                    $file = $baseDir . $filePath;
                    if (file_exists($file)) {
                        require_once $file;
                        return true;
                    }
                }
            }
            $namespace = rtrim($namespace, '\\');
        }

        return false;
    }
}
