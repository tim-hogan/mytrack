<?php
$_SECUITY_LEVELS = true;
define('SECURITY_DEFAULT','1');             //0000 0000 0001   Dec 1  = (2^x) -1
define('SECURITY_VIEW','2');                //0000 0000 0011   Dec 3
define('SECURITY_CUSTOMER','4');            //0000 0000 0111   Dec 7
define('SECURITY_ADMIN','128');             //0000 1111 1111   Dec 255
define('SECURITY_GOD','256');               //0001 1111 1111   Dec 511
define('SECURITY_MAX','4095');              //1111 1111 1111   Dec 4095

define('MAX_USERNAME_ATTEMPS','10');
?>