<?php
/*
 * PASSBBOOK REST Api Version 1.0
 * Getting the Latest Version of a Pass
 * GET request to webServiceURL/version/passes/passTypeIdentifier/serialNumber
 */
require_once ("../../PHPutil/MyLogPHP/MyLogPHP.class.php");
require_once ("../../PHPUtil/config.php");

function closeconn($connms)
{
    sqlsrv_close($connms);
}


$connms = sqlsrv_connect($mssql_host, $connectioninfo);



require_once __DIR__ . '/Model/ModelPassbook.php';
require_once __DIR__ . '/Model/ModelGuest.php';
require_once __DIR__ . '/Model/ModelRegisters.php';

$log = new MyLogPHP("../logs/Passbook.log.csv");
$passbooktable = new \Model\Passbook\ModelPassbook($connms, $log);
$guesttable = new \Model\Guest\ModelGuest($connms, $log);
$registerstable = new \Model\Registers\ModelRegisters($connms, $log);

(isset($_GET['guest'])) ? $guestcode = $_GET['guest'] : $log->error("Error Get parameter not found:>RESTAPI");

(isset($_GET['serialNumber'])) ? $serialnumber = $_GET['serialNumber'] : $log->error("Error Get parameter not found:>RESTAPI");

// This is if the serialnumber is in the form AH1-28756 where 28756 is the guestcode
// This notation is mandatory if exists card from many customers
$pieces = explode("-", $serialnumber);
$serialnumber = $pieces[0];

$authenticationtoken = str_replace('ApplePass ', '', $_SERVER['HTTP_AUTHORIZATION']);

$hotelid = $guesttable->hotelcode($guestcode);
if ($hotelid == NULL) { // at this time cannot exists this case
    $log->error("Error no Hotel for Guest: " . $guestcode . " >RESTAPI");
    closeconn($connms);
    http_response_code(204);
    exit();
}

// ************ if TRUE Debug ******************
if (FALSE) {
    ob_start();
    
    $content = ob_get_contents();
    $x = file_get_contents('latest.txt');
    
    $fp = fopen("latest.txt", 'w');
    if ($fp) {
        fwrite($fp, $x . "********" . $content);
        fclose($fp);
    }
    ob_clean();
}
// End debug



/*
 * Support standard HTTP caching on this endpoint: Check for the If-Modified-Since header,
 * and return HTTP status code 304 if the pass has not changed.
 */

/* If the request is not authorized, returns HTTP status 401 */
if ($passbooktable->authvalid($authenticationtoken, $hotelid, $serialnumber) == FALSE) {
    $log->error("Unautorized: Bad Authenticationtoken: " . $authenticationtoken . " for guest" . $guestcode . ">RESTAPI");
    http_response_code(401);
    closeconn($connms);
    exit();
}

// If not modified return 304 HTTP=do nothing -
// This is a case for call all serialnumbers withou since-modified set in getserialnumbers

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    $ts = @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    $dt = new DateTime("@$ts");
    $updatedsince = $dt->format('Y-m-d H:i:s');
    if (! $registerstable->modified_serial($guestcode, $serialnumber, $updatedsince)) {
        closeconn($connms);
        http_response_code(304);
        exit();
    }
}

// generate the new pass
require_once ('gennewversion.php');

// /If disassociation succeeds, returns HTTP status 200.

http_response_code(200);
exit();

?>