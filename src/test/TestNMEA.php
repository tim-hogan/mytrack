<?php
use devt\NMEA\NMEA;
require dirname(__FILE__) . "/includes/classNMEA.php";

$strdate = null;

//Checksum test
echo "Test 1 "; echo NMEA::validChecksum("\$PMTK251,38400*27\r\n") ? "Pass" : "Fail"; echo  "\n";
echo "Test 2 "; echo NMEA::validChecksum("\$PMTK251,38400*28\r\n") ? "Fail" : "Pass"; echo  "\n";
echo "Test 3 "; echo NMEA::validChecksum("PMTK251,38400*28\r\n") ? "Fail" : "Pass"; echo  "\n";
echo "Test 4 "; echo NMEA::validChecksum("\$PMTK251,38400*2\r\n") ? "Fail" : "Pass"; echo  "\n";
echo "Test 5 "; echo NMEA::validChecksum("\$PMTK251,38400\r\n") ? "Fail" : "Pass"; echo  "\n";
echo "Test 6 "; echo NMEA::validChecksum("\$PMTK251,38400*256\r\n") ? "Fail" : "Pass"; echo  "\n";


$a = NMEA::parseSentances("\$GPRMC,ghghg,hjsgdhjsg*55\$GPRMC,jkgh,jhg,*44");
echo "Test 7 "; echo (count($a) == 2) ? "Pass" : "Fail"; echo "\n";
echo "Test 8 "; echo ($a[0] == "\$GPRMC,ghghg,hjsgdhjsg*55") ? "Pass" : "Fail"; echo "\n";
echo "Test 9 "; echo ($a[1] == "\$GPRMC,jkgh,jhg,*44") ? "Pass" : "Fail"; echo "\n";

$a = NMEA::parseSentances("jahsdgjashgdjahsgdajshg\$GPRMC,ghghg,hjsgdhjsg*55\$GPRMC,jkgh,jhg,*44");
echo "Test 10 "; echo (count($a) == 2) ? "Pass" : "Fail"; echo "\n";
echo "Test 11 "; echo ($a[0] == "\$GPRMC,ghghg,hjsgdhjsg*55") ? "Pass" : "Fail"; echo "\n";
echo "Test 12 "; echo ($a[1] == "\$GPRMC,jkgh,jhg,*44") ? "Pass" : "Fail"; echo "\n";


$str = '$GNRMC,223509.000,A,4117.1356,S,17446.4269,E,0.42,182.17,300122,,,A*60$GNGGA,223510.000,4117.1358,S,17446.4268,E,1,08,1.20,141.1,M,19.0,M,,*6A' . "\r\n";
$a = NMEA::parseSentances($str);

echo "Count of returned sentences = " . count($a) . "\n";

echo "Test 13 "; echo  (count($a) == 2) ? "Pass" : "Fail" ; echo "\n";
foreach($a as $s)
{
    $decode = NMEA::decodeSentence($s,$strdate);
    echo "Test 14 "; echo  ($decode) ? "Pass" : "Fail" ; echo "\n";
    if ($decode)
        var_dump($decode);
}

$str = '$GNGGA,001933.000,4117.1214,S,176.4487,E,1,08,1.08,61.2,M,19.0,M,,*51';
$a = NMEA::parseSentances($str);
$strdate = "2022-02-03";
$decode = NMEA::decodeSentence($a[0],$strdate);
var_dump($decode);


?>