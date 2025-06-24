<?php

namespace Falcon;

/**
 * Контейнер внедрения зависимостей (Dependency Injection Container).
 * Позволяет управлять зависимостями и сервисами приложения.
 */
class Di {

    protected $services = [];
    protected $sharedInstances = [];

    /**
     * Регистрирует сервис.
     *
     * @param string $name Название сервиса
     * @param mixed $definition Определение сервиса (класс, callable-функция или значение)
     */
    public function set($name, $definition) {
        
        $this->services[$name] = $definition;
        // Убедимся, что сервис не помечен как общий, если был зарегистрирован как shared ранее
        unset($this->sharedInstances[$name]);
    }

    /**
     * Регистрирует общий (shared) сервис.
     * Общие сервисы создаются только один раз и переиспользуются.
     *
     * @param string $name Название сервиса
     * @param mixed $definition Определение сервиса
     */
    public function setShared($name, $definition) {
        $this->services[$name] = $definition;
        $this->sharedInstances[$name] = null; // Будет инициализирован при первом запросе
    }

    /**
     * Возвращает экземпляр сервиса.
     * Если сервис общий, вернет существующий экземпляр; иначе создаст новый.
     *
     * @param string $name Название сервиса
     * @param array|null $parameters Параметры для передачи в конструктор (если применимо)
     * @return mixed Экземпляр сервиса
     * @throws \Exception Если сервис не найден
     */
    public function get($name, array $parameters = null) {
        if (!isset($this->services[$name])) {
            throw new \Exception("Service '{$name}' not found in DI container.");
        }

        $definition = $this->services[$name];

        // Если сервис общий и уже создан, возвращаем его
        if (array_key_exists($name, $this->sharedInstances) && $this->sharedInstances[$name] !== null) {
            return $this->sharedInstances[$name];
        }

        $instance = null;
        if (is_callable($definition)) {
            // Если определение - callable-функция, вызываем её
            $instance = call_user_func_array($definition, $parameters ?: []);
        } elseif (is_string($definition) && class_exists($definition)) {
            // Если определение - имя класса, создаем его экземпляр
            $reflector = new \ReflectionClass($definition);
            $constructor = $reflector->getConstructor();
            if ($constructor) {
                $args = [];
                foreach ($constructor->getParameters() as $param) {
                    $paramType = $param->getType();
                    // Попытка автоматически разрешить зависимости из DI-контейнера
                    if ($paramType && !$paramType->isBuiltin() && class_exists($paramType->getName())) {
                        $args[] = $this->get($paramType->getName()); // Рекурсивный вызов get()
                    } elseif (isset($parameters[$param->getName()])) {
                        // Используем параметры, переданные вручную
                        $args[] = $parameters[$param->getName()];
                    } elseif ($param->isDefaultValueAvailable()) {
                        // Используем значение по умолчанию
                        $args[] = $param->getDefaultValue();
                    } else {
                        // Если параметр не может быть разрешен, бросаем исключение или передаем null
                        throw new \Exception("Cannot resolve dependency for parameter '{$param->getName()}' in service '{$name}'.");
                    }
                }
                $instance = $reflector->newInstanceArgs($args);
            } else {
                $instance = new $definition();
            }
        } else {
            // Если определение - простое значение (например, строка или число)
            $instance = $definition;
        }

        // Если сервис общий, сохраняем его экземпляр
        if (array_key_exists($name, $this->sharedInstances)) {
            $this->sharedInstances[$name] = $instance;
        }

        return $instance;
    }

    /**
     * Возвращает общий (shared) экземпляр сервиса.
     * Всегда возвращает один и тот же экземпляр.
     *
     * @param string $name Название сервиса
     * @param array|null $parameters Параметры (используются только при первом создании)
     * @return mixed Экземпляр сервиса
     * @throws \Exception Если сервис не найден
     */
    public function getShared($name, array $parameters = null) {
        
        return $this->get($name, $parameters);
    }
}
