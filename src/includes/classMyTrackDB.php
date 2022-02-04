<?php
//devt.Version = 1.0
require_once dirname(__FILE__) . '/classSQLPlus2.php';

class device extends TableRow
{
    function __construct($tabledata=null)
    {
        if ($tabledata)
            parent::__construct($tabledata);
        else
            parent::__construct
            (
                [
                    "iddevice" =>["type" => "int"],
                    "device_uuid" =>["type" => "varchar"],
                    "device_serial" =>["type" => "int"],
                    "device_ip_address"=>["type" => "varchar"],
                    "device_name" =>["type" => "varchar"]
                ]
            );
    }
}

class loc extends TableRow
{
    function __construct($tabledata=null)
    {
        if ($tabledata)
            parent::__construct($tabledata);
        else
            parent::__construct
            (
                [
                    "idloc" =>["type" => "int"],
                    "loc_device" =>["type" => "int"],
                    "loc_timestamp" =>["type" => "datetime"],
                    "loc_serial" =>["type" => "int"],
                    "loc_lat" =>["type" => "double"],
                    "loc_lon" =>["type" => "double"],
                    "loc_height" =>["type" => "double"],
                    "loc_hdop[" =>["type" => "double"]
                ]
            );
    }
}

class MyTrackDB extends SQLPlus
{
    function __construct($params)
    {
        parent::__construct($params);
    }

    //*********************************************************************
    // device
    //*********************************************************************
    public function getDevice($id)
    {
        return $this->o_singlequery("device","SELECT * from device where iddevice = ?","i",$id);
    }

    public function getDeviceByUUID($uuid)
    {
        return $this->o_singlequery("device","SELECT * from device where device_uuid = ?","s",$uuid);
    }

    public function lastDeviceSerial()
    {
        $device = $this->o_singlequery("device","SELECT device_serial from device order by device_serial desc limit 1",null,null);
        if (!$device)
            return false;
        return $device->device_serial;
    }

    public function createDevice($uuid,$name)
    {
        $serial = $this->lastDeviceSerial();
        if ($serial === false)
            $serial = 51001;
        else
            $serial++;
        if ($this->p_create("insert into device (device_uuid,device_serial,device_name) values (?,?,?)","sis",$uuid,$serial,$name) )
            return $this->getDeviceByUUID($uuid);
        return false;
    }

    public function updateDeviceIP($id,$ipaddress)
    {
        $strDT = (new DateTime())->format("Y-m-d H:i:s");
        return $this->p_update("update device set device_ip_address = ?, device_last_hello = ? where iddevice = ?","ssi",$ipaddress,$strDT,$id);
    }

    //*********************************************************************
    // loc
    //*********************************************************************
    public function getLoc($id)
    {
        return $this->o_singlequery("loc","SELECT * from loc where idloc = ?","i",$id);
    }

    public function getLastLocSerial($uuid)
    {
        $device = $this->getDeviceByUUID($uuid);
        if ($device)
        {
            $a = $this->o_singlequery("loc","select loc_serial from loc where loc_device = ? order by loc_serial desc limit 1","i",$device->iddevice);
            if ($a)
                return $a->loc_serial;
        }
        return false;
    }

    public function createLoc($uuid,$a)
    {
        $device = $this->getDeviceByUUID($uuid);
        $dt = (new DateTime())->setTimestamp($a["t"]);
        $strT = $dt->format("Y-m-d H:i:s");
        if ($device)
        {
            return $this->p_create("insert into loc (loc_device,loc_timestamp,loc_serial,loc_lat,loc_lon,loc_height,loc_hdop) values (?,?,?,?,?,?,?)","isidddd",$device->iddevice,$strT,$a["s"],$a["a"],$a["b"],$a["c"],$a["h"]);
        }
        return false;
    }

    //*********************************************************************
    // static functions
    //*********************************************************************
    public static function createRandomPW($length = 6)
    {
        $p = '';
        $characters = '23456789abcdefghjkmnprstuwxyzABCDEFGHJKLMNPQRSTUWXYZ';
        for($i = 0 ; $i < $length; $i++)
        {
            $p .= substr($characters, rand(0,51) , 1);
        }
        return strval($p);
    }

}

?>