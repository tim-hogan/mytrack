<?php
session_start();
require './includes/classSecure.php';
require './includes/classMyTrackDB.php';
require './includes/classTime.php';
$DB = new MyTrackDB($devt_environment->getDatabaseParameters());

$deviceid = 0;
if (isset($_GET["v"]))
    $deviceid = intval($_GET["v"]);

?>
<!DOCTYPE HTML>
<html>
<head>
    <meta name="viewport" content="width=device-width" />
    <meta name="viewport" content="initial-scale=1.0" />
    <title>MYTRACK</title>
    <style>
        body {font-family: Arial, Helvetica, sans-serif;font-size: 10pt;margin: 0;padding: 0;}
        #container {margin: auto;max-width: 1200px;background-color: #eee; border: solid 1px #888; border-radius: 8px;}
        #heading h1 {margin-left: 10px;color: #7b86ff}
    </style>
</head>
<body>
    <div id="container">
        <div id="heading">
            <h1>MY TRACK</h1>
        </div>
        <div id="trips">
            <h2>TRIPS</h2>
            <table>
                <?php
            $r = allTripsForDevice($deviceid);
            while ($trip = $r->fetch_assoc())
            {
                $strStart = classTimeHelpers::timeFormat24Hr($trip["trip_start"],"Pacific/Auckland");
                $strEnd = classTimeHelpers::timeFormat24Hr($trip["trip_end"],"Pacific/Auckland");
                echo "<tr><td>{$strStart}</td><td>{$strEnd}</td></tr>";
            }
                ?>
            </table>
        </div>
    </div>
</body>
</html>