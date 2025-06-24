<?php

return [
    'database' => [
        'adapter'  => 'Mysql',
        'host'     => 'localhost',
        'username' => 'root', // Ваше имя пользователя БД
        'password' => '',     // Ваш пароль БД
        'dbname'   => 'my_falcon_db', // Имя вашей базы данных
        'charset'  => 'utf8mb4',
    ],
    'application' => [
        'baseUri' => '/',
        'debug'   => true, // Режим отладки (true/false)
    ],
    // ... другие настройки (например, для кэширования, логирования и т.д.)
];
