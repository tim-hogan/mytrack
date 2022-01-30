#!/usr/bin/env php
<?php
function createChecksum($d)
{
    $cs = 0;
    $c = unpack("C*",$d);
    foreach($c as $b)
        $cs = $cs ^ $b;
    return $cs;
}

function usage()
{
    echo "GPSSetup\n";
    echo "  Usage: GPSSetup [-hm] [-f <ttyfile name> ] [-b <baudrate>] [-u <update rate>]\n";
    echo "  -h Help\n";
    echo "\n";
    echo "  -f <ttyfile name> Optional - sets the tty file name, default is /dev/serial0\n";
    echo "\n";
    echo "  -b <baud rate> Optional - default 9600\n";
    echo "    -b 1 - 4800\n";
    echo "    -b 2 - 9600\n";
    echo "    -b 3 - 14400\n";
    echo "    -b 4 - 19200\n";
    echo "    -b 5 - 38400\n";
    echo "    -b 6 - 57600\n";
    echo "    -b 7 - 115200\n";
    echo "\n";
    echo "  -u <update rate> Sets update rate\n";
    echo "    -u 1000 - Once very 1000 milliseconds (1 Sec)\n";
    echo "    -u 200 - Once very 200 milliseconds (5 times a second)\n";
    echo "    -u 100 - Once very 100 milliseconds (10 times a second)\n";
    echo "\n";
    echo "  -m Set minimal sentences of GMC and GGA only\n";
}

$baud = "9600";
$rate = "1000";
$ttyfile = "/dev/ttyS0";


$options = getopt("b:f:hmu:");

if (isset($options["h"]))
{
    usage();
    exit(0);
}

if (isset($options["b"]))
{
    $baudval = intval($options["b"]);
    switch ($baudval)
    {
        case 1:
            $baud = "4800";
            break;
        case 2:
            $baud = "9600";
            break;
        case 3:
            $baud = "14400";
            break;
        case 4:
            $baud = "19200";
            break;
        case 5:
            $baud = "38400";
            break;
        case 6:
            $baud = "57600";
            break;
        case 7:
            $baud = "115200";
            break;
        default:
            echo "ERROR Invalid baud rate value\n";
            usage();
            exit(1);
    }
}

if (isset($options["u"]))
{
    $rate = intval($options["u"]);
    if ($rate != 1000 && $rate != 200 && $rate != 100)
    {
        echo "ERROR: Invalid rate specified for option -u\n";
        exit(1);
    }
    $rate = strval($rate);
}

if (isset($options["f"]))
    $ttyfile = intval($options["f"]);


$f = fopen($ttyfile,"w+");
if ($f)
{
    echo "Open of file {$ttyfile}\n";
    if (isset($options["b"]))
    {
        $command = "PMTK251,{$baud}";
        $cs = createChecksum($command);

        $command = "$" . $command . "*" . bin2hex(pack("C*",$cs)) . "\r\n";
        echo "Setting gps module baud rate on file {$ttyfile} with command: {$command}\n";
        $command .= "\r\n";

        $l = fwrite($f,$command);
        if ($l && $l != strlen($command))
            echo "ERORR: writing to file\n";
        echo fgets($f);
    }

    if (isset($options["u"]))
    {
        $command = "PMTK220,{$rate}";
        $cs = createChecksum($command);

        $command = "$" . $command . "*" . bin2hex(pack("C*",$cs)) . "\r\n";
        echo "Setting gps module NMEA rate on file {$ttyfile} with command: {$command}\n";
        $command .= "\r\n";

        $l = fwrite($f,$command);
        if ($l && $l != strlen($command))
            echo "ERORR: writing to file\n";
        echo fgets($f);

    }

    if (isset($options["m"]))
    {
        $command = "PMTK314,0,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0";
        $cs = createChecksum($command);

        $command = "$" . $command . "*" . bin2hex(pack("C*",$cs)) . "\r\n";
        echo "Setting gps module NMEA to mi {$ttyfile} with command: {$command}\n";
        $command .= "\r\n";

        $l = fwrite($f,$command);
        if ($l && $l != strlen($command))
            echo "ERORR: writing to file\n";
        echo fgets($f);
    }

    fclose($f);
}

?>