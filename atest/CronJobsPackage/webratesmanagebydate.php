<?php
/* ABOUTHOTELIER : SEND WEBRATES Version 1.0
 * This file is part of the Jobs package.
 * (c) Giannis
 * 
 *  The Modul calculates the actual rates of a Hotel per Room!
 *  Find all discounts!!
 *  The specification is whole documented at webhotelier.com
 * 
 * 14/4/2016
 */


 
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');



require_once ("../PHPUtil/phputil.php");
require_once ("../PHPUtil/config.php");

//Utilities functions as extension
function validateDate($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function finddiff($from, $to)
{
    $datefrom = new DateTime($from);
    $dateto = new DateTime($to);
    
    return (diffdays($dateto, $datefrom));
}




// Get parameter
if (! isset($_GET['hotelcode'])) {
    echo "Error in action: give hotelcode parameter.   ";
    http_response_code(503);
    die();
}
if (! isset($_GET['from'])) {
    echo "Bad parameter date";
    http_response_code(503);
    die();
}
if (! isset($_GET['to'])) {
    echo "Bad parameter date 2";
    http_response_code(503);
    die();
}

if (! isset($_GET['adults'])) {
    echo "Bad parameter adults";
    http_response_code(503);
    die();
}

if (! isset($_GET['children'])) {
    $children = 0;
} else
    $children = intval($_GET['children']);

if (isset($_GET['closeout'])) {
    $closeout = TRUE;
} else
    $closeout = FALSE;

if (isset($_GET['availability'])) {
    $avail = TRUE;
} else
    $avail = FALSE;

if (isset($_GET['minstay'])) {
    $minstay = TRUE;
} else
    $minstay = FALSE;

$HOTELCODE = $_GET['hotelcode'];





$connms = sqlsrv_connect($mssql_host, $connectioninfo); // returns false
if ($connms === false) {
    echo "failed connection ";
    http_response_code(503);
    die(print_r(sqlsrv_errors(), true));
}
$sql = "SELECT * FROM HOTELS WHERE code=" . $HOTELCODE . ";";




// ************************************************
// Ready to fill the JSON 
// ********************************************************

$stmt = sqlsrv_query($connms, $sql);

if ($stmt === false) {
    echo "Error in query preparation/execution.\n";
    die(print_r(sqlsrv_errors(), true));
}

if ($rec = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
    $WEBHOTELIER_USERNAME = $rec['webHotelierUsername'];
    $WEBHOTELIER_PASSWORD = $rec['webHotelierPassword'];
    $WEBHOTELIER_CREDENTIALS = "$WEBHOTELIER_USERNAME:$WEBHOTELIER_PASSWORD";
} else {
    
    http_response_code(503);
    echo "no credentials: Hotel doesnt exist";
    die();
}

$adults = intval($_GET['adults']);

/*
 * $WEBHOTELIER_USERNAME = $_GET ['WHUser'];
 * $WEBHOTELIER_PASSWORD = $_GET ['WHPwd'];
 */

$date1 = $_GET['from'];

if (! validateDate($date1)) {
    echo ' WRONG DATE FROM ';
    http_response_code(503);
    die();
}

$date2 = $_GET['to'];

if (! validateDate($date2)) {
    echo ' WRONG DATE TO ';
    http_response_code(503);
    die();
}

// validate igf checkin & checkout from to for catalogs
if (finddiff($date1, $date2) < 1) {
    echo '[]';
    http_response_code(503);
    die();
}

// correct checkout to from/to

$parameterdate2 = $date2;

// $date2 -1 day for the query;
$date2 = date('Y-m-d', (strtotime('-1 day', strtotime($date2))));

/* Curl rates */

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://rest.reserve-online.net/manage/rates?from=" . $date1 . "&to=" . $date2);
curl_setopt($ch, CURLOPT_USERPWD, $WEBHOTELIER_CREDENTIALS);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/json',
    'Accept-Language: el'
));
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$result = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch) || ($httpCode != 200)) {
    echo '{"Error" :' . '"Error in parameters "' . ' }';
    http_response_code(503);
    die();
}

curl_close($ch);

$result_obj = json_decode($result, true);

// ********************************

/* Curl Rates Listing: Extract the Room */
$myrates = NULL;

function setmyrates()
{
    global $myrates, $WEBHOTELIER_USERNAME;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "https://rest.reserve-online.net/rate/$WEBHOTELIER_USERNAME");
    
    curl_setopt($ch, CURLOPT_USERPWD, $WEBHOTELIER_CREDENTIALS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Accept-Language: el'
    ));
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    
    $resultrates_obj = json_decode($result, true);
    
    $myrates = $resultrates_obj['data']['rates'];
    
    if (! $myrates) {
        
        echo '{"Error" :' . '"No rates on this Hotel"' . ' }';
        die();
    }
}

//Debug: print_r ($myrates);

// Find the specific room for a key of Rate
function find_room($ratekey)
{
    global $myrates;
    $myconcretrate = intval($ratekey);
    
    foreach ($myrates as $myratesvalue) {
        if ($myratesvalue['id'] == $myconcretrate)
            return ($myratesvalue['room']);
    }
    return NULL;
}

/* Curl room: dor a specific Room check the stop shells */
function stop_shells($room)
{
    global $date1, $date2, $WEBHOTELIER_CREDENTIALS;
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "https://rest.reserve-online.net/manage/availability/$room?from=" . $date1 . "&to=" . $date2);
    curl_setopt($ch, CURLOPT_USERPWD, $WEBHOTELIER_CREDENTIALS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Accept-Language: el'
    ));
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    $result = curl_exec($ch);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch) || ($httpCode != 200)) {
        echo '{"Error" :' . '"Error in parameters availiability "' . ' }';
        http_response_code(503);
        die();
    }
    
    curl_close($ch);
    
    $resultavail_obj = json_decode($result, true);
    
    // print_r ($resultavail_obj);
    
    foreach ($resultavail_obj as $avail) {
        if (isset($avail['stopsell']) && ($avail['stopsell'] == 1))
            return TRUE;
    }
    return FALSE;
    
    // print_r ($resultavail_obj);
}

$availrates = NULL;

function initavail()
{
    global $date1, $parameterdate2, $adults, $children, $WEBHOTELIER_CREDENTIALS, $WEBHOTELIER_USERNAME, $availrates;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "https://rest.reserve-online.net/availability/$WEBHOTELIER_USERNAME?checkin=" . $date1 . "&checkout=" . $parameterdate2 . "&adults=" . $adults . "&children=" . $children);
    
    curl_setopt($ch, CURLOPT_USERPWD, $WEBHOTELIER_CREDENTIALS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Accept-Language: el'
    ));
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch) || ($httpCode != 200)) {
        echo '{"Error" :' . '"Error in parameters availiability "' . ' }';
        http_response_code(503);
        die();
    }
    
    curl_close($ch);
    
    $availrates = json_decode($result, true);
    
    // Debug: print_r ($availrates);
}

/* Rates records */

$sql = "SELECT * FROM WEBRATES WHERE hotelcode=" . $HOTELCODE . ";";

$stmt = sqlsrv_query($connms, $sql);

if ($stmt === false) {
    echo "Error in query preparation/execution.\n";
    die(print_r(sqlsrv_errors(), true));
}

$records = array();

while ($rec = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rateid = $rec['rateid'];
    $ratecode = $rec['ratecode'];
    $discount = $rec['discount'];
    $rounding = $rec['rounding'];
    $additional = $rec['additional'];
    
    $records[] = array(
        'rateid' => $rateid,
        'ratecode' => trim($ratecode),
        'discount' => $discount,
        'rounding' => $rounding,
        'additional' => trim($additional)
    );
}



// ratebufferarray is the keys of ratecodes array:
// Booking, official site etc.
// fetch the minimum price of rates

$ratebuffer = array();




/**
 * 
 *  Functions for calculation 
 *  
 */




/** 
 *  Help Function for rate calculation
 */

function consolidate($value, $key)
{
    global $ratebuffer;
    
    // echo " price= ".$value ." Testcode = ".$key;
    
    // echo "key=".$key . "value= ".$value ."<br>";
    if (isset($ratebuffer[$key]))
        $ratebuffer[$key] = min($value, $ratebuffer[$key]);
    else
        $ratebuffer[$key] = $value;
}


/**
 *  Help Function for rate calculation
 */

function fetch($rateid)
{
    global $records;
    
    foreach ($records as $r) {
        
        if (intval($r['rateid']) == intval($rateid))
            if ($r['ratecode'] != '')
                return $r['ratecode'];
            else
                return NULL;
    }
    
    return NULL;
}


/**
 *  Help Function for rate calculation
 */
function fetchadditional($rateid)
{
    global $records;
    
    foreach ($records as $r) {
        
        if (intval($r['rateid']) == intval($rateid))
            if (trim($r['additional']) != '')
                return $r['additional'];
            else
                return NULL;
    }
    
    return NULL;
}

/**
 *  Help Function for rate calculation
 */
function fetchdiscount($ratekey)
{
    global $records;
    
    foreach ($records as $r) {
        
        if ($r['ratecode'] == $ratekey)
            if (isset($r['discount']))
                return floatval($r['discount']);
            else
                return 0;
    }
    
    return 0; // for availability and additional categorys return 0
}


/**
 *  Help Function for rate calculation
 */
function fetchkeyround($ratekey)
{
    global $records;
    
    foreach ($records as $r) {
        
        if ($r['ratecode'] == $ratekey)
            return intval($r['rounding']);
    }
    
    return 0; // this case never exists
}

/**
 *  Help Function for rate calculation
 */
function set_discount()
{
    global $ratebuffer, $date1, $date2;
    
    // echo finddiff ($date1, $date2)."";
    foreach ($ratebuffer as $key => $value) {
        
        $x = fetchdiscount($key);
        
        $d = (floatval($value)) * ((100 - $x) / 100);
        
        if (fetchkeyround($key) == 0) {
            $ratebuffer[$key] = round($d, 2);
        } else {
            $ratebuffer[$key] = round($d, 0);
        }
    }
}


/**
 *  Help Function for rate calculation
 */

// check if catalog open for the timeperiod is
// also for the period all days are existent
function checkdates($ratearray)
{
    $i = 0;
    foreach ($ratearray as $entry) {
        
        /*
         * Check dates and correct
         */
        
        if (isset($entry['date'])) {
            $i ++;
        } else if (isset($entry['from']) && isset($entry['to'])) {
            $i = $i + finddiff($entry['from'], $entry['to']) + 1;
        } else { // this can never hapen
            echo "[]";
            die();
        }
    }
    // print_r ($ratearray) ;
    return ($i);
    // must be $i>0 ;
}


/**
 *  Help Function for rate calculation
 */
function notokminstay($minstay)
{
    global $date1, $date2;
    if (! isset($minstay))
        return FALSE;
    else if ((1 + finddiff($date1, $date2)) < intval($minstay))
        return TRUE;
    else
        return FALSE;
}


/**
 *  Help Function for rate calculation
 */

// find the sum of a catalog
function findsum($value, $key)
{
    global $adults, $children, $closeout, $avail, $minstay;
    $i = 0;
    $sum = 0;
    /*
     */
    
    while (isset($value[$i])) {
        
        $found = false;
        if ($closeout && isset($value[$i]['closeout'])); // go to $found=false, i have foud closeout
        else if ($minstay && isset($value[$i]['min_stay']) && notokminstay($value[$i]['min_stay'])); // go to $found=false, i have foud closeout
        else if (isset($value[$i]['pricing']))
            foreach ($value[$i]['pricing'] as $pval) { // Ratecode array
                
                if ((intval($pval['adults']) == $adults) && (intval($pval['children']) == $children)) {
                    if (isset($value[$i]['date']))
                        $sum = $sum + floatval($pval['price']);
                    // one day
                    else {
                        $sum = $sum + floatval($pval['price']) * (1 + (finddiff($value[$i]['from'], $value[$i]['to'])));
                    }
                    // more days
                    
                    $found = true;
                    break; // stop search
                }
            } // foreach
        
        /* no found for this category */
        if ($found == false)
            return - 1; // for a pricing not found the adults!!!.
        
        $i ++;
    }
    
    return ($sum);
}


/**
 *  Help Function for rate calculation
 */

// finδ availibility &price
function findavail($value, $key)
{
    global $availrates;
    
    if ($availrates && isset($availrates['data']['rates'])) {
        $i = 0;
        $myvalue = $availrates['data']['rates'];
        while (isset($myvalue[$i])) {
            if (intval($myvalue[$i]['id']) == intval($key)) { // echo($myvalue[$i]['pricing']['price']);
                return $myvalue[$i]['pricing']['price'];
            }
            $i ++;
        }
    }
    
    return - 1;
}

 

/**
 * 
 * End Functions for calculation 
 *  
 */
/* 
 *
 * Body of  calculation
 *
 */

/*Debug: print_r ($result_obj);*/

// init availiability
initavail();

/**** Execute Calc on the Recordslist **/
foreach ($result_obj as $key => $value) {
    
    // check if relevant
    $x = fetch($key);
    if ($x == NULL) { // not relevant
        continue;
    }
    
    if (! $avail) {
        $j = checkdates($value);
        // print_r ($value);
        // if full dates by Json if 0 ore else dont tutch it
        if ($j == (1 + finddiff($date1, $date2))) {
            $sum = findsum($value, $key);
            if ($sum != - 1)
                consolidate($sum, $x);
            
            // if additional exists
            $y = fetchadditional($key);
            if ($y) {
                $sum = findavail($value, $key);
                if ($sum != - 1)
                    consolidate($sum, $y);
            }
        } // find diff
    } else {
        $sum = findavail($value, $key);
        if ($sum != - 1)
            consolidate($sum, $x);
        // if additional exists
        $y = fetchadditional($key);
        if ($y) {
            $sum = findavail($value, $key);
            if ($sum != - 1)
                consolidate($sum, $y);
        }
    } // else avail
} // foreach

http_response_code(200);
set_discount();

echo json_encode($ratebuffer);

?>