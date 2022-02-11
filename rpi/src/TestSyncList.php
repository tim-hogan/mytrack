<?php

define("STATUS_FIX",1);
define("STATUS_SERVER",2);

$last_values = array();
$last_values["status"] = 0;

function changeFix($on)
{
    global $last_values;

    if (boolval($last_values["status"] & STATUS_FIX) != $on)
    {
        echo "Value different than that set: Value: {$on} Lastvalue: {$last_values["status"]}\n";
        //We have a change in fix status
    }
    if ($on)
        $last_values["status"] = $last_values["status"] | STATUS_FIX;
    else
        $last_values["status"] = $last_values["status"] &  ~ STATUS_FIX;
}


echo "Set\n";
changeFix(true);
echo "Set\n";
changeFix(true);
echo "Set\n";
changeFix(true);
echo "Reset\n";
changeFix(false);
echo "Reset\n";
changeFix(false);
echo "Reset\n";
changeFix(false);
echo "Set\n";
changeFix(true);
echo "Reset\n";
changeFix(false);
echo "Set\n";
changeFix(true);

?>