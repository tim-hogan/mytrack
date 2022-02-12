<?php
session_start();
require './includes/classSecure.php';
require './includes/classMyTrackDB.php';
require './includes/classTime.php';
$DB = new MyTrackDB($devt_environment->getDatabaseParameters());

$deviceid = 0;
$tripid = 0;
if (isset($_GET["v"]))
    $deviceid = intval($_GET["v"]);
if (isset($_GET["t"]))
    $tripid = intval($_GET["t"]);

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
        #trips {margin: 10px; padding: 10px;background-color: #f8f8f8;border: solid 1px #888; border-radius: 8px;}
        #trips th {text-align: left;}
        #trips td.r {text-align: right;}
        #mapouter {margin: 10px; padding: 10px;background-color: #f8f8f8;border: solid 1px #888; border-radius: 8px;}
        #map {height: 600px; width: 600px;}
    </style>
    <script type="text/javascript"
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo $devt_environment->getkey("MAPKEY");?>">
    </script>
    <script>
            <?php
    echo "var paths = [";
    $r = $DB->allLocsForTrip($tripid);
    while ($t = $r->fetch_array())
    {
        if ($d1 && !$bend)
            echo ",";
        $d = new DateTime($t['loc_timestamp']);
        $d->setTimezone(new DateTimeZone('Pacific/Auckland'));
        if ($lastd)
        {
            if ( ($d->getTimestamp() - $lastd->getTimestamp() ) > 1200)
                $bend = true;
        }
        if (!$bend)
        {
            echo "{lat:" . $t['loc_lat'] . ", lng: " . $t['loc_lon'] . ", t: \"" .$d->format('H:i:s d/m/Y'). "\", a:".$t['loc_height']."}";
            $d1 = true;
        }
        $lastd = $d;
    }
    echo "];";
    ?>
    function buildmap()
    {
        if (paths.length > 0)
        {
            map = new google.maps.Map(document.getElementById('map'), {
                center: {lat: -41.285161, lng: 174.774244},
                zoom: 10
            });

            path = new google.maps.Polyline({
                path: paths,
                geodesic: true,
                strokeColor: "#FF0000",
                strokeOpacity: 1.0,
                strokeWeight: 3
            });

            path.setMap(map);
        }
    }
    </script>

</head>
<body onload='buildmap()'>
    <div id="container">
        <div id="heading">
            <h1>MY TRACK</h1>
        </div>
        <div id="trips">
            <h2>TRIPS</h2>
            <table>
                <tr><th>START</th><th>END</th><th>DURATION</th></tr>
                <?php
            $r = $DB->allTripsForDevice($deviceid);
            while ($trip = $r->fetch_assoc())
            {
                $strStart = classTimeHelpers::timeFormat24Hr($trip["trip_start"],"Pacific/Auckland");
                $strEnd = classTimeHelpers::timeFormat24Hr($trip["trip_end"],"Pacific/Auckland");
                $duration = "";
                if ($trip["trip_end"])
                    $duration = classTimeHelpers::age(new DateTime($trip["trip_end"]),new DateTime($trip["trip_start"]));
                echo "<tr><td><a href='Trips.php?v={$deviceid}&t={$trip["idtrip"]}'>{$strStart}</a></td><td>{$strEnd}</td><td class='r'>$duration</td></tr>";
            }
                ?>
            </table>
        </div>
        <div id="mapouter">
            <h2>MAP</h2>
            <div id="map">

            </div>
        </div>
    </div>
</body>
</html>