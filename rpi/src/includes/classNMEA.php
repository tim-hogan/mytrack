<?php
namespace devt\NMEA;
use parallel\Events\Event\Type;

class NMEA
{
    public const VALUE_LOW = 0;
    public const VALUE_HIGH = 1;

    static public function createChecksum($d)
    {
        $cs = 0;
        $c = unpack("C*",$d);
        foreach($c as $b)
            $cs = $cs ^ $b;
        return $cs;
    }

    static public function validChecksum($sentence)
    {
        /*
         * Expects a complete sentence starting with a $ and teminating in *xx
         * Otherwise will error
        */
        $s = trim(trim(trim($sentence,"\n"),"\r"));
        if (substr($s,0,1) != "$")
            return false;
        if (substr($s,-3,1) != "*")
            return false;
        if ( !ctype_xdigit(substr($s,-2,2)) )
            return false;
        $test = unpack("C*",hex2bin(substr($s,-2,2))) [1];
        $cs = self::createChecksum(substr($s,1,strlen($s)-4));
        return ($cs == $test);
    }

    /**
     * Summary of parseSentances
     * @param mixed $s
     * @return array of sentences
     */
    static public function parseSentances($s)
    {
        $ret = array();
        $s = trim(trim(trim($s,"\n"),"\r"));
        $f = strpos($s,"$");
        if ($f === false)
            return [];
        if ($f != 0)
            $s = substr($s,$f);
        $a = explode("$",$s);


        foreach($a as $b)
        {

            if (strlen($b) > 4)
            {
                //Trim $b
                $b = trim($b);
                if (substr($b,-3,1) == "*")
                    $ret[] = "$" . $b;
            }
        }
        return $ret;
    }

    static public function decodeTime($d)
    {
        if (strlen($d) < 6)
            return false;
        $milli = "000";
        $strTime = substr($d,0,2) . ":". substr($d,2,2) . ":" . substr($d,4,2);
        $a = explode(".",$d);
        if (count($a) > 1)
            $milli = intval(floatval("0." . trim($a[1])) * 1000);
        return [trim($strTime),$milli];
    }

    static public function decodeDate($d)
    {
        if (strlen($d) != 6)
            return false;
        return trim(strval(intval(substr($d,4,2)) + 2000) . "/" . substr($d,2,2) . "/" . substr($d,0,2));
    }

    static public function decodedms($a)
    {
        try
        {
            $b = explode(".",$a);
            if (count($b) !=2 )
                return false;
            $l = strlen($b[0]);
            $deg = intval(substr($b[0],0,$l-2));
            $min = intval(substr($b[0],$l-2,2));
            $l = strlen($b[1]);
            $dec = floatval($b[1]) / pow(10,$l);
            $min = floatval($min) + $dec;
            $deg = $deg + ($min / 60.0);
            return $deg;
        }
        catch (Exception $e)
        {
            return false;
        }
    }

    static public function decodeLat($a,$dir)
    {
        $deg = self::decodedms($a);
        if ($deg !== false)
        {
            if ($dir == "S")
                $deg = -$deg;
        }
        return $deg;
    }

    static public function decodeLon($a,$dir)
    {
        $deg = self::decodedms($a);
        if ($deg !== false)
        {
            if ($dir == "W")
                $deg = -$deg;
        }
        return $deg;
    }

    static public function isDateTimeWithin($strdate,$strtime,$seconds)
    {
        $dtNow = new \DateTime();
        try
        {
            $dtTS = new \DateTime($strdate . " " . $strtime);
        }
        catch (Exception $e)
        {
            return false;
        }
        
        if (abs( $dtNow->getTimestamp() - $dtTS->getTimestamp() ) < $seconds)
                return $dtTS->getTimestamp();
        
        return false;
    }

    static public function decodeSentence($sentence,&$strdate)
    {
        if (substr($sentence,0,1) != "$")
            return false;
        if (! self::validChecksum($sentence))
            return false;

        $fields = explode(",",$sentence);
        switch ($fields[0])
        {
            case '$GNRMC':
                if ($fields[2] != "A")
                    return false;  //No Fix
                $strdate = self::decodeDate($fields[9]);
                $ts = self::decodeTime($fields[1]);
                $strtime = $ts[0];
                $timemilli = $ts[1];
                $dtSerial = self::isDateTimeWithin($strdate,$strtime,300);
                if (!$dtSerial)
                    return false;
                $lat = self::decodeLat($fields[3],$fields[4]);
                $lon = self::decodeLon($fields[5],$fields[6]);
                if ($lat === false || $lon === false)
                    return false;
                return ["type" => "GNRMC", "t" => $dtSerial,"a" => $lat,"b" => $lon];
                break;
            case '$GNGGA':
                //We need a valid date as GNGGA does not include date.
                if (strlen($strdate) < 8)
                    return false;
                if ($fields[6] == "0")
                    return false;

                $ts = self::decodeTime($fields[1]);
                $strtime = $ts[0];
                $timemilli = $ts[1];

                $dtSerial = self::isDateTimeWithin($strdate,$strtime,300);
                if (!$dtSerial)
                    return false;
                $lat = self::decodeLat($fields[2],$fields[3]);
                $lon = self::decodeLon($fields[4],$fields[5]);
                if ($lat === false || $lon === false)
                    return false;
                $hdop = floatval($fields[8]);
                $height = floatval($fields[9]);
                return ["type" => "GNGGA", "t" => $dtSerial,"a" => $lat,"b" => $lon,"c" => $height,"h" => $hdop];
                break;
            default:
                return false;
        }
    }
}
?>