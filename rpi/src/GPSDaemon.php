#!/usr/bin/env php
<?php
require dirname(__FILE__) . "/includes/classNMEA.php";
require dirname(__FILE__) . "/includes/classSyncList.php";
use devt\NMEA\NMEA;
use devt\SyncList\SyncList;

define("__DEBUG__",false);

//Globals
$globalParams= array();
$globalParams["box"] = array();
$globalParams["max_speed"] = 500.0; //If this speed (in km/hr) between two points is greater than this then ignore the point
$globalParams["min_distance"] = 0.050; //If the distance (in km) from the last point is less than this ignore the point
$globalParams["host"] = "track.devt.nz";
$globalParams["api"] = "myTrackApi.php";
$globalParams["uuid"] = trim(file_get_contents("/etc/machine-id"));

//Lastloc
$last_values=array();
$last_values["host_serial"] = -1;
$last_values["ts"] = 0;
$last_values["lat"] = 0;
$last_values["lon"] = 0;
$last_values["serial"] = 0;
$last_values["hello"] = false;
$last_values["maxts"] = 0;

$g_strdate = "";

$alllocs = array();
$allts = array();

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

function pointInBox($lat,$lon,$box)
{
    if ($lat < $box["minlat"] || $lat > $box["maxlat"] || $lon < $box["minlon"] || $lon > $box["maxlon"] )
        return false;
    return true;
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


function getLocalIP()
{
    $output= array();
    $rslt = 0;
    $r = exec ("ifconfig",$output,$rslt);
    if ($rslt == 0)
    {
        $start = false;
        foreach($output as $o)
        {
            $o = trim($o);
            if ($start)
            {
                if (substr($o,0,5) == "inet ")
                {
                    $a = explode(" ",$o);
                    return $a[1];
                }
            }
            else
                if (substr($o,0,6) == "wlan0:")
                    $start = true;

        }
    }
    return "";
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

function sendHello()
{
    global $globalParams;

    echo "Send hello to host\n";


    $params["device"] = $globalParams["uuid"];
    $params["ipaddress"] = getLocalIP();

    $url = "https://{$globalParams["host"]}/{$globalParams["api"]}?r=hello";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($params));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

    $result = curl_exec($ch);
    if ($result)
    {
        $result = json_decode($result,true);
        if (isset($result["meta"]) && isset($result["meta"] ["status"]) && $result["meta"] ["status"])
        {
            echo " host sent good rstl\n";
            return true;
        }
    }
    return false;
}

function getHostLastSerial()
{
    global $globalParams;

    echo "Get host last serial\n";

    $url = "https://{$globalParams["host"]}/{$globalParams["api"]}?r=lastserial/{$globalParams["uuid"]}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
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

function sendBunch($synclist)
{
    global $globalParams;
    global $last_values;

    $params=array();
    $entries = array();

    $cnt = 0;

    //Have we sent a hello yet?
    if (! $last_values["hello"] )
    {
        $last_values["hello"] = sendHello();
        if ($last_values["hello"])
        {
            $v = getHostLastSerial();
            if ($v)
            {
                $last_values["host_serial"] = $v;
            }
        }
    }

    if ($last_values["hello"])
    {
        $list = $synclist->getFullList();
        foreach($list as $seq => $a)
        {
            $entries[$seq] = $a;
            $cnt++;
            if ($cnt > 50)
                break;
        }

        $params["device"] = $globalParams["uuid"];
        $params["entries"] = $entries;

        $url = "https://{$globalParams["host"]}/{$globalParams["api"]}?r=bunch";
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
            if (isset($data["completed"]) )
                return $data["completed"];
        }
    }
    return false;
}

function checkRequired($v,$synclist)
{
    global $globalParams;
    global $last_values;

    if ( ! pointInBox($v["a"],$v["b"],$globalParams["box"]) )
        return false;
    if ($synclist->inList("t",$v["t"]))
        return false;
    if ( $v["ts"] < ($last_values["maxts"] - 300) )
        return false;

    $dist = DistKM($last_values["lat"],$last_values["lon"],$v["a"],$v["b"]);
    $speed = ($dist / ($v["t"] - $last_values["ts"])) * 3600.0;

    if ($speed > $globalParams["max_speed"])
        return false;
    if ($v["t"] > $last_values["ts"] + (15*60) || $dist > $globalParams["min_distance"] )
    {
        $last_values["ts"] = $v["t"];
        $last_values["maxts"] = max($v["t"],$last_values["maxts"] ) ;
        $last_values["lat"] = $v["a"];
        $last_values["lon"] = $v["b"];
        return true;
    }

}

function post($v,$ignoreSend=false,$ignoreTrace=false,$sentence=null)
{
    global $allts;
    global $alllocs;
    global $ftrace;

    global $globalParams;
    global $last_values;

    $ret = false;

    if ( ! pointInBox($v["a"],$v["b"],$globalParams["box"]) )
        return false;
    if (array_search($v["t"],$allts) === false)
    {

        //Now check that the distance is more than 50 metres and time from last is greater than 15 minutes
        if ($ignoreTrace || $v["ts"] > ($last_values["maxts"] - 300))
        {
            $dist = DistKM($last_values["lat"],$last_values["lon"],$v["a"],$v["b"]);
            $speed = ($dist / ($v["t"] - $last_values["ts"])) * 3600.0;
            if ($speed < $globalParams["max_speed"])
            {

                if ($v["t"] > $last_values["ts"] + (15*60) || $dist > $globalParams["min_distance"] || $ignoreTrace)
                {
                    $allts[] = $v["t"];
                    $alllocs[$v["s"]] = $v;
                    if (! $ignoreTrace)
                    {
                        fwrite($ftrace,"{$v["s"]},{$v["t"]},{$v["a"]},{$v["b"]},{$v["c"]},{$v["h"]}\n");
                        $last_values["ts"] = $v["t"];
                        $last_values["maxts"] = max($v["t"],$last_values["maxts"] ) ;
                        $last_values["lat"] = $v["a"];
                        $last_values["lon"] = $v["b"];
                    }
                    $ret = true;
                }
            }

            if ($speed > 500 && $sentence)
                error_log("Invalid GPS data - speed too high {$speed}km/hr, decoded lat/lon {$v["a"]}/{$v["b"]} last lat/lon {$last_values["lat"]}/{$last_values["lon"]} offending sentence is {$sentence}");
        }

        if (!$ignoreSend)
        {
            if (count($alllocs) > 0)
            {
                $rslt = sendBunch();

                if ($rslt !== false)
                {
                    $last_values["host_serial"] = $rslt;
                    deleteUpTo($rslt);
                }
            }
        }
    }

    return $ret;

}

//function recoverFromfile($last_serial)
//{
//    global $strTraceFile;


//    $seq = -1;
//    echo "recoverFromFile - Last database serial is {$last_serial}\n";

//    $ftrace = fopen($strTraceFile,"r");
//    $ftracenew = fopen($strTraceFile . ".new","w");
//    fwrite($ftracenew,"SEQ,TIMESTAMP,LATTITUDE,LONGITUDE,HEIGHT,HDOP\n");

//    if ($ftrace)
//    {
//        $line = fgets($ftrace);
//        while (! feof($ftrace))
//        {
//            $line = fgets($ftrace);
//            if (strlen($line) > 5)
//            {
//                $seq = intval(strtok($line,","));
//                if ($seq > $last_serial)
//                {
//                    $v = array();
//                    $v["s"] = $seq;
//                    $v["t"] = intval(strtok(","));
//                    $v["a"] = floatval(strtok(","));
//                    $v["b"] = floatval(strtok(","));
//                    $v["c"] = floatval(strtok(","));
//                    $v["h"] = floatval(strtok(","));
//                    if ($v["t"] > 0)
//                    {
//                        fwrite($ftracenew,$line);
//                        post($v,true,true,null);
//                    }
//                }
//            }
//        }
//        fclose($ftrace);
//        fclose($ftracenew);
//        unlink($strTraceFile);
//        rename($strTraceFile . ".new",$strTraceFile);

//        echo "read recovery file and last seq in file is {$seq}\n";
//    }

//    return $seq;
//}

/*****************************************
 * Start
 * Parse the options at start
 *****************************************
*/


$config = parse_ini_file("/etc/GPS/GPS.conf");

if ($config["hostname"])
    $globalParams["host"] = $config["hostname"];
if ($config["api"])
    $globalParams["api"] = $config["api"];
if ($config["maxspeed"])
    $globalParams["max_speed"] = floatval($config["maxspeed"]);
if ($config["mindist"])
    $globalParams["min_distance"]=floatval($config["mindist"]) / 1000.0;
if ($config["boxlatmin"])
    $globalParams["box"]['minlat']=floatval($config["boxlatmin"]);
if ($config["boxlatmax"])
    $globalParams["box"]['maxlat']=floatval($config["boxlatmax"]);
if ($config["boxlonmin"])
    $globalParams["box"]['minlon']=floatval($config["boxlonmin"]);
if ($config["boxlonmax"])
    $globalParams["box"]['maxlon']=floatval($config["boxlonmax"]);

echo "Start - configuration details:\n";
echo " host - {$globalParams["host"]}\n";
echo " api - {$globalParams["api"]}\n";
echo " maxspeed - {$globalParams["max_speed"]}\n";
echo " mindist - {$globalParams["min_distance"]}\n";
echo " box - {$globalParams["box"]['minlat']},{$globalParams["box"]['minlon']} - {$globalParams["box"]['maxlat']},{$globalParams["box"]['maxlon']}\n";

sleep(45);  //Wait for networks and clock to come up.

$last_values["hello"] = sendHello();

if ($last_values["hello"])
{
    $v = getHostLastSerial();
    if ($v)
        $last_values["host_serial"] = $v;
}

$strTraceFile = "/var/GPS/TrackData.txt";
$synclist = new SyncList($strTraceFile);


$ftrace = null;

/*
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

        $last_values["serial"] = intval(strtok($lastline,","));

        if ($last_values["serial"] > 0)
            $last_values["serial"]++;
    }
    fclose($f1);

    echo "Recovering from file\n";
    $last_values["serial"] = recoverFromfile($last_values["host_serial"]);
    $last_values["serial"]++;
    echo "Recovered from file next serial is {$last_values["serial"]}\n";
}
*/

echo "Starting GPS logger with serial {$last_values["serial"]}\n";
$loop_counter = 0;


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
                    if (checkRequired($v,$synclist))
                    {
                        $synclist->insert($v);
                        if ($synclist->count() > 0)
                        {
                            $rsltList = sendBunch($synclist);
                            foreach($rsltList as $seq)
                            {
                                $synclist->remove($seq);
                            }
                        }
                    }
                }
            }
        }

        //Check that we have a bunch to send every 200 GPS reads
        if ($loop_counter % 400 == 0)
        {
            echo "Loop counter % 400 reached\n";
            $loop_counter = 0;
            $num_locs = $synclist->count();
            if ($num_locs > 0)
            {
                echo " sending to host bunch of locs count in queue is {$num_locs}\n";
                $rsltList = sendBunch($synclist);
                foreach($rsltList as $seq)
                {
                    $synclist->remove($seq);
                }
            }
        }
        $loop_counter++;
    }
    debug("End of file");
}
else
    debug("Could not open file");
?>