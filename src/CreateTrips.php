<?php
session_start();
require './includes/classSecure.php';
require './includes/classMyTrackDB.php';
$DB = new MyTrackDB($devt_environment->getDatabaseParameters());


$r = $DB->allDevices();
while ($device = $r->fetch_assoc())
{
    $lastlocTS = null;
    $tripid = null;

    $r2 = $DB->allLocsForDeviceNoTrip($device["iddevice"]);
    while ($loc = $r2->fetch_assoc())
    {
        $dtLocTs = (new DateTime($loc["loc_timestamp"]))->getTimestamp();
        //Check that the time stamp is at least 15 minutes old
        if ( ((new DateTime())->getTimestamp() - $dtLocTs) > 15*60 )
        {
            //Do we have a trip
            if (!$tripid)
            {
                //Is there a trip for this device thats end time is
                $tripid = findTrip(($device["iddevice"],$sloc["loc_timestamp"]trTs,(10*60));
                if (!$tripid)
                    //Start a new trip
                    $tripid = $DB->newTrip($device["iddevice"],loc["loc_timestamp"]);
            }

            //Check that the time stamp is less than 10 minutes from last
            if ($lastlocTS != null && ($dtLocTs - $lastlocTS) > (10 * 60) )
            {
                if ($tripid)
                    $DB->updateTripLastTimestamp($tripid,$loc["loc_timestamp"]);
                $tripid = $DB->newTrip($device["iddevice"],loc["loc_timestamp"]);
            }

            $DB->addTripToLoc($tripid,$loc["idloc"]);


            $lastlocTS = $dtLocTs;
        }

    }

}
?>