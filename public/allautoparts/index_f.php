<?php

/**
 * Пример реализации работы с SOAP-сервисами компании Автостелс на языке PHP.
 *
 * Данный скрипт реализует:
 * • работу с сервисом поиска ценовых предложений.
 * • размещение предложения в корзине
 *
 * Системные требования
 * •	PHP5
 * •	Установленный модуль SOAP (обычно входит в дистрибутив PHP5)
 * •	Установленный модуль openssl (обычно входит в дистрибутив PHP5)
 * •	Установленный модуль SimpleXML
 *
 * © Autostels
 */


/**
 * Вспомогательные функции
 */

/**
 * generateRandom
 *
 * Генерирует случайную строку из чисел заданой длины
 *
 * @param int $maxlen длина строки
 * @return string
 */
function generateRandom($maxlen = 32)
{
   $code = '';
   while (strlen($code) < $maxlen) {
      $code .= mt_rand(0, 9);
   }
   return $code;
}

/**
 * validateData
 *
 * Фунцкия производит проверку и подготовку данных для отправки в запрос
 *
 * @param &array $data ссылка на ассоц. массив с данными
 * @param &array $errors ссылка на массив ошибок
 * @return true в случае, если данные корректны, false при ошибке
 */
function validateData(&$data, &$errors)
{
   if (!$data['search_code'])
      $errors[] = 'Необходимо ввести номер для поиска';

   if (!$data['session_id'])
      $errors[] = 'Необходимо указать ID входа для работы с сервисом';

   if ((!$data['session_login'] || !$data['session_password']) && !$data['session_guid'])
      $errors[] = 'Необходимо ввести логин и пароль' . $data['session_guid'];

   $data['instock'] = $data['instock'] ? 1 : 0;
   $data['showcross'] = $data['showcross'] ? 1 : 0;
   $data['periodmin'] = $data['periodmin'] ? (int)$data['periodmin'] : -1;
   $data['periodmax'] = $data['periodmax'] ? (int)$data['periodmax'] : -1;

//   var_dump($errors);

   return count($errors) ? false : true;
}

/**
 * createSearchRequestXML
 *
 * Генерация строки запроса на поиск
 *
 * @param &array $data ссылка на ассоц. массив с данными
 * @return string возвращает строку с XML
 */
function createSearchRequestXML($data)
{
   $session_info = $data['session_guid'] ?
      'SessionGUID="' . $data['session_guid'] . '"' :
      'UserLogin="' . base64_encode($data['session_login']) . '" UserPass="' . base64_encode($data['session_password']) . '"';

   $xml = '<root>
				  <SessionInfo ParentID="' . $data['session_id'] . '" ' . $session_info . '/>
				  <search>
					 <skeys>
						<skey>' . $data['search_code'] . '</skey>
					 </skeys>
					 <instock>' . $data['instock'] . '</instock>
					 <showcross>' . $data['showcross'] . '</showcross>
					 <periodmin>' . $data['periodmin'] . '</periodmin>
					 <periodmax>' . $data['periodmax'] . '</periodmax>
				  </search>
				</root>';
   return $xml;
}

/**
 * createAddBasketRequestXML
 *
 * Генерация строки запроса на добавление в корзину
 *
 * @param &array $data ссылка на ассоц. массив с данными
 * @return string возвращает строку с XML
 */
function createAddBasketRequestXML($data)
{
   $session_info = $data['session_guid'] ?
      'SessionGUID="' . $data['session_guid'] . '"' :
      'UserLogin="' . base64_encode($data['session_login']) . '" UserPass="' . base64_encode($data['session_password']) . '"';
   $xml = '<root>
                 <SessionInfo ParentID="' . $data['session_id'] . '" ' . $session_info . ' />
                 <rows>
                    <row>
                        <Reference>' . $data['Reference'] . '</Reference>
                        <AnalogueCodeAsIs>' . $data['AnalogueCodeAsIs'] . '</AnalogueCodeAsIs>
                        <AnalogueManufacturerName>' . $data['AnalogueManufacturerName'] . '</AnalogueManufacturerName>
                        <OfferName>' . $data['OfferName'] . '</OfferName>
                        <LotBase>' . $data['LotBase'] . '</LotBase>
                        <LotType>' . $data['LotType'] . '</LotType>
                        <PriceListDiscountCode>' . $data['PriceListDiscountCode'] . '</PriceListDiscountCode>
                        <Price>' . $data['Price'] . '</Price>
                        <Quantity>' . $data['Quantity'] . '</Quantity>
                        <PeriodMin>' . $data['PeriodMin'] . '</PeriodMin>
                        <ConstraintPriceUp>-1</ConstraintPriceUp>
                        <ConstraintPeriodMinUp>-1</ConstraintPeriodMinUp>
                    </row>
                 </rows>
				</root>';
   return $xml;
}

/**
 * parseSearchResponseXML
 *
 * Разбор ответа сервиса поиска.
 *
 * Собственно просто преобразует данные из SimpleXMLObject в массив,
 * также добавляет к каждой записи уникальный ReferenceID. В данном примере
 * в этом качестве будет выступать случайным образом сгенерированная строка.
 * В реальном использовании Reference обозначает ID конкретной записи в контексте
 * системы, в которой используются сервисы (например, id из таблицы БД, с которой
 * сопоставлено предложение)
 *
 * @param SimpleXMLObject XML-объект
 * @return array возвращает массив данных
 */
function parseSearchResponseXML($xml)
{
   $data = array();
   foreach ($xml->rows->row as $row) {
      $_row = array();
      foreach ($row as $key => $field) {
          if( $key == 'Price' ){
         $_row[(string)$key] = ceil((int)$field/100*120);
          }else{
         $_row[(string)$key] = htmlspecialchars((string)$field);
          }
      }
      $_row['Reference'] = generateRandom(9);
      $data[] = $_row;
   }
   return $data;
}


/**
 * parseAddBasketResponseXML
 *
 * Разбор ответа сервиса добавления в корзину.
 * Ответ содержит набор строк с результатами размещения выбранные позиций
 * В этом примере разбор ответа сводится к простой конвертации результата в массив.
 * Интерпретация и вывод результата происходит в файле /html/result_basket.html
 *
 * @param SimpleXMLObject $xml XML-объект
 * @return array возвращает массив с результатами
 */
function parseAddBasketResponseXML($xml)
{
   $data = array();
   foreach ($xml->rows->row as $row) {
      $_row = array();
      foreach ($row as $key => $field) {
         $_row[(string)$key] = (string)$field;
      }
      $data[] = $_row;
   }
   return $data;
}
