<?php

declare(strict_types=1);

namespace Falcon\Mvc;

use Falcon\Di; // Предполагается, что у вас есть класс Falcon\Di
use Falcon\Exception; // Предполагается, что у вас есть базовый класс исключений Falcon\Exception

/**
 * Класс для управления отображениями (представлениями) в приложении Falcon MVC.
 */
class View
{
    /**
     * @var string|null Путь к директории представлений.
     */
    protected ?string $_viewsDir = null;

    /**
     * @var array Переменные, которые будут доступны в представлениях.
     */
    protected array $_viewVars = [];

    /**
     * @var bool Флаг, указывающий, включено ли отображение.
     */
    protected bool $_enabled = true;

    /**
     * @var Di|null Инжектор зависимостей.
     */
    protected ?Di $_dependencyInjector = null;

    /**
     * Конструктор View.
     *
     * @param string|null $viewsDir Опциональный путь к директории представлений.
     */
    public function __construct(?string $viewsDir = null)
    {
        if ($viewsDir !== null) {
            $this->setViewsDir($viewsDir);
        }
    }

    /**
     * Устанавливает путь к директории представлений.
     *
     * @param string $viewsDir Путь к директории представлений.
     * @return View
     * @throws Exception Если директория представлений не существует или недоступна для чтения.
     */
    public function setViewsDir(string $viewsDir): View
    {
        $viewsDir = rtrim($viewsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($viewsDir) || !is_readable($viewsDir)) {
            throw new Exception("Директория представлений '{$viewsDir}' не существует или недоступна для чтения.");
        }
        $this->_viewsDir = $viewsDir;
        return $this;
    }

    /**
     * Возвращает путь к директории представлений.
     *
     * @return string|null
     */
    public function getViewsDir(): ?string
    {
        return $this->_viewsDir;
    }

    /**
     * Устанавливает инжектор зависимостей.
     *
     * @param Di $dependencyInjector Объект инжектора зависимостей.
     * @return View
     */
    public function setDI(Di $dependencyInjector): View
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
     * Устанавливает переменную, доступную в представлении.
     *
     * @param string $key Имя переменной.
     * @param mixed $value Значение переменной.
     * @return View
     */
    public function setVar(string $key, mixed $value): View
    {
        $this->_viewVars[$key] = $value;
        return $this;
    }

    /**
     * Устанавливает несколько переменных, доступных в представлении.
     *
     * @param array $vars Ассоциативный массив переменных.
     * @return View
     */
    public function setVars(array $vars): View
    {
        $this->_viewVars = array_merge($this->_viewVars, $vars);
        return $this;
    }

    /**
     * Возвращает значение переменной из представления.
     *
     * @param string $key Имя переменной.
     * @param mixed|null $defaultValue Значение по умолчанию, если переменная не установлена.
     * @return mixed|null
     */
    public function getVar(string $key, mixed $defaultValue = null): mixed
    {
        return $this->_viewVars[$key] ?? $defaultValue;
    }

    /**
     * Возвращает все переменные, установленные для представления.
     *
     * @return array
     */
    public function getVars(): array
    {
        return $this->_viewVars;
    }

    /**
     * Проверяет, существует ли файл представления.
     *
     * @param string $viewPath Путь к файлу представления относительно viewsDir (например, "index/index.phtml").
     * @return bool
     */
    public function exists(string $viewPath): bool
    {
        if ($this->_viewsDir === null) {
            return false; // Директория представлений не установлена
        }
        return file_exists($this->_viewsDir . $viewPath);
    }

    /**
     * Отключает отображение.
     *
     * @return View
     */
    public function disable(): View
    {
        $this->_enabled = false;
        return $this;
    }

    /**
     * Включает отображение.
     *
     * @return View
     */
    public function enable(): View
    {
        $this->_enabled = true;
        return $this;
    }

    /**
     * Проверяет, включено ли отображение.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->_enabled;
    }

    /**
     * Рендерит (отображает) файл представления.
     * Все установленные переменные становятся доступными в шаблоне.
     *
     * @param string $viewPath Путь к файлу представления относительно viewsDir (например, "index/index.phtml").
     * @param array $params Дополнительные параметры, которые будут объединены с установленными переменными.
     * @return string Отрендеренное содержимое представления.
     * @throws Exception Если директория представлений не установлена или файл представления не найден.
     */
    public function render(string $viewPath, array $params = []): string
    {
        if (!$this->_enabled) {
            return ''; // Если отображение отключено, возвращаем пустую строку
        }

        if ($this->_viewsDir === null) {
            throw new Exception("Директория представлений не установлена. Используйте setViewsDir().");
        }

        $fullPath = $this->_viewsDir . $viewPath;

        if (!file_exists($fullPath)) {
            throw new Exception("Файл представления не найден: '{$fullPath}'");
        }

        // Объединяем глобальные переменные представления с локальными параметрами
        $data = array_merge($this->_viewVars, $params);

        // Извлекаем переменные в локальную область видимости для шаблона
        extract($data);

        // Начинаем буферизацию вывода
        ob_start();

        try {
            // Включаем файл представления
            include $fullPath;
        } catch (\Throwable $e) {
            ob_end_clean(); // Очищаем буфер в случае ошибки
            throw new Exception("Ошибка при рендеринге представления '{$viewPath}': " . $e->getMessage(), 0, $e);
        }

        // Возвращаем содержимое буфера и очищаем его
        return ob_get_clean() ?: '';
    }
}