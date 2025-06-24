<?php

namespace App\Controllers;

use Falcon\Mvc\Controller;

// use App\Models\User; // Подключим, когда будем работать с моделями

/**
 * Контроллер главной страницы.
 */
class IndexController extends Controller
{

    /**
     * Действие для главной страницы.
     */
    public function indexAction()
    {
        $name = $this->request->getQuery('name', 'Гость');
        $this->view->setVar('pageTitle', 'Добро пожаловать в Falcon!');
        $this->view->setVar('name', $name);

        // Рендерим представление 'index/index.phtml'
        return $this->view->render('index.index');
    }

    /**
     * Действие "Привет" с параметром из URL.
     * URL: /hello/{name}
     *
     * @param string $name Имя из URL.
     * @return \Falcon\Http\Response
     */
    public function helloAction($name)
    {
        // Отправляем простой текстовый ответ
        return $this->response->setContent("Привет, {$name}! Добро пожаловать в Falcon!");
    }

    /**
     * Действие для обработки POST-запросов.
     * URL: /submit
     *
     * @return \Falcon\Http\Response
     */
    public function submitAction()
    {
        if ($this->request->getMethod() === 'POST') {
            $data = $this->request->getPost();
            // Здесь могла бы быть логика обработки данных, валидация, сохранение в БД
            // Например:
            // $user = new User();
            // $user->username = $data['username'] ?? 'Anonymous';
            // $user->email = $data['email'] ?? null;
            // $user->save();
            return $this->jsonResponse(['status' => 'success', 'message' => 'Данные получены!', 'received_data' => $data]);
        }
        // Если запрос не POST, возвращаем ошибку метода
        return $this->response->setStatusCode(405)->setContent('Метод не разрешен (Method Not Allowed)');
    }
}
