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


// echo '<pre>',print_r($_SERVER),'</pre>';
// echo '<pre>',print_r($_REQUEST),'</pre>';
// echo '<pre>',print_r($_POST),'</pre>';
// echo '<pre>',print_r($_GET),'</pre>';

if (empty($_REQUEST['search']))
    die();

// die($_SERVER['SERVER_NAME']);

// if( $_SERVER['SERVER_NAME'] == 'localhost' ){
//if( isset($_REQUEST['ss']) && $_REQUEST['ss'] != 'da' ){
//   die( file_get_contents('https://22.avto-as.ru/apiAllAutoparts/api.php?ss=da&search='.$_REQUEST['search'] ?? 'x' ) );
//}

require('./index_f.php');

// echo '<pre>';
// print_r( $_GET );
// echo '</pre>';

// $cfgVar = parse_ini_file( $_SERVER['DOCUMENT_ROOT'].'/.env' , true );

//if( file_exists('./../.env') ) {
//    echo __LINE__;
//}else{
//    echo __LINE__;
//}
//echo '--';
//$cfgVar = parse_ini_file('./../../.env');

//$cfgVar = parse_ini_file('./../.env');
$cfgVar = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/../.env', true);

if (!empty($cfgVar['ALLAUTOPARTS_API_secret']) && !empty($_REQUEST['login']) && $_REQUEST['login'] == $cfgVar['ALLAUTOPARTS_API_secret']) {
    $id = $cfgVar['ALLAUTOPARTS_API_session_id'];
    $login = $cfgVar['ALLAUTOPARTS_API_session_login'];
    $password = $cfgVar['ALLAUTOPARTS_API_session_password'];
} else {
    $id = $_REQUEST['api_id'] ?? 'x';
    $login = $_REQUEST['login'] ?? 'x';
    $password = $_REQUEST['password'] ?? 'x';
}

if ($password == 'x') {
    die(json_encode(['error' => true, 'code' => __LINE__, 'in' => $_REQUEST]));
}


//Обработка входных данных:
//Значения формы по-умолчанию
$data =
$defaults = array(
    'session_id' => $id,
    'session_guid' => '',
    'session_login' => $login,
    'session_password' => $password,
    // 'search_code' => 'OC47',
    'search_code' => (string)$_REQUEST['search'],
    'instock' => 'ON',
    'showcross' => '',
    'periodmin' => 0,
    'periodmax' => 10,
);

//Получение POST данных
// $data = isset($_POST['session_id']) ? array_merge($defaults, $_POST) : $defaults;

//Поиск:
// $action = isset($_POST['do']) ? $_POST['do'] : FALSE;
//$action = 'search';
//
//if ($action !== FALSE) {
//
//    //Нажата одна из кнопок на форме
//    switch ($action) {
//
//        //Нажата кнопка "Поиск"
//        case 'search':

$errors = array();
$parsed_data = $data;   //Данные из формы копируются в другую переменную, чтобы
//подготовить их для формирования запроса.
//Исходные данные будут отображены на форме.

$result = [];

//Проверка данных
if (validateData($parsed_data, $errors)) {

    //Подключение класса SOAP-клиента и создание экземпляра
    require_once("lib/soap_transport.php");
    $SOAP = new soap_transport();

    //Генерация запроса
    $requestXMLstring = createSearchRequestXML($parsed_data);

    //Выполнение запроса
    $responceXML = $SOAP->query('SearchOffer', array('SearchParametersXml' => $requestXMLstring), $errors);
    //Пожалуйста обратите внимание что параметр именованный - SearchParametersXml
    //Для разных методов сервисов это имя параметра разное и в документации оно нигде не описано
    //Для того, чтобы узнать имя параметра следует смотреть WSDL схему
    /*
       Вот примерный порядок действий чтобы узнать имя параметра:
       1. Открываем WSDL схему документа броузером, например, Google Chrome
       Для этого открываем URL https://allautoparts.ru/WEBService/SearchService.svc/wsdl?wsdl
       2. Находим строки
       <xsd:schema targetNamespace="http://tempuri.org/Imports">
          <xsd:import schemaLocation="https://allautoparts.ru/WEBService/SearchService.svc/wsdl?xsd=xsd0" namespace="http://tempuri.org/"/>
          <xsd:import schemaLocation="https://allautoparts.ru/WEBService/SearchService.svc/wsdl?xsd=xsd1" namespace="http://schemas.microsoft.com/2003/10/Serialization/"/>
       </xsd:schema>
       3. Открываем url https://allautoparts.ru/WEBService/SearchService.svc/wsdl?xsd=xsd0
       4. Находим строки соответствующие методу, который вызываем и узнаем имя параметра
       <xs:element name="SearchOffer">
          <xs:complexType>
             <xs:sequence>
                <xs:element minOccurs="0" name="SearchParametersXml" nillable="true" type="xs:string"/>
             </xs:sequence>
          </xs:complexType>
       </xs:element>
       */


    //Получен ответ
    if ($responceXML) {
        //Установка параметра session_guid, полученного из ответа сервиса.
        //Параметр используется, как замена связке session_login + session_password,
        //и при повторном поиске может быть подставлен в запрос вместо неё
        $attr = $responceXML->rows->attributes();
        $data['session_guid'] = (string)$attr['SessionGUID'];

        //Разбор данных ответа
        $result = parseSearchResponseXML($responceXML);
    }
}
//            break;
//    }
//}


// (
//    [AnalogueCode] => OC47
//    [AnalogueCodeAsIs] => OC47
//    [AnalogueManufacturerName] => KNECHT/MAHLE
//    [AnalogueWeight] => 0.000
//    [CodeAsIs] => OC47
//    [DeliveryVariantPriceAKiloForClientDescription] =>
//    [DeliveryVariantPriceAKiloForClientPrice] => 0.00
//    [DeliveryVariantPriceNote] =>
//    [PriceListItemDescription] =>
//    [PriceListItemNote] => [OC47]MAHLE/KNECHT
//    [IsAvailability] => 1
//    [IsCross] => 0
//    [LotBase] => 1
//    [LotType] => 0
//    [ManufacturerName] => KNECHT/MAHLE
//    [OfferName] => MSC-STC-3438
//    [PeriodMin] => 5
//    [PeriodMax] => 5
//    [PriceListDiscountCode] => 583757
//    [ProductName] => Фильтр масляный (Mahle Фильтр масляный)
//    [Quantity] => 22
//    [SupplierID] => 1513
//    [GroupTitle] =>
//    [Price] => 303.00
//    [Reference] => 901911655
// )

die(json_encode(['data' => $result, 'status' => 'success']));
