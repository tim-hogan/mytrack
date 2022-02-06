<?php
require dirname(__FILE__) . "/includes/classSyncList.php";
use devt\SyncList\SyncList;

$synclist = new SyncList("audit.txt");

$a1 = ["t" => 156786,"a" => -41.344, "b" => 174.867, "c" => 12.4, "h" => 0.86];
$a2 = ["t" => 156787,"a" => -41.343, "b" => 174.868, "c" => 12.5, "h" => 0.85];
$a3 = ["t" => 156788,"a" => -41.342, "b" => 174.869, "c" => 12.6, "h" => 0.84];
$a4 = ["t" => 156789,"a" => -41.341, "b" => 174.850, "c" => 12.7, "h" => 0.83];

//$synclist->insert($a1);
//$synclist->insert($a2);
//$synclist->insert($a3);
//$synclist->insert($a4);

if ($synclist->inList("t",15678237) )
    echo "Is in List\n";

echo "Count in synclist {$synclist->count()}\n";

?>