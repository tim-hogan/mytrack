#!/usr/bin/env php
<?php
require dirname(__FILE__) . "/includes/classNMEA.php";
use devt\NMEA\NMEA;

define("__DEBUG__",false);

//Globals
$serial = 0;
$g_strdate = "";
$g_last_serial = -1;
$g_last_ts = 0;
$g_last_lat = 0;
$g_last_lon = 0;

$alllocs = array();
$allts = array();

$g_host = "track.devt.nz";
$g_api = "myTrackApi.php";
$g_max_speed = 500.0;   //If this speed (in km/hr) between two points is greater than this then ignore the point
$g_min_distance=0.050;   //If the distance (in km) from the last point is less than this ignore the point
$g_uuid = file_get_contents("/etc/machine-id");
$g_uuid = trim($g_uuid);


function debug($t)
{
    if (__DEBUG__)
    {
        echo $t . "\n";
    }
}

function debug_var_dump($a,$t="")
{
    if (__DEBUG__)
    {
        echo $t . "\n";
        var_dump($a);
    }
}

function DistKM($lat,$long,$dlat,$dlong)
{
    $toRadians = (3.14159265358979 / 180.0);
    $latFrom = $lat * $toRadians;
    $longFrom = $long * $toRadians;
    $latTo = $dlat * $toRadians;
    $longTo = $dlong * $toRadians;
    $theta = 0;
    $theta = sin($latFrom) * sin($latTo) + (cos($latFrom) * cos($latTo) * cos($longFrom-$longTo));
    return (6378.15 * acos($theta));
}

/**
 * External functions
 */

function getResultData($result)
{
    if ($result)
    {
        $result = json_decode($result,true);
        if (isset($result["meta"]) && isset($result["meta"] ["status"]) && $result["meta"] ["status"])
        {
            if (isset($result["data"]))
                return $result["data"];
        }
    }
    return false;
}

function getHostLastSerial()
{
    global $g_uuid;
    global $g_host;
    global $g_api;

    $url = "https://{$g_host}/{$g_api}?r=lastserial/{$g_uuid}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

    $result = curl_exec($ch);
    $data = getResultData($result);
    if ($data !== false)
    {
        if (isset($result["data"] ["lastserial"]) )
            return intval($result["data"] ["lastserial"]);
    }
    return false;
}

/**
 * deleteUpTo
 * Deletes entries that have been received by the server
 * Also deletes list of timestamps that are older than 600 seconds
*/
function deleteUpTo($last)
{
    global $alllocs;
    global $allts;

    $ts = (new DateTime())->getTimestamp() - 600;
    foreach ($alllocs as $serial => $loc)
    {
        if ($serial <= $last)
            unset($alllocs[$serial]);
    }

    foreach($allts as $t)
    {
        if ($t < $ts)
            unset($allts[$t]);
    }

}

function sendBunch()
{
    global $g_uuid;
    global $g_host;
    global $g_api;
    global $alllocs;

    $params=array();
    $entries = array();

    $cnt = 0;
    foreach($alllocs as $idx => $loc)
    {
        $entries[$idx] = $loc;
        $cnt++;
        if ($cnt > 50)
            break;
    }

    $params["device"] = $g_uuid;
    $params["entries"] = $entries;

    $url = "https://{$g_host}/{$g_api}?r=bunch";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($params));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

    $result = curl_exec($ch);
    $data = getResultData($result);
    if ($data !== false)
    {
        if (isset($data["lastserial"]) )
            return intval($data["lastserial"]);
    }
    return false;
}

function post($v,$ignoreSend=false,$ignoreTrace=false,$sentence=null)
{
    global $allts;
    global $alllocs;
    global $ftrace;
    global $g_last_ts;
    global $g_last_lat;
    global $g_last_lon;
    global $g_max_speed;
    global $g_min_distance;

    $ret = false;

    if (array_search($v["t"],$allts) === false)
    {

        //Now check that the distance is more than 50 metres and time from last is greater than 15 minutes

        $dist = DistKM($g_last_lat,$g_last_lon,$v["a"],$v["b"]);
        $speed = ($dist / ($v["t"] - $g_last_ts)) * 3600.0;
        if ($speed < $g_max_speed)
        {

            if ($v["t"] > $g_last_ts + (15*60) || $dist > $g_min_distance || $ignoreTrace)
            {
                $allts[] = $v["t"];
                $alllocs[$v["s"]] = $v;
                if (! $ignoreTrace)
                {
                    fwrite($ftrace,"{$v["s"]},{$v["t"]},{$v["a"]},{$v["b"]},{$v["c"]},{$v["h"]}\n");
                    $g_last_ts = $v["t"];
                    $g_last_lat = $v["a"];
                    $g_last_lon = $v["b"];
                }
                $ret = true;
            }
        }

        if ($speed > 500 && $sentence)
            error_log("Invalid GPS data - speed too high {$speed}km/hr, offending sentence is {$sentence}");
        if (!$ignoreSend)
        {
            if (count($alllocs) > 0)
            {
                $rslt = sendBunch();

                if ($rslt !== false)
                {
                    $g_last_serial = $rslt;
                    deleteUpTo($g_last_serial);
                }
            }
        }
    }

    return $ret;

}

function recoverFromfile($last_serial)
{
    global $strTraceFile;


    $seq = -1;

    $ftrace = fopen($strTraceFile,"r");
    $ftracenew = fopen($strTraceFile . ".new","w");
    fwrite($ftracenew,"SEQ,TIMESTAMP,LATTITUDE,LONGITUDE,HEIGHT,HDOP\n");

    if ($ftrace)
    {
        $line = fgets($ftrace);
        while (! feof($ftrace))
        {
            $line = fgets($ftrace);
            if (strlen($line) > 5)
            {
                $seq = intval(strtok($line,","));
                if ($seq > $last_serial)
                {
                    $v = array();
                    $v["s"] = $seq;
                    $v["t"] = intval(strtok(","));
                    $v["a"] = floatval(strtok(","));
                    $v["b"] = floatval(strtok(","));
                    $v["c"] = floatval(strtok(","));
                    $v["h"] = floatval(strtok(","));
                    if ($v["t"] > 0)
                    {
                        fwrite($ftracenew,$line);
                        post($v,true,true,null);
                    }
                }
            }
        }
        fclose($ftrace);
        fclose($ftracenew);
        unlink($strTraceFile);
        rename($strTraceFile . ".new",$strTraceFile);

        echo "read recovery file and last seq in file is {$seq}\n";
    }

    return $seq;
}


/*****************************************
 * Start
 * Parse the options at start
 *****************************************
*/


$config = parse_ini_file("/etc/GPS/GPS.conf");

if ($config["hostname"])
    $g_host = $config["hostname"];
if ($config["api"])
    $g_api = $config["api"];
if ($config["maxspeed"])
    $g_max_speed = floatval($config["maxspeed"]);
if ($config["mindist"])
    $g_min_distance=floatval($config["mindist"]) / 1000.0;

echo "Start - configuration details:\n";
echo " host - {$g_host}\n";
echo " api - {$g_api}\n";

$v = getHostLastSerial();
if ($v)
{
    $g_last_serial = $v;
    $g_last_serial++;
}


$strTraceFile = "/var/GPS/TrackData.txt";

$ftrace = null;

//Find last line of a file
if (! file_exists($strTraceFile))
{
    debug("No existing data found - starting with serial of 0");
    $ftrace = fopen($strTraceFile,"a");
    fwrite($ftrace,"SEQ,TIMESTAMP,LATTITUDE,LONGITUDE,HEIGHT,HDOP\n");
    fclose($ftrace);
}
else
{
    $lastline="";
    $sz = filesize($strTraceFile);
    $f1 = fopen($strTraceFile,"r");
    debug("Existing file found of size {$sz}");
    if ($sz > 200)
        fseek($f1,-100,SEEK_END);
    while (! feof($f1))
    {
        $line = fgets($f1);
        if (strlen($line > 5))
            $lastline = $line;
    }
    if (strlen($lastline) > 2)
    {
        debug("Lastline {$lastline}");
        $tok = strtok($lastline,",");
        debug("Tok {$tok}");

        $serial = intval(strtok($lastline,","));

        if ($serial > 0)
            $serial++;
    }
    fclose($f1);

    echo "Recovering from file\n";
    $serial = recoverFromfile($g_last_serial);
    $serial++;
    echo "Recovered from file next serial is {$serial}\n";
}


echo "Starting GPS logger with serial {$serial}\n";

$ftrace = fopen($strTraceFile,"a");

$f = fopen("/dev/ttyS0","w+");

if ($f)
{
    while (!feof($f))
    {
        $data = fgets($f);
        foreach (NMEA::parseSentances($data) as $s)
        {
            $v = NMEA::decodeSentence($s,$g_strdate);
            if ($v)
            {
                if ($v["type"] == "GNGGA")
                {
                    unset($v["type"]);
                    $v["s"] = $serial;
                    if (post($v,false,false,$s) )
                        $serial++;
                }
            }
        }
    }
    debug("End of file");
}
else
    debug("Could not open file");
?>