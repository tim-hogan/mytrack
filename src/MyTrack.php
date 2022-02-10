<?php
session_start();
require './includes/classSecure.php';
require './includes/classMyTrackDB.php';
$DB = new MyTrackDB($devt_environment->getDatabaseParameters());

?>
<!DOCTYPE HTML>
<html>
<head>
    <meta name="viewport" content="width=device-width" />
    <meta name="viewport" content="initial-scale=1.0" />
    <title>MYTRACK</title>
    <style>
        body {font-family: Arial, Helvetica, sans-serif;font-size: 10pt;margin: 0;padding: 0;}
        #container {margin: auto;max-width: 1200px;background-color: #eee; border: solid 1px #888; border-radius: 8px;}
        #heading h1 {margin-left: 10px;color: #7b86ff}
        #devices {margin: 10px; padding: 10px;background-color: #f8f8f8;border: solid 1px #888; border-radius: 8px;}
        #devices th {padding-right: 16px;color: #888;text-align: left;}
        #devices td {padding-right: 16px;}
        #devices h2 {color: #666;}
        #functions {margin: 10px; padding: 10px;background-color: #f8f8f8;border: solid 1px #888; border-radius: 8px;}
        #functions h2 {color: #666;}
        #functions li.link {cursor: pointer; color: blue;}
    </style>
    <script>
        function action(n) {
            let w = n.getAttribute("where");
            let l = document.getElementsByClassName('sel');
            for (let i = 0; i < l.length; i++) {
                if (l[i].checked)
                    window.location = w + "?v=" + l[i].value;
            }
        }
        function download() {
            let l = document.getElementsByClassName('sel');
            for (let i = 0; i < l.length; i++) {
                if (l[i].checked)
                    window.location = "Download.php?v=" + l[i].value;
            }
        }
    </script>
</head>
<body>
    <div id="container">
        <div id="heading">
            <h1>MY TRACK</h1>
        </div>
        <div id="devices">
            <h2>DEVICES</h2>
            <table>
                <tr><th>SELECT</th><th>NAME</th><th>UUID</th><th>SERIAL</th><th>LOCAL IP</th><th>LAST BOOT</th></tr>
                <?php
                $devices = $DB->every("device");
                foreach($devices as $device)
                {
                    $strLastHello = "";
                    if ($device["device_last_hello"])
                    {
                        $dt = new DateTime($device["device_last_hello"]);
                        $dt->setTimezone(new DateTimeZone('Pacific/Auckland'));
                        $strLastHello = $dt->format("H:i D jS M Y");
                    }
                    echo "<tr><td><input class='sel' type='checkbox' value='{$device['iddevice']}'></td><td>{$device['device_name']}</td><td>{$device['device_uuid']}</td><td>{$device['device_serial']}</td><td>{$device['device_ip_address']}</td><td>{$strLastHello}</td></tr>";
                }
                ?>
            </table>
        </div>
        <div id="functions">
            <h2>FUNCTIONS</h2>
            <ul>
                <li class="link" where="Trips.php" onclick="action(this)">TRIPS</li>
                <li class="link" onclick="download()">DOWLOAD CSV</li>
            </ul>
        </div>
    </div>
</body>
</html>