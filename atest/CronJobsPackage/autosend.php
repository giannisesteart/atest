<?php

/* ABOUTHOTELIER : SEND EMAILS Version 1.0
 * This file is part of the Jobs package.
 * (c) Giannis
 * 
 * Auto email is a autobach - cronjob handling sends forall the selected
 * Guests
 * The specification is whole documented at webhotelier.com
 * Parameter: action (confirmemail, etc...)
 * Use Curl in autos for Modularity
 * 12/2/2016
 */


define("NO_HOTEL", -1);
// No Hotel: the hotel doesn't exist


$MYROOT = __DIR__ . "/..";
// $MYROOT = "C:/inetpub/vhosts/creationadv.gr/httpdocs/ihotelweb/vadmin";



require_once ("DEF_MESSAGES.php");
require_once ($MYROOT . "/PHPUtil/phputil.php");
require_once ($MYROOT . "/PHPUtil/config.php");

// Error hadler
require_once ($MYROOT . "/PHPUtil/MyLogPHP/MyLogPHP.class.php");
$log = new MyLogPHP("Error.log.csv");
function is_die($stmt, $message)
{
    global $log;
    if ($stmt === false) {
        echo ($message);
        $log->error($message);
        die(print_r(sqlsrv_errors(), true));
    }
}





// Fetch the CONSTANTS FOR THE SEND

if (! isset($_GET['action'])) {
    echo "Error in action: no action parameter for email action  ";
    $log->error("Error in action: give action parameter for email action .   ");
    die();
} else
    $action = $_GET['action'];

// $log->info ("Execute autoSend for > ".$action);

/* fetch the actual data */
require_once ($action . ".php");
$URL = $_SERVER['SERVER_NAME'] . dirname($_SERVER['REQUEST_URI']) . URLENDPOINT;

$connms = sqlsrv_connect($mssql_host, $connectioninfo);
is_die($connms, "failed connection");
$sql = "SELECT TOP 20 * FROM GUESTS WHERE " . SELECT_GUEST . " ORDER BY hotelcode ASC ;";
$stmt = sqlsrv_query($connms, $sql);
is_die($stmt, "Error in query preparation/execution");




$hotelcode = NO_HOTEL; // no Hotel
while ($rec = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
    $guestcode = $rec['code'];
    
    // set new hotelcode
    if ($hotelcode != $rec['hotelCode']) { // if $hotelcode switches
                                             // fetch the $hoteldata
        $hotelcode = $rec['hotelCode'];
        $sqlhotel = "SELECT * FROM HOTELS WHERE  code=" . $hotelcode . " ;";
        $stmthotel = sqlsrv_query($connms, $sqlhotel);
        is_die($stmthotel, "Error in query preparation/execution");
        $rechotel = sqlsrv_fetch_array($stmthotel, SQLSRV_FETCH_ASSOC);
        if (! $rechotel)
            is_die(false, "Send email: Hotel " . $hotelcode . "not found");
    }
    
    // prepare data for email
    // object date to string for post
    if (! isset($rec['checkInDate']) || ! isset($rec['checkOutDate']) || ($rec['checkInDate'] == "") || ($rec['checkOutDate'] == "")) {
        $checkin = "";
        $checkout = "";
    } else {
        $checkin = date_format($rec['checkInDate'], "d-m-Y");
        $checkout = date_format($rec['checkOutDate'], "d-m-Y");
    }
    // send email with curl
    $post = [
        'hotelcode' => "" . $hotelcode,
        'guestcode' => "" . $rec['code'],
        'title' => $rechotel['title'],
        'htlemail' => $rechotel['email'],
        'adress' => $rechotel['address'],
        'postBookingStatus' => $rechotel['postBookingStatus'],
        'postBookingBody' => $rechotel['postBookingBody'],
        'postBookingTitle' => $rechotel['postBookingTitle'],
        'icon' => $rechotel['icon'],
        'password' => $rec['password'],
        'checkin' => $checkin,
        'checkout' => $checkout,
        'lname' => $rec['guestSurname'],
        'fname' => $rec['guestName'],
        'email' => $rec['email'],
        'status' => $rec['status'],
        'price' => $rec['reservationPrice'],
        'referer' => $rec['referer']
    ];
    
    $ch = curl_init($URL);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    
    // session_write_close(); //dead lock prevent if session. Here is not session reqiered
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        is_die(false, 'Errno' . curl_errno($ch) . '<br/>Error' . curl_error($ch) . '<br/>' . ": Curl error for Hotelcode:" . $hotelcode . " Guestcode:" . $guestcode);
    } else {
        if (IS_TO_UPDATE) {
            if (($httpCode == 200) || (diffFromNow(date_format($rec['lastActive'], 'Y-m-d H:i:s')) > MAX_WAIT_TIME)) {
                // message is sended and update status 200 OK or Try than MAX_WAIT_TIME
                $sqlguest = "UPDATE GUESTS SET " . UPDATE_GUEST . " WHERE code=" . $guestcode . ";";
                $stmtguest = sqlsrv_query($connms, $sqlguest);
                is_die($stmtguest, "Error in query preparation/execution for UPDATE guests in " . $guestcode);
            }
        }
        if ($httpCode != 200) {
            echo "Http code not 200 OK for " . $guestcode;
            $log->error("Bad entry in action " . $action . " Cannot sent message: Guestcode  " . $guestcode);
        }
        
        // $log->error ( "Bad entry in ". $action." Cannot sent message: Guestcode " . $guestcode);
    }
    
    // echo "response>>".$response ;
    curl_close($ch);

} // end while

echo "OK";
$log->info("OK");
sqlsrv_close($connms);
http_response_code(200);

?>

 