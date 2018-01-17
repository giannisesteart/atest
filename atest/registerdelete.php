<?php
/**
 * PASSBBOOK REST Api Version 1.0
 * Unregistering a Device
 * DELETE request to
 * webServiceURL/version/devices/deviceLibraryIdentifier/registrations/passTypeIdentifier/serialNumber
 */

require_once ("../../PHPutil/MyLogPHP/MyLogPHP.class.php");
require_once ("../../PHPUtil/config.php");

$connms = sqlsrv_connect($mssql_host, $connectioninfo);

function closeconn($connms)
{
    sqlsrv_close($connms);
}

require_once __DIR__ . '/Model/ModelGuest.php';
require_once __DIR__ . '/Model/ModelRegisters.php';
require_once __DIR__ . '/Model/ModelDevices.php';





$log = new MyLogPHP("../logs/Passbook.log.csv");

$guesttable = new \Model\Guest\ModelGuest($connms, $log);
$registerstable = new \Model\Registers\ModelRegisters($connms, $log);
$devicetable = new \Model\Devices\ModelDevices($connms, $log);



/*
 * guest/version/devices/deviceLibraryIdentifier/registrations/passTypeIdentifier/serialNumber
 * <deviceidentifier>".$_GET ['deviceLibraryIdentifier'] is not needed while we have guestcode
 */

(isset($_GET['guest'])) ? $guestcode = $_GET['guest'] : $log->error("Error Get parameter not found:>RESTAPI");
(isset($_GET['serialNumber'])) ? $serialnumber = $_GET['serialNumber'] : $log->error("Error Get parameter not found:>RESTAPI");
(isset($_GET['deviceLibraryIdentifier'])) ? $deviceLibraryIdentifier = $_GET['deviceLibraryIdentifier'] : $log->error("Error Get parameter not found:>RESTAPI");


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



/**
 * ***********IF Debug ******************
 */
if (FALSE) { // debug
    
    echo 'registerdelete>>>';
    echo "Auth key:>> " . $authenticationtoken;
    echo "<br/>Customer code >" . $guestcode;
    echo "<br/>Serialnumber>" . $serialnumber;
    echo "<br/>Serialnumber>" . $hotelid;
}
// **********End debug ************





/* If the request is not authorized, returns HTTP status 401 */
if ($passbooktable->authvalid($authenticationtoken, $hotelid, $serialnumber) == FALSE) {
    $log->error("Unautorized: Bad Authenticationtoken: " . $authenticationtoken . " for guest" . $guestcode . ">RESTAPI");
    http_response_code(401);
    closeconn($connms);
    exit();
}

$deviceid = $devicetable->deviceidforguest($guestcode, $deviceLibraryIdentifier);
if ($deviceid == NULL) {
    closeconn($connms);
    http_response_code(503);
    exit();
}

/*
 * If disassociation succeeds, returns HTTP status 200.
 * Otherwise, returns the appropriate standard HTTP status.
 */
if ($registerstable->delete_registration($guestcode, $serialnumber, $deviceid))
    http_response_code(200);
else
    http_response_code(401);

closeconn($connms);

?>