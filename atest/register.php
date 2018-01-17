 <?php
/*
 * PASSBBOOK REST Api Version 1.0
 * Case of access Restapi
 * Call from Apple
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require ('registerpost.php');
} else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    require ('registerdelete.php');
} else {
    require ("../../PHPutil/MyLogPHP/MyLogPHP.class.php");
    $log = new MyLogPHP("../logs/Passbook.log.csv");
    $log->error("Undefined Server Request Method:" . $_SERVER['REQUEST_METHOD']);
    http_response_code(503);
}
   
    
?>