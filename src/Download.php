<?php
session_start();
header('Content-type: text/csv');
header("Content-Disposition: attachment; filename=\"Rawdata.csv\"");

require './includes/classSecure.php';
require './includes/classMyTrackDB.php';
$DB = new MyTrackDB($devt_environment->getDatabaseParameters());

$id = 0;
if (isset($_GET["v"]))
{
    $id = intval($_GET["v"]);
}

echo "TIMESTAMP,LATTITUDE,LONGITUDE,HEIGHT,HDOP\r\n";

$r = $DB->allLocsFor($id);

error_log("Return from allLocsFor num rows = {$r->num_rows}");

while ($loc = $r->fetch_assoc())
{
    $dt = new DateTime($loc['loc_timestamp']);
    $dt->setTimezone(new DateTimeZone('Pacific/Auckland'));
    $strT = $dt->format("d/m/Y H:i:s");
    echo "{$strT},{$loc['loc_lat']},{$loc['loc_lon']},{$loc['loc_height']},{$loc['loc_hdop']}\r\n";
}

?>