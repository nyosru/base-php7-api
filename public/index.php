<?php


// Разрешаем доступ с любого домена
header("Access-Control-Allow-Origin: *");

// Разрешаем использование куки и HTTP-аутентификацию
header("Access-Control-Allow-Credentials: true");

// Разрешаем методы запросов (например, GET, POST, OPTIONS)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Разрешаем указанные заголовки в запросе
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Дополнительные настройки для кэширования предварительных запросов
header("Access-Control-Max-Age: 86400"); // 24 часа

// Если запрос метода OPTIONS, завершаем выполнение скрипта
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}



echo __FILE__.' #'.__LINE__;
phpinfo();