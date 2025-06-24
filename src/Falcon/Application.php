<?php

namespace Falcon;

use Falcon\Http\Request;
use Falcon\Http\Response;
use Falcon\Router\Router;
use Falcon\Mvc\View;
use Falcon\Mvc\Model; // Для настройки соединения с БД для моделей

class Application {

    protected $di;

    public function __construct(Di $di) {
        $this->di = $di;
        // Регистрируем основные сервисы фреймворка при создании приложения
        $this->registerCoreServices();
    }

    /**
     * Регистрирует основные сервисы фреймворка в DI-контейнере.
     */
    protected function registerCoreServices() {
        // Регистрируем Request как общий (shared) сервис
        $this->di->setShared('request', Request::class);

        // Регистрируем Response как общий сервис
        $this->di->setShared('response', Response::class);

        // Регистрируем Router как общий сервис
        $this->di->setShared('router', Router::class);

        // Регистрируем View как общий сервис
        $this->di->setShared('view', function () {
            $view = new View();
            // Устанавливаем директорию для представлений
            $view->setViewsDir(BASE_PATH . '/app/Views/');
            return $view;
        });

        // Пример регистрации сервиса базы данных (PDO)
        // В реальном приложении здесь будет загрузка конфигурации
        // и создание PDO объекта.
        $this->di->setShared('db', function () {
            // Предполагаем, что у вас есть config.php в app/Config
            $config = include BASE_PATH . '/app/Config/config.php';
            $dbConfig = $config['database'];

            $dsn = "{$dbConfig['adapter']}:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
            $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            return $pdo;
        });

        // Устанавливаем соединение с базой данных для базовой модели
        // Это простой способ для демонстрации, в реальной ORM будет сложнее
        $this->di->getShared('db'); // Инициализируем соединение
        Model::setConnection($this->di->getShared('db'));
    }

    /**
     * Обрабатывает входящий HTTP-запрос.
     *
     * @return Response Объект ответа
     * @throws \Exception Если что-то пошло не так
     */
    public function handle(): Response {
        /** @var Request $request */
        $request = $this->di->getShared('request');
        /** @var Response $response */
        $response = $this->di->getShared('response');
        /** @var Router $router */
        $router = $this->di->getShared('router');

        // Пример регистрации маршрутов. В реальном приложении их можно загружать из файла.
        $router->get('/', ['controller' => 'Index', 'action' => 'index']);
        $router->get('/hello/{name}', ['controller' => 'Index', 'action' => 'hello']);
        $router->post('/submit', ['controller' => 'Index', 'action' => 'submit']);
        $router->get('/users', ['controller' => 'User', 'action' => 'list']);
        $router->get('/users/{id}', ['controller' => 'User', 'action' => 'show']);

        // Обрабатываем маршрут
        $routeInfo = $router->handle($request);

        if ($routeInfo) {
            $paths = $routeInfo['paths'];
            $params = $routeInfo['params'];

            // Если маршрут указывает на callable-функцию
            if (is_callable($paths)) {
                $result = call_user_func_array($paths, $params);
                if ($result instanceof Response) {
                    return $result;
                }
                if ($result !== null) {
                    $response->setContent($result);
                }
            } elseif (is_array($paths) && isset($paths['controller']) && isset($paths['action'])) {
                // Если маршрут указывает на контроллер и действие
                $controllerName = 'App\\Controllers\\' . ucfirst($paths['controller']) . 'Controller';
                $actionName = $paths['action'] . 'Action';

                if (class_exists($controllerName)) {
                    // Разрешаем контроллер через DI-контейнер
                    $controller = $this->di->get($controllerName);

                    // Устанавливаем DI-контейнер для контроллера
                    if (method_exists($controller, 'setDi')) {
                        $controller->setDi($this->di);
                    }

                    if (method_exists($controller, $actionName)) {
                        // Вызываем метод действия, передавая параметры из маршрута
                        $result = call_user_func_array([$controller, $actionName], $params);

                        // Если метод действия возвращает объект Response, используем его
                        if ($result instanceof Response) {
                            return $result;
                        }
                        // Если метод действия возвращает что-то другое (строку, HTML), устанавливаем это как контент ответа
                        if ($result !== null) {
                            $response->setContent($result);
                        }
                    } else {
                        $response->setStatusCode(404)->setContent('Action not found');
                    }
                } else {
                    $response->setStatusCode(404)->setContent('Controller not found');
                }
            } else {
                $response->setStatusCode(500)->setContent('Invalid route definition');
            }
        } else {
            // Если маршрут не найден
            $response->setStatusCode(404)->setContent('Not Found');
        }

        return $response;
    }
}
