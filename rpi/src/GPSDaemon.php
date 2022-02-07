#!/usr/bin/env php
<?php
require dirname(__FILE__) . "/includes/classNMEA.php";
require dirname(__FILE__) . "/includes/classSyncList.php";
use devt\NMEA\NMEA;
use devt\SyncList\SyncList;

define("__DEBUG__",false);

//This is a deamon so set no time limit
set_time_limit(0);

//Globals
$globalParams= null;

//Lastloc
$last_values=array();
$last_values["host_serial"] = -1;
$last_values["ts"] = 0;
$last_values["lat"] = 0;
$last_values["lon"] = 0;
$last_values["serial"] = 0;
$last_values["hello"] = false;
$last_values["maxts"] = 0;
$last_values['allts'] = array();  //List of the last time stamps deleted if older than last ts and 600 seconds

$g_strdate = "";
$ftrace = null;


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

function traceTime($v)
{
    global $ftrace;

    if (! $ftrace)
        $ftrace = fopen("/var/GPS/TraceGGA.txt","a");
    if ($ftrace)
        fwrite($ftrace,strval($v["t"]) . "," . strval($v["a"]) . "," . strval($v["b"]) . "\n");
}

function Led($action,$colour="red",$rate=2,$duration=5000,$ratio=0.5)
{
    $command = array();
    $command["action"] = $action;
    $command["colour"] = $colour;
    $command["rate"] = $rate;
    $command["duration"] = $duration;
    $command["ratio"] = $ratio;

    $str_json = json_encode($command);

    try
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket)
        {
            if (@socket_connect($socket, "127.0.0.1", 2207) )
            {
                @socket_write($socket, $str_json, strlen($str_json));
                socket_close($socket);
            }
        }
    }
    catch (Exception $e)
    {

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
            Led("blink","magenta",0.5,0,0.01);
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
            $a["s"] = $seq;
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
    if ( $v["t"] < ($last_values["maxts"] - 300) )
        return false;

    $speed = 0.0;
    $dist = DistKM($last_values["lat"],$last_values["lon"],$v["a"],$v["b"]);
    $divisor = $v["t"] - $last_values["ts"];
    if ($divisor != 0.0)
        $speed = ($dist / $divisor) * 3600.0;

    if ($speed > $globalParams["max_speed"])
        return false;

    $allts = $last_values['allts'];


    if ($v["t"] > $last_values["ts"] + (15*60) || $dist > $globalParams["min_distance"] )
    {
        $last_values["ts"] = $v["t"];
        $last_values["maxts"] = max($v["t"],$last_values["maxts"] ) ;
        $last_values["lat"] = $v["a"];
        $last_values["lon"] = $v["b"];
        $last_values['allts'] [] = $v["t"];
        return true;
    }

    return false;
}

function parseOptions($filename)
{
    $params["uuid"] = trim(file_get_contents("/etc/machine-id"));

    $config = parse_ini_file($filename);

    if ($config["hostname"])
        $params["host"] = $config["hostname"];
    if ($config["api"])
        $params["api"] = $config["api"];
    if ($config["maxspeed"])
        $params["max_speed"] = floatval($config["maxspeed"]);
    if ($config["mindist"])
        $params["min_distance"]=floatval($config["mindist"]) / 1000.0;
    if ($config["boxlatmin"])
        $params["box"]['minlat']=floatval($config["boxlatmin"]);
    if ($config["boxlatmax"])
        $params["box"]['maxlat']=floatval($config["boxlatmax"]);
    if ($config["boxlonmin"])
        $params["box"]['minlon']=floatval($config["boxlonmin"]);
    if ($config["boxlonmax"])
        $params["box"]['maxlon']=floatval($config["boxlonmax"]);
    return $params;
}


/*****************************************
 * Start
 * Parse the options at start
 *****************************************
*/
Led("blink","red",0.5,0,0.01);

sleep(30);  //Wait for networks and clock to come up.

$globalParams = parseOptions("/etc/GPS/GPS.conf");
echo "Start - configuration details:\n";
echo " uuid - {$globalParams["uuid"]}; host - {$globalParams["host"]}; api - {$globalParams["api"]}\n";
echo " maxspeed - {$globalParams["max_speed"]}; mindist - {$globalParams["min_distance"]}\n";
echo " box  [{$globalParams["box"]['minlat']},{$globalParams["box"]['minlon']}] - [{$globalParams["box"]['maxlat']},{$globalParams["box"]['maxlon']}]\n";


$last_values["hello"] = sendHello();

if ($last_values["hello"])
{
    $v = getHostLastSerial();
    if ($v)
        $last_values["host_serial"] = $v;
}
$start_serial = $last_values["host_serial"] + 1;

$strttyfile = "/dev/ttyS0";
$strTraceFile = "/var/GPS/TrackData.txt";
$synclist = new SyncList($strTraceFile,$start_serial);

echo "Starting GPS logger with serial {$start_serial}\n";
$loop_counter = 0;
$f = fopen($strttyfile,"w+");

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
                    traceTime($v);
                    if (checkRequired($v,$synclist))
                    {
                        $synclist->insert($v);
                        if ($synclist->count() > 0)
                        {
                            $rsltList = sendBunch($synclist);
                            if ($rsltList !== false && count($rsltList) > 0)
                            {
                                Led("blink","green",0.5,0,0.01);
                                foreach($rsltList as $seq)
                                {
                                    $synclist->remove($seq);
                                }
                            }
                            else
                                Led("blink","blue",0.5,0,0.01);
                        }
                    }
                }
            }
        }

        //Check that we have a bunch to send every 200 GPS reads
        if ($loop_counter % 1000 == 0)
        {
            $loop_counter = 0;
            $num_locs = $synclist->count();
            if ($num_locs > 0)
            {
                $rsltList = sendBunch($synclist);
                foreach($rsltList as $seq)
                {
                    $synclist->remove($seq);
                }
            }
        }
        $loop_counter++;
    }
    echo "End of file received from {$strttyfile}\n";
}
else
    echo "Could not open file {$strttyfile}\n";
?>