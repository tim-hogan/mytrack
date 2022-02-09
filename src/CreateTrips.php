<?php
session_start();
require './includes/classSecure.php';
require './includes/classMyTrackDB.php';
$DB = new MyTrackDB($devt_environment->getDatabaseParameters());

$r = $DB->allDevices();
while ($device = $r->fetch_assoc())
{
    $r2 = $DB->allLocsForDeviceNoTrip($device["iddevice"]);
    while ($loc = $r2->fetch_assoc())
    {
        //Check that the time stamp is at least 15 minutes old
        if ( (new DateTime())->getTimestamp() - (new DateTime($loc["loc_timestamp"]))->getTimestamp() > 15*60)
        {
            //Start a new trip

        }

    }

}
?>