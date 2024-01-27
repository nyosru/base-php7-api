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




if (empty($_REQUEST['search']))
    die();
$errors = [];
require('./index_f.php');

//if (extension_loaded('soap')) {
//    echo 'SOAP поддерживается в вашей конфигурации PHP.';
//} else {
//    echo 'SOAP НЕ поддерживается в вашей конфигурации PHP.';
//}

$cfgVar = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/../.env',true);

if (!empty($_REQUEST['secret']) && $_REQUEST['secret'] == $cfgVar['ALLAUTOPARTS_API_secret']) {
    $session_login = $cfgVar['ALLAUTOPARTS_API_session_login'];
    $session_password = $cfgVar['ALLAUTOPARTS_API_session_password'];
} elseif (!empty($_REQUEST['login']) && !empty($_REQUEST['password'])) {
    $session_login = $_REQUEST['login'];
    $session_password = $_REQUEST['password'];
} else {
    die(json_encode(['error' => __LINE__]));
}

//Значения формы по-умолчанию
$data2 =
$data = [
    'session_id' => '22148',
    'session_guid' => '',
//    'session_login' => $cfgVar['ALLAUTOPARTS_API_session_login'],
    'session_login' => $session_login,
//    'session_password' => $cfgVar['ALLAUTOPARTS_API_session_password'],
    'session_password' => $session_password,
    // 'search_code' => 'OC47',
    'search_code' => $_REQUEST['search'],
    'instock' => 'ON',
    'showcross' => '',
    'periodmin' => 0,
    'periodmax' => 10,
];

$errors = [];

//Проверка данных
if (validateData($data, $errors)) {

    //Подключение класса SOAP-клиента и создание экземпляра
    require_once("./lib/soap_transport.php");
    $SOAP = new soap_transport();

//    var_dump([__LINE__,$SOAP]);

    //Генерация запроса
    $requestXMLstring = createSearchRequestXML($data2);

//    var_dump($requestXMLstring);
//    echo PHP_EOL.PHP_EOL;

    //Выполнение запроса
    $responceXML = $SOAP->query('SearchOffer', array('SearchParametersXml' => $requestXMLstring), $errors);

    var_dump([__LINE__,$responceXML]);
    echo PHP_EOL.PHP_EOL;

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

        var_dump([__LINE__,$result]);

    }
}

// (
//    [AnalogueCode] => OC47
//    [AnalogueCodeAsIs] => OC47
//    [AnalogueManufacturerName] => KNECHT/MAHLE
//    [AnalogueWeight] => 0.000
//    [CodeAsIs] => OC147
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
//    [SupplierID] => 1512213
//    [GroupTitle] =>
//    [Price] => 3033.00
//    [Reference] => 90191162155
// )

die(json_encode($result));