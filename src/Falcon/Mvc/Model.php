<?php

declare(strict_types=1);

namespace Falcon\Mvc;

use Falcon\Di; // Используем наш контейнер зависимостей
use Falcon\Exception; // Используем базовый класс исключений Falcon

/**
 * Базовый класс для всех моделей ORM.
 * Предоставляет базовую функциональность для взаимодействия с базой данных.
 *
 * Примечание: Это очень упрощенная ORM для демонстрации.
 * Реальная ORM будет включать в себя гораздо больше функционала (валидация, события, связи, query builder и т.д.).
 */
abstract class Model
{
    /**
     * @var Di|null Инжектор зависимостей. Используется для получения соединения с БД.
     */
    protected ?Di $_dependencyInjector = null;

    /**
     * @var \PDO|null Соединение с базой данных. Получается из DI.
     */
    protected ?\PDO $_connection = null; // Изменяем на нестатическое и приватное/protected

    /**
     * @var string Имя таблицы в базе данных. Если не указано, выводится из имени класса.
     */
    protected string $tableName; // Добавляем тип

    /**
     * @var array Атрибуты (данные полей) модели.
     */
    protected array $attributes = []; // Добавляем тип

    /**
     * Конструктор модели.
     * Определяет имя таблицы, если оно не было переопределено.
     * После создания объекта модели (например, через DI), вызовите setDI() и onConstruct().
     */
    public function __construct()
    {
        if (empty($this->tableName)) {
            // Предполагаем, что имя таблицы - это имя класса в нижнем регистре + 's'
            // Например, UserModel -> users, ProductModel -> products
            $parts = explode('\\', get_called_class());
            $className = array_pop($parts);
            // Удаляем суффикс 'Model', если он есть, перед формированием имени таблицы
            if (str_ends_with($className, 'Model')) {
                $className = substr($className, 0, -5); // Удаляем "Model"
            }
            $this->tableName = strtolower($className) . 's';
        }
    }

    /**
     * Устанавливает инжектор зависимостей для модели.
     * Этот метод должен вызываться после создания экземпляра модели.
     *
     * @param Di $di Объект инжектора зависимостей.
     * @return void
     */
    public function setDI(Di $di): void
    {
        $this->_dependencyInjector = $di;
    }

    /**
     * Возвращает инжектор зависимостей.
     * Если инжектор не был установлен через setDI, пытается получить дефолтный.
     *
     * @return Di|null
     */
    public function getDI(): ?Di
    {
        if ($this->_dependencyInjector === null) {
            $this->_dependencyInjector = Di::getDefault();
        }
        return $this->_dependencyInjector;
    }

    /**
     * Возвращает соединение с базой данных из DI.
     *
     * @return \PDO Объект PDO-соединения.
     * @throws Exception Если соединение не установлено или сервис 'db' не найден в DI.
     */
    protected function getConnection(): \PDO
    {
        if ($this->_connection === null) {
            $di = $this->getDI();
            if ($di === null || !$di->hasShared('db')) {
                throw new Exception("Сервис 'db' не доступен в DI. Пожалуйста, зарегистрируйте его.");
            }
            $this->_connection = $di->getShared('db');
        }
        return $this->_connection;
    }

    /**
     * Вызывается после создания экземпляра модели и установки DI.
     * Дочерние классы могут переопределить этот метод для своей инициализации.
     *
     * @return void
     */
    public function onConstruct(): void
    {
        // Метод-заглушка для инициализации в дочерних классах
        // Например, здесь можно получить соединение с БД, если оно нужно сразу.
        // $this->getConnection();
    }

    /**
     * Находит первую запись, соответствующую условиям.
     *
     * @param array $conditions Условия выборки (например, ['id' => 1])
     * @return static|null Экземпляр модели или null, если не найдено.
     * @throws Exception Если соединение с базой данных не установлено.
     */
    public static function findFirst(array $conditions = []): ?self
    {
        $instance = new static(); // Создаем экземпляр для получения tableName и DI
        $connection = $instance->getConnection(); // Получаем соединение через экземпляр

        $tableName = $instance->tableName;

        $whereClause = '';
        $params = [];
        if (!empty($conditions)) {
            $whereClause = ' WHERE ' . implode(' AND ', array_map(function ($key) use (&$params, $conditions) {
                $params[":{$key}"] = $conditions[$key];
                return "`{$key}` = :{$key}";
            }, array_keys($conditions)));
        }

        $stmt = $connection->prepare("SELECT * FROM `{$tableName}` {$whereClause} LIMIT 1");
        $stmt->execute($params);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data) {
            // Заполняем атрибуты модели
            $instance->fill($data);
            return $instance;
        }
        return null;
    }

    /**
     * Находит все записи, соответствующие условиям.
     *
     * @param array $conditions Условия выборки
     * @return static[] Массив экземпляров моделей.
     * @throws Exception Если соединение с базой данных не установлено.
     */
    public static function find(array $conditions = []): array
    {
        $instance = new static(); // Создаем экземпляр для получения tableName и DI
        $connection = $instance->getConnection(); // Получаем соединение через экземпляр

        $tableName = $instance->tableName;

        $whereClause = '';
        $params = [];
        if (!empty($conditions)) {
            $whereClause = ' WHERE ' . implode(' AND ', array_map(function ($key) use (&$params, $conditions) {
                $params[":{$key}"] = $conditions[$key];
                return "`{$key}` = :{$key}";
            }, array_keys($conditions)));
        }

        $stmt = $connection->prepare("SELECT * FROM `{$tableName}` {$whereClause}");
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $models = [];
        foreach ($results as $data) {
            $model = new static();
            $model->fill($data); // Заполняем атрибуты
            $models[] = $model;
        }
        return $models;
    }

    /**
     * Заполняет атрибуты модели из массива.
     *
     * @param array $data Ассоциативный массив данных.
     * @return self
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    /**
     * Сохраняет текущую модель в базе данных (INSERT или UPDATE).
     *
     * @return bool True в случае успеха, False в случае неудачи.
     * @throws Exception Если соединение с базой данных не установлено.
     */
    public function save(): bool
    {
        $this->getConnection(); // Убедимся, что соединение есть

        // Простая логика: если есть 'id', то UPDATE, иначе INSERT.
        // В реальной ORM это будет более надежно.
        if (isset($this->attributes['id']) && $this->attributes['id'] > 0) {
            return $this->update();
        } else {
            return $this->create();
        }
    }

    /**
     * Вставляет новую запись в базу данных.
     *
     * @return bool True в случае успеха.
     * @throws Exception Если соединение с базой данных не установлено.
     */
    protected function create(): bool
    {
        $connection = $this->getConnection();
        $columns = implode(', ', array_map(function ($key) { return "`{$key}`"; }, array_keys($this->attributes)));
        $placeholders = implode(', ', array_map(function ($key) { return ":{$key}"; }, array_keys($this->attributes)));

        $sql = "INSERT INTO `{$this->tableName}` ({$columns}) VALUES ({$placeholders})";
        $stmt = $connection->prepare($sql);

        $result = $stmt->execute($this->attributes);

        if ($result && !isset($this->attributes['id'])) {
            // Если таблица имеет автоинкрементный ID, устанавливаем его
            $this->attributes['id'] = (int)$connection->lastInsertId();
        }
        return $result;
    }

    /**
     * Обновляет существующую запись в базе данных.
     *
     * @return bool True в случае успеха.
     * @throws Exception Если модель не имеет ID для обновления или соединение с БД не установлено.
     */
    protected function update(): bool
    {
        $connection = $this->getConnection();
        if (!isset($this->attributes['id'])) {
            throw new Exception("Cannot update model without an 'id' attribute.");
        }

        $setClause = implode(', ', array_map(function ($key) { return "`{$key}` = :{$key}"; }, array_keys($this->attributes)));
        $sql = "UPDATE `{$this->tableName}` SET {$setClause} WHERE `id` = :id";
        $stmt = $connection->prepare($sql);

        return $stmt->execute($this->attributes);
    }

    /**
     * Удаляет текущую запись из базы данных.
     *
     * @return bool True в случае успеха.
     * @throws Exception Если модель не имеет ID для удаления или соединение с БД не установлено.
     */
    public function delete(): bool
    {
        $connection = $this->getConnection();
        if (!isset($this->attributes['id'])) {
            throw new Exception("Cannot delete model without an 'id' attribute.");
        }

        $sql = "DELETE FROM `{$this->tableName}` WHERE `id` = :id";
        $stmt = $connection->prepare($sql);
        return $stmt->execute([':id' => $this->attributes['id']]);
    }

    /**
     * Магический метод для установки атрибутов.
     *
     * @param string $name Имя атрибута
     * @param mixed $value Значение атрибута
     */
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Магический метод для получения атрибутов.
     *
     * @param string $name Имя атрибута
     * @return mixed Значение атрибута или null, если не найден.
     */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Магический метод для проверки существования атрибута.
     *
     * @param string $name Имя атрибута
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Возвращает все атрибуты модели.
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}