<?php

if (empty($_REQUEST['search']))
    die();

require('./index_f.php');

$cfgVar = parse_ini_file('./../../.env');
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
$data = [
    'session_id' => '22148',
    'session_guid' => '',
    'session_login' => $cfgVar['ALLAUTOPARTS_API_session_login'],
    'session_password' => $cfgVar['ALLAUTOPARTS_API_session_password'],
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

    //Генерация запроса
    $requestXMLstring = createSearchRequestXML($data);

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