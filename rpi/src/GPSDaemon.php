#!/usr/bin/env php
<?php
/**
 * GPS Daemon
 * LED STATUS
 *      Slow flash GREEN - GPS Fx and communciation with host
 *      Slow falsh YELLOW - GPS Fix and no communciation with host
 *      Slow flash BLUE - No GPS fix and communciating with host
 *      Slow flash MAGENTA - No GPS fix and not communciating with host
 *      Fast flash red - Startup no commuinication with host and no GPS data
 *      Fast flash magenta - Communication with host and no GPS data
*/


require dirname(__FILE__) . "/includes/classNMEA.php";
require dirname(__FILE__) . "/includes/classSyncList.php";
require dirname(__FILE__) . "/includes/classOptions.php";
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
$last_values['status'] = new option();

define("STATUS_FIX", 1);
define("STATUS_SERVER", 2);
define("STATUS_FIX_AND_SERVER", 3);

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

function led_slow_blink($colour)
{
    Led("blink",$colour,0.25,0,0.005);
}

function led_fast_blink($colour)
{
    Led("blink",$colour,2.0,0,0.005);
}

function changeFix($on)
{
    global $last_values;
    if (! $last_values["status"]->same(STATUS_FIX,$on) )
    {
        //We have a change in fix status
        if ($on)
            $colour = ($last_values["status"]->isset(STATUS_SERVER)) ? "green" : "yellow";
        else
            $colour = ($last_values["status"]->isset(STATUS_SERVER)) ? "blue" : "magenta";
        led_slow_blink($colour);
        sendFixStatus($on);
    }
    $last_values["status"]->setstate(STATUS_FIX,$on);
}

function changeServerStatus($on)
{
    global $last_values;

    if (! $last_values["status"]->same(STATUS_SERVER,$on) )
    {
        //We have a change in server status
        if ($on)
            $colour = ($last_values["status"]->isset(STATUS_FIX)) ? "green" : "blue";
        else
            $colour = ($last_values["status"]->isset(STATUS_FIX)) ? "yellow" : "magenta";
        led_slow_blink($colour);
    }
    $last_values["status"]->setstate(STATUS_SERVER,$on);
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

function sendFixStatus($have_fix)
{
    global $globalParams;


    $params["device"] = $globalParams["uuid"];
    $params["fixstatus"] = $have_fix;

    $url = "https://{$globalParams["host"]}/{$globalParams["api"]}?r=fixstatus";
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
            return true;
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
            led_fast_blink("magenta");
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

function startServer()
{
    global $last_values;
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

function sendBunch($synclist)
{
    global $globalParams;
    global $last_values;

    $params=array();
    $entries = array();

    $cnt = 0;

    //Have we sent a hello yet?
    if (! $last_values["hello"] )
        startServer();

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

    //Check that this is a newer time stamp
    if ($v["t"] < $last_values["maxts"])
        return false;
    if ( ! pointInBox($v["a"],$v["b"],$globalParams["box"]) )
        return false;
    if ($synclist->inList("t",$v["t"]))
        return false;

    $speed = 0.0;
    $dist = DistKM($last_values["lat"],$last_values["lon"],$v["a"],$v["b"]);
    $divisor = $v["t"] - $last_values["ts"];
    if ($divisor != 0.0)
        $speed = ($dist / $divisor) * 3600.0;

    if ($speed > $globalParams["max_speed"])
        return false;

    if ($v["t"] > $last_values["ts"] + (15*60) || $dist > $globalParams["min_distance"] )
    {
        $last_values["ts"] = $v["t"];
        $last_values["maxts"] = $v["t"];
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
led_fast_blink("red");
$globalParams = parseOptions("/etc/GPS/GPS.conf");
echo "Start - configuration details:\n";
echo " uuid - {$globalParams["uuid"]}; host - {$globalParams["host"]}; api - {$globalParams["api"]}\n";
echo " maxspeed - {$globalParams["max_speed"]}; mindist - {$globalParams["min_distance"]}\n";
echo " box  [{$globalParams["box"]['minlat']},{$globalParams["box"]['minlon']}] - [{$globalParams["box"]['maxlat']},{$globalParams["box"]['maxlon']}]\n";


startServer();
$start_serial = $last_values["host_serial"] + 1;

$strttyfile = "/dev/ttyS0";
$strTraceFile = "/var/GPS/TrackData.txt";
$synclist = new SyncList($strTraceFile,$start_serial,["seq","type","t","a","b","c","h"]);

echo "Starting GPS logger with serial {$start_serial}\n";
$loop_counter = 0;
$f = fopen($strttyfile,"w+");

if ($f)
{
    while (true)
    {
        while (!feof($f))
        {
            $data = fgets($f);
            foreach (NMEA::parseSentances($data) as $s)
            {
                $v = NMEA::decodeSentence($s,$g_strdate);
                if ($v)
                {
                    if (isset($v["nofix"]))
                        changeFix(false);
                    if (isset($v["type"]) && $v["type"] == "GNGGA")
                    {
                        changeFix(true);
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
                                    changeServerStatus(true);
                                    foreach($rsltList as $seq)
                                    {
                                        $synclist->remove($seq);
                                    }
                                }
                                else
                                    changeServerStatus(false);
                            }
                        }
                    }
                }
            }

            //Check that we have a bunch to send every 200 GPS reads
            if ($loop_counter % 1000 == 0)
            {
                //Have we sent a hello yet?
                if (! $last_values["hello"] )
                    startServer();

                $loop_counter = 0;
                $num_locs = $synclist->count();
                if ($num_locs > 0)
                {
                    $rsltList = sendBunch($synclist);
                    if ($rsltList !== false && count($rsltList) > 0)
                    {
                        changeServerStatus(true);
                        foreach($rsltList as $seq)
                        {
                            $synclist->remove($seq);
                        }
                    }
                    else
                        changeServerStatus(false);
                }
            }
            $loop_counter++;
            usleep(1000);
        }

        echo "End of file received from {$strttyfile}\n";
        @fclose($f);
        $f = fopen($strttyfile,"w+");
    }
}
else
    echo "Could not open file {$strttyfile}\n";
?>