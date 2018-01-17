<?php
/*
 * PASSBBOOK REST Api Version 1.0
 * Register a Device to Receive Push Notifications for a Pass
 * POST request to
 * webServiceURL/version/devices/deviceLibraryIdentifier/registrations/passTypeIdentifier/serialNumber
 */
require_once ("../../PHPutil/MyLogPHP/MyLogPHP.class.php");
require_once ("../../PHPUtil/config.php");

$connms = sqlsrv_connect($mssql_host, $connectioninfo);

function closeconn($connms)
{
    sqlsrv_close($connms);
}

require_once __DIR__ . '/Model/ModelPassbook.php';
require_once __DIR__ . '/Model/ModelGuest.php';
require_once __DIR__ . '/Model/ModelDevices.php';
require_once __DIR__ . '/Model/ModelRegisters.php';
require_once __DIR__ . '/Model/Defines.php';

$log = new MyLogPHP("../logs/Passbook.log.csv");
$passbooktable = new \Model\Passbook\ModelPassbook($connms, $log);
$guesttable = new \Model\Guest\ModelGuest($connms, $log);
$devicetable = new \Model\Devices\ModelDevices($connms, $log);
$registerstable = new \Model\Registers\ModelRegisters($connms, $log);

/*
 * guest/version/devices/deviceLibraryIdentifier/registrations/passTypeIdentifier/serialNumber
 * echo "<br/><deviceidentifier>".$_GET ['deviceLibraryIdentifier'] is not needed while we have guestcode
 */
(isset($_GET['guest'])) ? $guestcode = $_GET['guest'] : $log->error("Error Get parameter not found:>RESTAPI");
(isset($_GET['serialNumber'])) ? $serialnumber = $_GET['serialNumber'] : $log->error("Error Get parameter not found:>RESTAPI");
(isset($_GET['deviceLibraryIdentifier'])) ? $deviceLibraryIdentifier = $_GET['deviceLibraryIdentifier'] : $log->error("Error Get parameter not found:>RESTAPI");

// This is if the serialnumber is in the form AH1-28756 where 28756 is the guestcode
// This notation is mandatory if exists card from many costomers
$pieces = explode("-", $serialnumber);
$serialnumber = $pieces[0];

// test: $authenticationtoken = str_replace('ApplePass ', '', "ApplePass 0msXQ6GmM04dukSvtXFz");

$data = json_decode(file_get_contents("php://input"));
$pushtoken = $data->pushToken;
// test: $pushtoken= "55f281b7922a94ab87739b662084654b4e903f5d960831060f8e6cb64d953a7b";

if ($_GET['passtype'] == 'attido') {
    $pushservice = $data->pushServiceUrl;
    $authenticationtoken = str_replace('AttidoPass ', '', $_SERVER['HTTP_AUTHORIZATION']);
} else {
    $pushservice = IOS;
    $authenticationtoken = str_replace('ApplePass ', '', $_SERVER['HTTP_AUTHORIZATION']);
}

$hotelid = $guesttable->hotelcode($guestcode);
if ($hotelid == NULL) { // at this time cannot exists this case
    $log->error("Error no Hotel for Guest: " . $guestcode . " >RESTAPI");
    closeconn($connms);
    http_response_code(204);
    exit();
}

/**
 * ***********If Debug ******************
 */
if (TRUE) { // debug
    ob_start();
    
    $content = ob_get_contents();
    $x = file_get_contents('memo.txt');
    $fp = fopen("memo.txt", 'w');
    if ($fp) {
        fwrite($fp, $x . "*******************" . $content . "<br>");
        fclose($fp);
    }
    
    ob_clean();
}

// **********End debug ************

/* If the request is not authorized, returns HTTP status 401 */
if ($passbooktable->authvalid($authenticationtoken, $hotelid, $serialnumber) == FALSE) {
    $log->error("Unautorized: Bad Authenticationtoken: " . $authenticationtoken . " for guest" . $guestcode . ">RESTAPI");
    http_response_code(401);
    closeconn($connms);
    exit();
}

$registrationpair = $registerstable->isregistered($guestcode, $serialnumber, UPDATE_CARD);

// Server error while Card on Guest must be present ->returns the appropriate standard HTTP status
if ($registrationpair['registrationid'] == NULL) {
    
    $log->error("Systemerror:!!!! Request on Guest thats is not Registered in REGISTRATIONS for 
		               guestcode=" . $guestcode . " AND Serial" . $serialnumber . ">RESTAPI");
    closeconn($connms);
    http_response_code(503);
    exit();
}

// Entry is registered in the Registrationstable
$deviceid = $devicetable->deviceidforguest($guestcode, $deviceLibraryIdentifier);
if (($registrationpair['deviceid'] != NULL) && ($deviceid != NULL)) {
    // all ok the serial number is already registered for this device, returns HTTP status 200.
    
    http_response_code(200);
    closeconn($connms);
    exit();
} else { // If registration succeeds, returns HTTP status 201.
    
    if ($deviceid == NULL){ // if not exists
        if ($deviceid = $devicetable->insertdevice($guestcode, $pushtoken, $deviceLibraryIdentifier, $pushservice)); // got it
        else { // not entry
            $log->error("Update a device in the DEVICES table fails for guestcode=" . $guestcode . ">RESTAPI");
            closeconn($connms);
            http_response_code(503);
            exit();
        }
    }
    
    if ($registerstable->registerdevice($registrationpair['registrationid'], $deviceid) == NULL) {
        $log->error("Update a deviceid in Registrations table  fails for guestcode=" . $guestcode . ">RESTAPI");
        closeconn($connms);
        http_response_code(503);
        exit();
    }
    
    // registration succeeds, returns HTTP status 201
    http_response_code(201);
    closeconn($connms);
}

?>