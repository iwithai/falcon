<?php

// public/index.php (точка входа)

// 1. Подключаем сам автозагрузчик.
// Это единственный require_once, который нам нужен в начале.
require_once __DIR__ . '/../src/Falcon/Loader.php';

// Используем наш собственный Loader
use Falcon\Loader;

// 2. Регистрируем наш автозагрузчик
Loader::register();

// 3. Добавляем пространства имен и их базовые директории
// Для классов фреймворка Falcon
Loader::addNamespace('Falcon', __DIR__ . '/../src/Falcon/');
// Для классов вашего приложения (контроллеров и т.д.)
Loader::addNamespace('App', __DIR__ . '/../app/');


// Все остальные "use" заявления теперь будут работать благодаря Falcon\Loader
use Falcon\Di;
use Falcon\Mvc\Dispatcher;
use Falcon\Http\Response;
use Falcon\Mvc\View;
use Falcon\Mvc\Url;
use Falcon\Mvc\Router;
use Falcon\Exception;

// 4. Инициализация DI
$di = new Di();
Di::setDefault($di);

// Регистрация сервисов
// Роутер регистрируем первым, так как он нужен для Url
$di->setShared('router', function () {
    $router = new Router();
    $router->setDefaultNamespace('App\\Controllers\\'); // Пространство имен для ваших контроллеров

    // Определение маршрутов (как мы делали ранее)
    $router->add('/', ['controller' => 'Index', 'action' => 'index'], 'home');
    $router->get('/users', ['controller' => 'Users', 'action' => 'list'], 'users_list');
    $router->get('/users/{id:[0-9]+}', ['controller' => 'Users', 'action' => 'view'], 'user_view');
    $router->post('/users', ['controller' => 'Users', 'action' => 'create']);
    $router->add('/about', ['controller' => 'Index', 'action' => 'about'], 'about_us');
    $router->get('/products/{id:[0-9]+}/{slug}', 'Products::show', 'product_show');

    $router->add('/admin/dashboard', [
        'namespace' => 'App\\Controllers\\Admin\\',
        'controller' => 'Dashboard',
        'action' => 'index'
    ], 'admin_dashboard');

    $router->add('/blog/{year:[0-9]{4}}/{month:[0-9]{2}}/{slug}', [
        'namespace' => 'App\\Controllers\\Blog\\',
        'controller' => 'Posts',
        'action' => 'show'
    ], 'blog_post');

    return $router;
});

$di->setShared('url', function () use ($di) {
    $url = new Url();
    $url->setBaseUri('/');
    $url->setRouter($di->getShared('router'));
    return $url;
});

$di->setShared('response', function () {
    return new Response();
});

$di->setShared('view', function () use ($di) {
    $view = new View();
    $view->setViewsDir(__DIR__ . '/../app/views/');
    $view->setDI($di);
    return $view;
});

$di->setShared('dispatcher', function () use ($di) {
    $dispatcher = new Dispatcher($di);
    return $dispatcher;
});

// 5. Использование Роутера для обработки запроса
$router = $di->getShared('router');
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($router->handle($requestUri, $requestMethod)) {
    $dispatcher = $di->getShared('dispatcher');
    $dispatcher->setControllerName($router->getControllerName());
    $dispatcher->setActionName($router->getActionName());
    $dispatcher->setParams($router->getParams());
} else {
    // Маршрут не найден - 404 Not Found
    $response = $di->getShared('response');
    $response->setStatusCode(404, 'Not Found');
    $response->setContent("<h1>404 Not Found</h1><p>The requested URL was not found on this server.</p>");
    $response->send();
    exit;
}

// 6. Диспетчеризация запроса
try {
    $result = $dispatcher->dispatch();

    $response = $di->getShared('response');

    if ($result !== null) {
        $response->setContent((string)$result);
    } elseif ($response->getContent() === null && !$response->isSent()) {
        $view = $di->getShared('view');
        if ($view->isEnabled() && $view->getRenderedContent() !== null) {
            $response->setContent($view->getRenderedContent());
        }
    }

    $response->send();

} catch (Exception $e) {
    error_log("Application Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    $response = $di->getShared('response');
    $response->setStatusCode(500, 'Internal Server Error');
    $response->setContent("<h1>500 Internal Server Error</h1><p>An internal server error occurred. " . $e->getMessage() . "</p>");
    $response->send();
}
