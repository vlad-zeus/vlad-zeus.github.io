<?php

/** Параметры подключения. Один для локальной разработки, второй для хостера.*/
if ($_SERVER['SERVER_NAME'] <> 'myproject.loc') {
    $dbCon = new PDO('dblib:charset=UTF-8; host=91.222.246.89:4869;database=Agent_7_2', 'vgorodetsky', '1715804226Gor');
} else  $dbCon = new PDO('sqlsrv:Server=91.222.246.89,4869;database=Agent_7_2', 'vgorodetsky', '1715804226Gor');

$dbConSMS = new PDO('mysql:host=sql.turbosms.ua;dbname=users;charset=UTF8', 'vitalytour', '1715804226Gor');


/** Запросы */

/** $queryManager - здесь мы выбираем менеджеров. Убраны старые/неиспользуемые менеджеры, выстроены в том порядке, в котором я хочу.*/
$queryManager = "SELECT
                  mng.id
                 ,mng.name
                 FROM dbo.recipient rc
                 INNER JOIN dbo.recipient mng
                  ON rc.managerid = mng.id
                 WHERE mng.id NOT IN (991, 1, 10, 12, 21, 991, 7612, 7953, 7983, 8094, 8820, 9195, 25, 16)
                 GROUP BY mng.name
                         ,mng.id
                 ORDER BY CASE mng.id
                  WHEN '18' THEN 1
                  WHEN '20' THEN 2
                  WHEN '8107' THEN 3
                  WHEN '3978' THEN 4
                  WHEN '2922' THEN 5
                  WHEN '8446' THEN 6
                  WHEN '344' THEN 7
                  ELSE 8
                 END";

/** Выбираем номера заявок из базы */
$queryClaim = "SELECT
                  reservation.id AS id
                 ,reservation.number AS number
                 FROM dbo.reservation
                 WHERE reservation.cdate > DATEADD(DAY, -365, GETDATE())
                 ORDER BY reservation.number DESC";

/** Выбираем тур по покупателю. ТУТ НЕТ ТУРИСТОВ!!! Только покупатель. */
$queryCustomer = "SELECT
                  freight.name AS flightNumber
                 ,forder.datetime AS dateDeparture
                 ,forder.arrivedate AS dateArrive
                 ,LTRIM(townfrom.name) AS townFrom
                 ,fromstation.name AS stationFrom
                 ,LTRIM(townto.name) AS townTo
                 ,tostation.name AS stationTo
                 ,subclaim.extcode AS claimNumberTourOperator
                 ,reservation.number AS claimNumber
                 ,reservation.cdate AS claimDate
                 ,claim_manager.name AS nameManager
                 ,recipient.name AS nameTourist
                 ,recipient.phone
                 ,recipient_1.name AS operator
                 ,job.name AS job
                 FROM
                   dbo.subclaim 
                   INNER JOIN dbo.forder ON forder.subclaimid = subclaim.id 
                   INNER JOIN dbo.freight ON freight.id = forder.freightid 
                   INNER JOIN dbo.class ON class.id = forder.classid 
                   INNER JOIN dbo.town townfrom ON townfrom.id = forder.fromtownid 
                   INNER JOIN dbo.town townto ON townto.id = forder.totownid 
                   INNER JOIN dbo.freighttype ON freighttype.id = forder.freighttypeid 
                   LEFT OUTER JOIN dbo.station fromstation ON fromstation.id = forder.fromstationid 
                   LEFT OUTER JOIN dbo.station tostation ON tostation.id = forder.tostationid 
                   LEFT OUTER JOIN dbo.reservation ON subclaim.reservationid = reservation.id 
                   INNER JOIN dbo.recipient ON reservation.recipientid = recipient.id 
                   LEFT OUTER JOIN dbo.recipient claim_manager ON reservation.humanid = claim_manager.id 
                   INNER JOIN dbo.recipient recipient_1 ON subclaim.legalid = recipient_1.id 
                   INNER JOIN dbo.country ON townfrom.countryid = country.id 
                   INNER JOIN dbo.country country_1 ON townto.countryid = country_1.id
                   INNER JOIN dbo.human ON human.id = recipient.id
                   INNER JOIN dbo.job ON human.jobid = job.id
                 WHERE
                   freighttype.id = 1";

/** Эта функция - костыль. Почему-то на хостинге не отрабатывал вложенный цикл. Вернее, я выходил из цикла после первой итерации.
 * И судя по всему, из-за того, что в цикле располагался запрос к базе.
 * Пришлось все выносить в отдельную функцию и дергать ее из цикла.
 * Тут мы получаем туристов. Берем из предыдущего запроса номер заявки ( @param $claimIdNumber ), и подставляем, чтобы получить туристов, только этой заявки.
 */
function whileForClient($claimIdNumber)
{

    /** Параметры подключения. Один для локальной разработки, второй для хостера.*/
    if ($_SERVER['SERVER_NAME'] <> 'myproject.loc') {
        $dbCon = new PDO('dblib:charset=UTF-8; host=91.222.246.89:4869;database=Agent_7_2', 'vgorodetsky', '1715804226Gor');
    } else  $dbCon = new PDO('sqlsrv:Server=91.222.246.89,4869;database=Agent_7_2', 'vgorodetsky', '1715804226Gor');

    global $messageSelect;
    /** Выбираем тур с туристами. ТУТ НЕТ ПОКУПАТЕЛЯ!!! Только туристы. Хотя, зачастую, среди туристов есть и покупатель, но в данном случае это не важно. */
    $queryTourists = "SELECT
                  freight.name AS flightNumber
                 ,forder.datetime AS dateDeparture
                 ,forder.arrivedate AS dateArrive
                 ,LTRIM(townfrom.name) AS townFrom
                 ,fromstation.name AS stationFrom
                 ,LTRIM(townto.name) AS townTo
                 ,tostation.name AS stationTo
                 ,class.name AS className
                 ,freighttype.name AS freighttypename
                 ,subclaim.datebeg AS begDateTour
                 ,subclaim.dateend AS endDateTour
                 ,reservation.number AS claimNumber
                 ,subclaim.extcode AS claimNumberTourOperator
                 ,recipient.managerid AS managerId
                 ,reservation.cdate AS claimDate
                 ,claim_manager.name AS nameManager
                 ,recipient_1.name AS operator
                 ,country.name AS countryFrom
                 ,country_1.name AS countryTo
                 ,recipient.name AS customerName
                 ,recipient.phone AS phone
                 ,recipient_2.name AS nameTourist
                 ,recipient_2.phone AS touristPhone
                 ,job.name AS job
                FROM dbo.subclaim INNER JOIN dbo.forder ON forder.subclaimid = subclaim.id
                INNER JOIN dbo.freight ON freight.id = forder.freightid INNER JOIN dbo.class ON class.id = forder.classid
                INNER JOIN dbo.town townfrom ON townfrom.id = forder.fromtownid INNER JOIN dbo.town townto ON townto.id = forder.totownid
                INNER JOIN dbo.freighttype ON freighttype.id = forder.freighttypeid LEFT OUTER JOIN dbo.station fromstation ON fromstation.id = forder.fromstationid
                LEFT OUTER JOIN dbo.station tostation ON tostation.id = forder.tostationid 
                LEFT OUTER JOIN dbo.reservation ON subclaim.reservationid = reservation.id 
                INNER JOIN dbo.recipient ON reservation.recipientid = recipient.id
                LEFT OUTER JOIN dbo.recipient claim_manager ON reservation.humanid = claim_manager.id
                INNER JOIN dbo.recipient recipient_1 ON subclaim.legalid = recipient_1.id
                INNER JOIN dbo.country ON townfrom.countryid = country.id
                INNER JOIN dbo.country country_1 ON townto.countryid = country_1.id
                INNER JOIN dbo.people ON people.reservationid = reservation.id
                INNER JOIN dbo.recipient recipient_2 ON people.humanid = recipient_2.id
                INNER JOIN dbo.human ON human.id = recipient_2.id
                INNER JOIN dbo.job ON human.jobid = job.id
                ";

    /** Наверное, уже избыточно. Поскольку используется функция - то запрос на каждую заявку обнуляется.*/
    $queryTourist = $queryTourists;

    /** Подставляем в запрос номер заявки*/
    $andClaimId = "\r\n WHERE reservation.number =" . '\'' . $claimIdNumber . '\'';

    /** В конце добавляем сортировку скрипта.*/
    $orderBy = "\r\n ORDER BY claimNumber, flightNumber, dateDeparture, nameTourist";

    /**Клеим запрос. If нужен для того, чтобы понимать, подставляем дату в скрипт или нет*/
    $andFromUkraine = "\r\n AND country.id = 169";
    $andNotFromUkraine = "\r\n AND country.id != 169";

    if ($messageSelect !== '4') {
        $queryTourist .= $andFromUkraine;
    } else {
        $queryTourist .= $andNotFromUkraine;
    }

    $queryTourist .= $andClaimId;
    $queryTourist .= $orderBy;

    /** Формируем HTML таблицу с результатами.*/
    $resultQueryTourists = $dbCon->query($queryTourist);
    $resultQueryTourists->execute();

    foreach ($resultQueryTourists as $rowTourist) {
        echo '<tr>' .
            '<td class="text-center">' . $rowTourist['claimNumber'] . '</td>' .
            '<td class="text-center">' . $dateClaim = date('d.m.Y', strtotime($rowTourist['claimDate'])) . '</td>' .
                '<td class="text-center">' . $rowTourist['claimNumberTourOperator'] . '</span></td>' .
                '<td class="text-center">' . str_replace(" ", "", $rowTourist['flightNumber']) . '</td>' .
                '<td class="text-center">' . $dateDeparture = date('d.m.Y H:i:s', strtotime($rowTourist['dateDeparture'])) . '</td>' .
                    '<td class="text-center">' . $dateArrive = date('d.m.Y H:i:s', strtotime($rowTourist['dateArrive'])) . '</td>' .
                        '<td>' . $rowTourist['townFrom'] . '</td>' .
                        '<td>' . $rowTourist['stationFrom'] . '</td>' .
                        '<td>' . $rowTourist['townTo'] . '</td>' .
                        '<td>' . $rowTourist['stationTo'] . '</td>' .
                        '<td>' . $rowTourist['nameTourist'] . '</td>' .
                        '<td>' . $rowTourist['job'] . '</td>' .
                        '<td>' . $rowTourist['touristPhone'] . '</td>' .
                        '<td>' . $rowTourist['operator'] . '</td>' .
                        '<td>' . substr($rowTourist['nameManager'], 0, strripos($rowTourist['nameManager'], ' ')) . '</td>' .
                        '<td style="display:none;">' . $messageSelect . '</td>' . '</tr>';
    }

    /** Закрывайем подключение к базе */
    $dbCon = null;
}

/** Эта функция - костыль. Почему-то на хостинге не отрабатывал вложенный цикл. Вернее, я выходил из цикла после первой итерации.
 * И судя по всему, из-за того, что в цикле располагался запрос к базе.
 * Пришлось все выносить в отдельную функцию и дергать ее из цикла.
 * Тут мы получаем туристов. Берем из предыдущего запроса номер заявки ( @param $claimNumber ), и подставляем, чтобы получить туристов, только этой заявки.
 * @return  $isTourist Возвращаем признак, является ли заказчик в данном случае туристом.
 */

function CustomerIsTourist($claimNumber)
{
    /** Параметры подключения. Один для локальной разработки, второй для хостера.*/
    if ($_SERVER['SERVER_NAME'] <> 'myproject.loc') {
        $dbCon = new PDO('dblib:charset=UTF-8; host=91.222.246.89:4869;database=Agent_7_2', 'vgorodetsky', '1715804226Gor');
    } else  $dbCon = new PDO('sqlsrv:Server=91.222.246.89,4869;database=Agent_7_2', 'vgorodetsky', '1715804226Gor');

    /** Проверяем, является ли покупатель туристом */
    $queryCustomerIsTourist = '
SELECT
   (CASE
       WHEN exists(SELECT
  recipient_2.id AS touristId
FROM dbo.subclaim INNER JOIN dbo.forder ON forder.subclaimid = subclaim.id
INNER JOIN dbo.freight ON freight.id = forder.freightid
INNER JOIN dbo.class ON class.id = forder.classid
INNER JOIN dbo.town townfrom ON townfrom.id = forder.fromtownid
INNER JOIN dbo.town townto ON townto.id = forder.totownid
INNER JOIN dbo.freighttype ON freighttype.id = forder.freighttypeid
LEFT OUTER JOIN dbo.station fromstation ON fromstation.id = forder.fromstationid
LEFT OUTER JOIN dbo.station tostation ON tostation.id = forder.tostationid
LEFT OUTER JOIN dbo.reservation ON subclaim.reservationid = reservation.id
INNER JOIN dbo.recipient ON reservation.recipientid = recipient.id
LEFT OUTER JOIN dbo.recipient claim_manager ON reservation.humanid = claim_manager.id
INNER JOIN dbo.recipient recipient_1 ON subclaim.legalid = recipient_1.id
INNER JOIN dbo.country ON townfrom.countryid = country.id
INNER JOIN dbo.country country_1 ON townto.countryid = country_1.id
INNER JOIN dbo.people ON people.reservationid = reservation.id
INNER JOIN dbo.recipient recipient_2 ON people.humanid = recipient_2.id
WHERE country.id = 169
AND reservation.number = :claimId1
AND recipient_2.id = (SELECT recipient.id AS customerId
FROM dbo.subclaim
INNER JOIN dbo.forder ON forder.subclaimid = subclaim.id
INNER JOIN dbo.freight ON freight.id = forder.freightid
INNER JOIN dbo.class ON class.id = forder.classid
INNER JOIN dbo.town townfrom ON townfrom.id = forder.fromtownid
INNER JOIN dbo.town townto ON townto.id = forder.totownid
INNER JOIN dbo.freighttype ON freighttype.id = forder.freighttypeid
LEFT OUTER JOIN dbo.station fromstation ON fromstation.id = forder.fromstationid
LEFT OUTER JOIN dbo.station tostation ON tostation.id = forder.tostationid
LEFT OUTER JOIN dbo.reservation ON subclaim.reservationid = reservation.id
INNER JOIN dbo.recipient ON reservation.recipientid = recipient.id
LEFT OUTER JOIN dbo.recipient claim_manager ON reservation.humanid = claim_manager.id
INNER JOIN dbo.recipient recipient_1 ON subclaim.legalid = recipient_1.id
INNER JOIN dbo.country ON townfrom.countryid = country.id
INNER JOIN dbo.country country_1 ON townto.countryid = country_1.id
WHERE freighttype.id = 1
AND country.id = 169
AND reservation.number = :claimId2)
) THEN 1 ELSE 0 END) AS Result';

    $resultQueryCustomerIsTourist = $dbCon->prepare($queryCustomerIsTourist, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
    $resultQueryCustomerIsTourist->execute(array(':claimId1' => $claimNumber, ':claimId2' => $claimNumber));
    $rowResultQueryCustomerIsTourist = $resultQueryCustomerIsTourist->fetch();
    return $isTourist = $rowResultQueryCustomerIsTourist ['Result'];

    /** Закрывайем подключение к базе */
    $dbCon = 'null';
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Отправка смс</title>
    <!-- 1. Подключить CSS-файл Bootstrap 3 -->
    <link rel="stylesheet" href="css/bootstrap.min.css"/>
    <!-- 2. Подключить библиотеку jQuery -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"
            integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script
            <!-- 3. Подключить библиотеку moment -->
    <script src="js/moment-with-locales.min.js"></script>
    <!-- 4. Подключить js-файл фреймворка Bootstrap 3 -->
    <script src="js/bootstrap.min.js"></script>
    <!-- 5. Подключить DataTable -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.20/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8"
            src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.js"></script>
    <script src="https://code.jquery.com/ui/1.12.0/jquery-ui.min.js"
            integrity="sha256-eGE6blurk5sHj+rmkfsGYeKyZx3M4bG+ZlFyA7Kns7E=" crossorigin="anonymous"></script>
    <link rel="stylesheet" type="text/css"
          href="https://cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css">
    <script src="https://cdn.datatables.net/buttons/1.2.2/js/buttons.html5.js"></script>
    <link rel="stylesheet" type="text/css"
          href="https://cdn.datatables.net/buttons/1.6.1/css/buttons.dataTables.css">
    <script src="https://cdn.datatables.net/select/1.3.1/css/select.dataTables.css"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.32/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.32/vfs_fonts.js"></script>

    <link href="https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.css" rel="stylesheet" type="text/css"/>
    <script src="https://cdn.datatables.net/buttons/1.5.1/js/dataTables.buttons.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.5.1/js/buttons.colVis.min.js"></script>
    <script type="text/javascript" language="javascript"
            src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

    <script src="https://cdn.datatables.net/buttons/1.5.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.print.min.js"></script>

    <style>
        .container {
            width: 90%;
        }
    </style>

</head>
<body>
<div class="container">
    <style>
        .menu {
            margin: 1em 0em;
        }

        .box {
            position: absolute;
            top: -1200px;
            width: 100%;
            color: #fff;
            margin: auto;
            padding: 0px;
            z-index: 999999;
            text-align: right;
            left: 3em;
        }

        a.boxclose {
            cursor: pointer;
            text-align: center;
            display: block;
            position: absolute;
            top: 5px;
            right: 320px;
        }

        .menu_box_list {
            display: inline-block;
            float: left;
            margin-left: 1em;
        }

        .menu_box_list ul li {
            display: inline-block;
        }

        .menu_box_list li a {
            color: #0c0c0c;
            font-size: 1.2em;
            font-weight: 400;
            display: block;
            padding: 0.5em 0.5em;
            text-decoration: none;
            text-transform: uppercase;
            -webkit-transition: all 0.5s ease-in-out;
            -moz-transition: all 0.5s ease-in-out;
            -o-transition: all 0.5s ease-in-out;
            transition: all 0.5s ease-in-out;
            letter-spacing: 0.1em;
        }

        .menu_box_list li a:hover, .menu_box_list ul li.active a {
            color: #E74C3C;
        }

        .menu_box_list ul {
            background: transparent;
            padding: 9px;
        }

        .menu_box_list li a > i > img {
            vertical-align: middle;
            padding-right: 10px;
        }
    </style>
    <div class="col-lg-12 col-md-12 col-xs-12">
        <div class="col-lg-10 col-md-12 col-xs-12">
            <div class="menu">
                <a href="#" id="activator"><img src="menu.png" alt=""/></a>
                <div class="box" id="box">
                    <div class="box_content">
                        <div class="menu_box_list">
                            <ul>
                                <li><a href="http://vitalytour.com.ua/smsviber/sendMessage.php">Отправка SMS</a></li>
                                <li><a href="http://vitalytour.com.ua/smsviber/reportMessage.php">Отчет по SMS</a></li>
                                <li><a href="http://vitalytour.com.ua/smsviber/menedgerwork.php">Работа менеджеров</a>
                                </li>
                                <div class="clearfix"></div>
                            </ul>
                        </div>
                        <a class="boxclose" id="boxclose"><img src="close.png" alt=""/></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-12 col-xs-12">
            <h4 class="text-center">Баланс</h4>
            <?php
            $url = 'https://api.turbosms.ua/user/balance.json?token=9a29272c4a1773e3187deb8043f20b8354f5c780';
            # Получаем json курсов валют
            $content = file_get_contents($url);
            # Декодируем. На выходе получили массив
            $obj = json_decode($content, true);

            $test = $obj['response_result']['balance'] ;
            echo'<h4 class="text-center" style="color:Red; font-weight:bold">' .  $test . '</h4>' . PHP_EOL;
            ?>
        </div>
    </div>


    <script>
        var $ = jQuery.noConflict();
        $(function () {
            $('#activator').click(function () {
                $('#box').animate({'top': '0px'}, 500);
            });
            $('#boxclose').click(function () {
                $('#box').animate({'top': '-700px'}, 500);
            });
        });
    </script>

    <h3 class="text-uppercase text-center">Отправка SMS клиентам.</h3>
    <form method="post" action="" id="deals_report" name="deals_report" target="_self">
        <div class="col-lg-2 col-md-12 col-xs-12">
            <div class="form-group">
                <h4 id="datefrom" class="text-center">Дата с</h4>
                <input type="text" class="form-control" name="dealsReportDateFrom" id="dealsReportDateFrom">
            </div>
        </div>
        <div class="col-lg-2 col-md-12 col-xs-12">
            <div class="form-group">
                <h4 id="dateto" class="text-center">Дата по</h4>
                <input type="text" class="form-control" name="dealsReportDateTo" id="dealsReportDateTo">
            </div>
        </div>
        <div class="col-lg-2 col-md-12 col-xs-12">
            <div class="form-group">
                <h4 class="text-center">Менеджер</h4>
                <select name="managerSelect" class="form-control" id="managerSelect">
                    <option value="">Выберите менеджера</option>
                    <?php
                    $resultManagerSelect = $dbCon->query($queryManager);
                    /** Выводим результаты по менеджерам */
                    while ($rowManagerSelect = $resultManagerSelect->fetch()) echo "<option value='" . $rowManagerSelect['id'] . "'>" . substr($rowManagerSelect['name'], 0, strripos($rowManagerSelect['name'], ' ')) . "</option>" . PHP_EOL;
                    ?>
                </select>
            </div>
        </div>
        <div class="col-lg-2 col-md-12 col-xs-12">
            <div class="form-group">
                <h4 class="text-center">Заявка</h4>
                <select name="claimSelect" class="form-control" id="claimSelect">
                    <option value="">Выберите номер заявки</option>
                    <?php
                    $resultClaimSelect = $dbCon->query($queryClaim);
                    /** Выводим результаты по заявкам*/
                    while ($rowClaimSelect = $resultClaimSelect->fetch()) echo "<option value='" . $rowClaimSelect['id'] . "'>" . $rowClaimSelect['number'] . "</option>" . PHP_EOL;
                    ?>
                </select>
            </div>
        </div>
        <div class="col-lg-2 col-md-12 col-xs-12">
            <div class="form-group">
                <h4 class="text-center">Отображение</h4>
                <select name="typeSelect" class="form-control" id="typeSelect">
                    <option value="2">Покупатели и туристы</option>
                    <option value="1">Только покупатели</option>
                </select>
            </div>
        </div>
        <div class="col-lg-2 col-md-12 col-xs-12">
            <div class="form-group">
                <h4 class="text-center">Тип сообщения</h4>
                <select name="messageSelect" class="form-control" id="messageSelect">
                    <option value="1">Деньги, бронь</option>
                    <option value="2">Подтверждение</option>
                    <option value="3">За день до вылета</option>
                    <option value="4">Обратный вылет</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12 col-md-12 col-xs-12">
                <button id="submit" type="submit" class="btn btn-success center-block" name="submit_table">Сформировать
                    таблицу туристов
                </button>
            </div>
        </div>
        <div class="nullrow">
        </div>
        <style>
            .nullrow {
                height: 10px; /* Высота блока */
            }
        </style>
    </form>

    <script src="//cdn.jsdelivr.net/momentjs/latest/moment-with-locales.min.js"></script>
    <script src="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js"></script>
    <script type="text/javascript" src="js/daterangepicker-style-and-post.js"></script>

    <?php

    /** Если нажали кнопку формирования таблицы - забираем все отправленное.*/
    if (isset($_POST['submit_table'])) {
        $begDate = $_POST['dealsReportDateFrom'];
        $endDate = $_POST['dealsReportDateTo'];
        $managerId = $_POST['managerSelect'];
        $claimId = $_POST['claimSelect'];
        $typeSelect = $_POST['typeSelect'];
        $messageSelect = $_POST['messageSelect'];
        unset($_POST['messageSelect']);


        /** Это все куски для запроса. Ниже условия, по которым склеивается sql запрос" */
        /** Этот if для локальной разработки. Конвертация дат для разных драйверов PDO разная */

        switch ($messageSelect) {
            case 1:
                $andDateBegEnd = "\r\n AND reservation.cdate BETWEEN CAST('" . date('Y.m.d', strtotime($begDate)) . " 00:00:00' as datetime) AND CAST('" . date('Y.m.d', strtotime($endDate)) . " 23:59:59' as datetime) ";
                break;
            case 2:
                $andDateBegEnd = "\r\n AND reservation.cdate BETWEEN CAST('" . date('Y.m.d', strtotime($begDate)) . " 00:00:00' as datetime) AND CAST('" . date('Y.m.d', strtotime($endDate)) . " 23:59:59' as datetime) ";
                break;
            case 3:
                $andDateBegEnd = "\r\n AND forder.datetime BETWEEN CAST('" . date('Y.m.d', strtotime($begDate)) . " 00:00:00' as datetime) AND CAST('" . date('Y.m.d', strtotime($endDate)) . " 23:59:59' as datetime) ";
                break;
            case 4:
                $andDateBegEnd = "\r\n AND forder.datetime BETWEEN CAST('" . date('Y.m.d', strtotime($begDate)) . " 00:00:00' as datetime) AND CAST('" . date('Y.m.d', strtotime($endDate)) . " 23:59:59' as datetime) ";
                break;
        }

        $andMangerIdSql = "\r\n AND claim_manager.id = $managerId";
        $andClaimIdSql = "\r\n AND reservation.id = $claimId";
        $andFromUkraine = "\r\n AND country.id = 169";
        $andNotFromUkraine = "\r\n AND country.id != 169";
        $orderBySql = "\r\n ORDER BY claimNumber";


        /** Это для передачи в процедуру. Так мы будем опрделять, надо ли подклеивать дату в запрос. */
        if (!empty($claimId)) {
            $andDateBegEnd = 0;
        }

        /** Если не выбирали номер заявки - добавляем условие по дате. А если выбрали - дату игнорируем. */
        if (empty($claimId)) {
            $queryCustomer .= $andDateBegEnd;
        }


        /** Если выбрали менеджера - добавляем условие по менеджеру, если не выбран номер заявки. НЕ ДОБАВЛЯЕМ менеджера, если выбрали номер заявки.*/
        if (!empty($managerId) and empty($claimId)) {
            $queryCustomer .= $andMangerIdSql;
        }

        /** Если выбрали заявку - добавляем условие по заявке.*/
        if (!empty($claimId)) {
            $queryCustomer .= $andClaimIdSql;
        }

        /** Если тип сообщения 4, то фильтруем тех, кто возвращаетсяч в страну.*/
        if ($messageSelect !== '4') {
            $queryCustomer .= $andFromUkraine;
        } else {
            $queryCustomer .= $andNotFromUkraine;
        }

        /** В конце добавляем сортировку скрипта.*/
        $queryCustomer .= $orderBySql;

        /** Формируем HTML таблицу с результатами.*/
        $resultQueryCustomer = $dbCon->prepare($queryCustomer);
        $resultQueryCustomer->execute();
        /*     $arr=$resultQueryCustomer->fetchAll();
               var_dump($arr);*/


        echo '<table data-page-length=\'50\' id="airtable"  class="display table table-bordered table-bordered table-hover table-condensed table-responsive ">' .
            '<caption style="padding: 2px; vertical-align: top; text-align: center;"><H3>Список туристов с ' . date('d.m.Y', strtotime($begDate)) . ' по ' . date('d.m.Y', strtotime($endDate)) . '</H3></caption>' .
            '<thead>' .
            '<tr>' .
            '<th colspan="3" style="padding: 2px; vertical-align: top; text-align: center;" >Заявка</th>' .
            '<th colspan="7" style="padding: 2px; vertical-align: top; text-align: center;" >Информация о перелете</th>' .
            '<th colspan="3" style="padding: 2px; vertical-align: top; text-align: center;" >Турист</th>' .
            '<th rowspan="2" style="padding: 2px; vertical-align: top; text-align: center;" >Оператор</th>' .
            '<th rowspan="2" style="padding: 2px; vertical-align: top; text-align: center;" >Менеджер</th>' .
            '<th rowspan="2" style="display:none;" class="text-center" style="width: 0%"></th>' .
            '</tr>' .
            '<tr>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >№</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Дата</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Код</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Рейс</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Вылет</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Прилет</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Вылет</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Аэропорт</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Прилет</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Аэропорт</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >ФИО</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >ОПР</th>' .
            '<th rowspan="1" style="padding: 2px; vertical-align: top; text-align: center;" >Телефон</th>' .
            '</tr>' .
            '</thead>' .
            '<tbody>';


        /** Этот кусок - мы получаем заказчика */
        /*var_dump($resultQueryCustomer);*/

        foreach ($resultQueryCustomer as $rowCustomer) {

            /*var_dump($rowResultQueryCustomerIsTourist);*/
            /** Условие по которому, заказчик выделяется другим цветом, если он не является туристом. */
            if (CustomerIsTourist($rowCustomer['claimNumber']) == 1) {
                $spanStyle = '<span style=font-weight:bold>';
            } else $spanStyle = '<span style="color:DarkGreen; font-weight:bold">';

            echo '<tr>' .
                '<td class="text-center">' . $spanStyle . $rowCustomer['claimNumber'] . '</span></td>' .
                '<td class="text-center">' . $spanStyle . $dateClaim = date('d.m.Y', strtotime($rowCustomer['claimDate'])) . '</span></td>' .
                    '<td class="text-center">' . $spanStyle . $rowCustomer['claimNumberTourOperator'] . '</span></td>' .
                    '<td class="text-center">' . $spanStyle . str_replace(" ", "", $rowCustomer['flightNumber']) . '</span></td>' .
                    '<td class="text-center">' . $spanStyle . $dateDeparture = date('d.m.Y H:i:s', strtotime($rowCustomer['dateDeparture'])) . '</span></td>' .
                        '<td class="text-center">' . $spanStyle . $dateArrive = date('d.m.Y H:i:s', strtotime($rowCustomer['dateArrive'])) . '</span></td>' .
                            '<td>' . $spanStyle . $rowCustomer['townFrom'] . '</span></td>' .
                            '<td>' . $spanStyle . $rowCustomer['stationFrom'] . '</span></td>' .
                            '<td>' . $spanStyle . $rowCustomer['townTo'] . '</span></td>' .
                            '<td>' . $spanStyle . $rowCustomer['stationTo'] . '</span></td>' .
                            '<td>' . $spanStyle . $rowCustomer['nameTourist'] . '</span></td>' .
                            '<td>' . $spanStyle . $rowCustomer['job'] . '</span></td>' .
                            '<td>' . $spanStyle . $rowCustomer['phone'] . '</span></td>' .
                            '<td>' . $spanStyle . $rowCustomer['operator'] . '</span></td>' .
                            '<td>' . $spanStyle . substr($rowCustomer['nameManager'], 0, strripos($rowCustomer['nameManager'], ' ')) . '</span></td>' .
                            '<td style="display:none;">' . $messageSelect . '</span></td>' . '</tr>';

            /** Вызываем функцию, которая добавит в таблицу клиентов */
            if ($typeSelect == "2") {
                whileForClient($rowCustomer['claimNumber'], $andDateBegEnd);
            }


        }

        echo '</tbody>';
        echo '</table>';

        /** Это кнопка отправки сообщений, появляется после формирования таблицы*/
        echo '    <div class="row">
                            <div class="col-xs-12">
                            <button id ="button_sms" class="btn btn-success center-block" name="submit_sms" type="button_sms">Отправить сообщения
                            </button>
                            </div>
                      </div>';

    }

    /** Закрывайем подключение к базе */
    $dbCon = null;
    ?>

    <!--Работа с таблицами, сортировка, поиск, пагинация, перевод-->
    <script>
        var select = document.getElementById("messageSelect");
        var input = document.querySelector("input");

        select.addEventListener("change", function () {
            if (input.value == '4') {
                document.getElementById('datefrom').innerHTML = 'Тест';
            }
            // input.value = this.value;
            // Если нужен текст
        });

        $(document).ready(function () {
            var eventFired = function (type) {
                var n = $('#demo_info')[0];
                n.innerHTML += '<div>' + type + ' event - ' + new Date().getTime() + '</div>';
                n.scrollTop = n.scrollHeight;
            };


        });


        $.fn.dataTable.ext.type.detect.unshift(
            function (d) {
                return d === 'Low' || d === 'Medium' || d === 'High' ?
                    'salary-grade' :
                    null;
            }
        );

        $.fn.dataTable.ext.type.order['salary-grade-pre'] = function (d) {
            switch (d) {
                case 'Low':
                    return 1;
                case 'Medium':
                    return 2;
                case 'High':
                    return 3;
            }
            return 0;
        };

        $(document).ready(function () {
            let table = $('#airtable').DataTable({
                dom: 'lBfrtip',
                buttons: [
                    {
                        extend: 'print', exportOptions:
                            {columns: ':visible'}
                    },
                    {
                        extend: 'copy', exportOptions:
                            {columns: ':visible'}
                    },
                    {
                        extend: 'excel', exportOptions:
                            {columns: ':visible'}
                    },
                    {
                        extend: 'pdf', exportOptions:
                            {columns: [0, 1, 2, 3, 4]}
                    },
                    {extend: 'colvis', postfixButtons: ['colvisRestore']}
                ],
                select: true,
                language: {
                    processing: "Подождите...",
                    search: "Поиск:",
                    lengthMenu: "Показать _MENU_ записей",
                    info: "Записи с _START_ до _END_ из _TOTAL_ записей",
                    infoEmpty: "Записи с 0 до 0 из 0 записей",
                    infoFiltered: "(отфильтровано из _MAX_ записей)",
                    infoPostFix: "",
                    loadingRecords: "Загрузка записей...",
                    zeroRecords: "Записи отсутствуют.",
                    emptyTable: "В таблице отсутствуют данные",
                    paginate: {
                        first: "Первая",
                        previous: "Предыдущая",
                        next: "Следующая",
                        last: "Последняя"
                    },
                    buttons: {
                        print: 'Печать',
                        copy: 'Буфер обмена',
                        colvis: 'Видимость колонок',
                        colvisRestore: 'Все колонки'
                    }, //buttons
                    aria: {
                        sortAscending: ": активировать для сортировки столбца по возрастанию",
                        sortDescending: ": активировать для сортировки столбца по убыванию"

                    }
                },
                "lengthMenu": [[10, 15, 25, 50, -1], [10, 15, 25, 50, "All"]]
            });


            $('#airtable tbody').on('click', 'tr', function () {
                $(this).toggleClass('selected');
            });

            $('#button_sms').click(function () {
                let myArray = table.rows('.selected').data().toArray();
                $.ajax({
                    type: "POST",
                    url: "sms.php",
                    data: {service: JSON.stringify(myArray)},
                    success: function (res) {
                        console.log(res);
                    }
                });
                console.log(myArray);

            });
        });

    </script>
    <?php
    unset($_POST);
    ?>

</div>
</body>
</html>
