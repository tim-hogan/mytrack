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
                    "device_name" =>["type" => "varchar"],
                    "device_last_fix_status" => ["type" => "boolean"],
                    "device_last_fix_status_timestmap" => ["type" => "datetime"]
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
                    "loc_hdop" =>["type" => "double"],
                    "loc_trip" =>["type" => "int"]
                ]
            );
    }
}

class trip extends TableRow
{
    function __construct($tabledata=null)
    {
        if ($tabledata)
            parent::__construct($tabledata);
        else
            parent::__construct
            (
                [
                    "idtrip" =>["type" => "int"],
                    "trip_start" =>["type" => "datetime"],
                    "trip_end" =>["type" => "datetime"],
                    "trip_device" =>["type" => "int"],
                    "trip_name" =>["type" => "varchar"]
                ]
            );
    }
}

class audit extends TableRow
{
    function __construct($tabledata=null)
    {
        if ($tabledata)
            parent::__construct($tabledata);
        else
            parent::__construct
            (
                [
                    "idaudit" =>["type" => "int"],
                    "audit_device" =>["type" => "int"],
                    "audit_type" =>["type" => "varchar"],
                    "audit_timestamp" =>["type" => "datetime"],
                    "audit_description" =>["type" => "varchar"]
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

    public function allDevices()
    {
        return $this->p_all("select * from device",null,null);
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


    public function updateDeviceFixStatus($id,$status)
    {
        $strDT = (new DateTime())->format("Y-m-d H:i:s");
        if ($status)
            return $this->p_update("update device set device_last_fix_status = 1, device_last_fix_status_timestmap = ? where iddevice = ?","si",$strDT,$id);
        else
            return $this->p_update("update device set device_last_fix_status = 0, device_last_fix_status_timestmap = ? where iddevice = ?","si",$strDT,$id);
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

    public function haveLocFor($iddevice,$strTime)
    {
        $a = $this->o_singlequery("loc","select * from loc where loc_device = ? and loc_timestamp = ?","is",$iddevice,$strTime);
        if ($a)
            return true;
        return false;
    }

    public function createLoc($uuid,$a)
    {
        $device = $this->getDeviceByUUID($uuid);
        $dt = (new DateTime())->setTimestamp($a["t"]);
        $strT = $dt->format("Y-m-d H:i:s");
        if ($device)
        {
            //Do we already have a lov
            if (! $this->haveLocFor($device->iddevice,$strT) )
            {
                $rslt = $this->p_create("insert into loc (loc_device,loc_timestamp,loc_serial,loc_lat,loc_lon,loc_height,loc_hdop) values (?,?,?,?,?,?,?)","isidddd",$device->iddevice,$strT,$a["s"],$a["a"],$a["b"],$a["c"],$a["h"]);
                if ($rslt || $this->lasterrno == 1062)
                    return true;
            }
            else
                return true;
        }
        return false;
    }

    public function allLocsFor($id)
    {
        return $this->p_all("select * from loc where loc_device = ? order by loc_timestamp","i",$id);
    }


    public function allLocsForDeviceNoTrip($deviceid)
    {
        return $this->p_all("select * from loc where loc_device = ? and loc_trip is null order by loc_timestamp","i",$deviceid);
    }

    public function addTripToLoc($tripid,$locid)
    {
        return $this->p_update("update loc set loc_trip = ? where idloc = ?","ii",$tripid,$locid);
    }

    //*********************************************************************
    // trip functions
    //*********************************************************************
    public function newTrip($did,$strTS)
    {
        $rslt = $this->p_create("insert into trip (trip_device,trip_start) values (?,?)","is",$did,$strTS);
        if ($rslt)
            return $this->insert_id;
        return null;
    }

    public function findTrip($did,$strTs,$seconds)
    {
        $endTs = new DateTime($strTs);
        $endTs->setTimestamp($endTs->getTimestamp()+$seconds);
        $strEndTs = $endTs->format("T-m-d H:i:s");

        $trip =  $this->p_singlequery("select * from trip where trip_device = ? and trip_start < ? and trip_end < ?","iss",$did,$strTs,$strEndTs);
        if ($trip)
            return $trip["idtrip"];
        return null;
    }

    public function updateTripLastTimestamp($tripid,$strTS)
    {
        return $this->p_update("update trip set trip_end = ? where idtrip = ?","si",$strTS,$tripid);
    }

    public function allTripsForDevice($deviceid)
    {
        return $this->p_all("select * from trip where trip_device = ?","i",$deviceid);
    }

    //*********************************************************************
    // audit functions
    //*********************************************************************
    public function createAudit($type,$description,$deviceid=null)
    {
        if ($deviceid)
            return $this->p_create("insert into audit (audit_type,audit_description,audit_device) value (?,?,?)","ssi",$type,$description,$deviceid);
        else
            return $this->p_create("insert into audit (audit_type,audit_description) value (?,?)","ss",$type,$description);
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