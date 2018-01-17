<?php

namespace Model\Registers;

require_once 'Defines.php';

/*
 * PASSBBOOK REST Api Version 1.0
 * Class realizes Restapi calls for TABLE REGISTRATIONS
 * The Table is the central server table !
 */


class ModelRegisters
{

    private $connms;

    private $log;

    public function __construct($connms, $logfile)
    {
        $this->connms = $connms;
        $this->log = $logfile;
        
        if ($this->connms === false) // returns false
        {
            $this->log->error("Failed connection:RESTAPI");
            echo "Failed connection ";
            die(print_r(sqlsrv_errors(), true));
        }
    }

    // insert registration
    public function insert_registration($guestcode, $hotelcode, $serialnumber, $status, $updated_at)
    {
        $sql = "INSERT INTO REGISTRATIONS ( guestcode, hotelcode, serialnumber, status, last_updated ) 
              VALUES ( " . $guestcode . " ," . $hotelcode . " ,'" . $serialnumber . "','" . $status . "','" . $updated_at . "');";
        
        $stmt = sqlsrv_query($this->connms, $sql);
        if ($stmt === false) {
            $this->log->error("Error in query preparation/execution INSERT :>RESTAPI" . $guestcode . " Serialnumber" . $serialnumber);
            return FALSE;
        }
        
        return TRUE;
    }

    // update registration
    public function update_registration($guestcode, $serialnumber, $updated_at)
    {
        $sql = "UPDATE REGISTRATIONS SET last_updated ='" . $updated_at . "' WHERE guestcode=" . $guestcode . " AND serialnumber ='" . $serialnumber . "';";
        
        $stmt = sqlsrv_query($this->connms, $sql);
        if ($stmt === false) {
            $this->log->error("Error in query preparation/execution INSERT :>UPDATE" . $guestcode . " Serialnumber" . $serialnumber);
            return FALSE;
        }
        
        return TRUE;
    }

    /*
     * Delete from registration table is virtual delete: Set the status DELETED_FROM_USER
     * /* The device in the devicetable will not deleted
     */
    public function delete_registration($guestcode, $serialnumber, $deviceid)
    {
        $sql = "UPDATE REGISTRATIONS SET status ='" . DELETED_FROM_USER . "' WHERE guestcode=" . $guestcode . " AND deviceid=" . $deviceid . " AND serialnumber >='" . $serialnumber . "';";
        $stmt = sqlsrv_query($this->connms, $sql);
        if ($stmt === false) {
            $this->log->error("Error in query preparation/execution DELETED :>RESTAPI" . $guestcode . " Serialnumber" . $serialnumber);
            return FALSE;
        }
        
        return TRUE;
    }

    /* if modified: serialnumber than $sincedate */
    public function modified_serial($guestcode, $serialnumber, $sincedate)
    {
        $sql = "SELECT * FROM REGISTRATIONS WHERE status ='" . UPDATE_CARD . "' AND guestcode=" . $guestcode . " AND serialnumber='" . $serialnumber . "' AND last_updated > '" . $sincedate . "';";
        
        $stmt = sqlsrv_query($this->connms, $sql);
        if ($stmt === false) 
        {
            $this->log->error("Error in query preparation/execution Serial modified since :>RESTAPI" . $guestcode);
            return (FALSE);
        }
        
        if (sqlsrv_has_rows($stmt))
            return TRUE;
        else
            return FALSE;
    }

    // lookup all serialnumbers since date
    public function lookupserials($guestcode, $sincedate, $deviceid)
    {
        $serials = array();
        
        ($sincedate != NULL) ? $sql = "SELECT guestcode, serialnumber FROM REGISTRATIONS WHERE status ='" . UPDATE_CARD . "' AND guestcode=" . $guestcode . " AND deviceid=" . $deviceid . " AND last_updated >'" . $sincedate . "';" : $sql = "SELECT guestcode, serialnumber FROM REGISTRATIONS WHERE status ='" . UPDATE_CARD . "' AND guestcode=" . $guestcode . " AND deviceid=" . $deviceid . ";";
        
        $stmt = sqlsrv_query($this->connms, $sql);
        if ($stmt === false) {
            $this->log->error("Error in query preparation/execution LOOKUPSERIALS :>RESTAPI" . $guestcode . " &deviceid=" . $deviceid);
            
            return (array(
                'error' => TRUE,
                'serialnumbers' => $serials
            ));
        }
        
        // Convention: Serialnumber of client is with guestcode ex. AH1-27824
        while ($rec = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            array_push($serials, $rec['serialnumber'] . "-" . $rec['guestcode']);
        }
        
        return array(
            'error' => FALSE,
            'serialnumbers' => $serials
        );
    }

    // lookup if is registered device and return deviceid
    public function isregistered($guestcode, $serialnumber, $status)
    {
        if ($status != NULL)
            $sql = "SELECT * FROM REGISTRATIONS WHERE status ='" . $status . "' AND guestcode=" . $guestcode . " AND serialnumber='" . $serialnumber . "';";
        else
            $sql = "SELECT * FROM REGISTRATIONS WHERE guestcode=" . $guestcode . " AND serialnumber='" . $serialnumber . "';";
        
        $stmt = sqlsrv_query($this->connms, $sql);
        if ($stmt === false) {
            $this->log->error("Error in query preparation/execution SELECT :>RESTAPI" . $guestcode . $serialnumber);
            
            return (array(
                'registrationid' => NULL,
                'deviceid' => NULL,
                'last_updated' => NULL,
                "status" => null
            ));
        }
        
        if ($rec = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            return (array(
                'registrationid' => $rec['id'],
                'deviceid' => $rec['deviceid'],
                'last_updated' => $rec['last_updated'],
                "status" => $rec['status']
            ));
        } else {
            // $this->log->error ("Registration for ". $guestcode." doesnt exists:>RESTAPI");
            return (array(
                'registrationid' => NULL,
                'deviceid' => NULL,
                'last_updated' => NULL,
                "status" => null
            ));
        }
    }

    // lookup if is registered device and return deviceid for AH3
    // Note if exists for AH3 a guestcode this is the pivotguestcode
    public function isregisteredAH($hotelcode, $guestcode, $serialnumber, $status)
    {
        if ($serialnumber = "AH3")
            $sql = "SELECT * FROM
				   (SELECT b.code FROM  
							  ( SELECT    email    FROM Guests where code=$guestcode AND hotelCode=$hotelcode) a
				  INNER JOIN   ( SELECT code, email    FROM GUESTS where hotelCode=$hotelcode) b
				  ON   a.email = b.email) c 
					INNER JOIN  REGISTRATIONS ON c.code=REGISTRATIONS.guestcode  
					  WHERE serialnumber='AH3' ; ";
        else
            $sql = "SELECT * FROM REGISTRATIONS WHERE guestcode=" . $guestcode . " AND serialnumber='" . $serialnumber . "';";
        
        $stmt = sqlsrv_query($this->connms, $sql);
        if ($stmt === false) {
            $this->log->error("Error in query preparation/execution MODELREGISTER is_registeredAH :>RESTAPI" . $guestcode . $serialnumber);
            return (NULL);
        }
        
        if ($rec = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            
            return (array(
                'registrationguest' => $rec['guestcode'],
                'registrationid' => $rec['id'],
                'deviceid' => $rec['deviceid'],
                'last_updated' => $rec['last_updated'],
                "status" => $rec['status']
            ));
        } else {
            // $this->log->error ("Registration for ". $guestcode." doesnt exists:>RESTAPI");
            return (array(
                'registrationguest' => $guestcode,
                'registrationid' => NULL,
                'deviceid' => NULL,
                'last_updated' => NULL,
                "status" => null
            ));
        }
    }

    /* Insert device in Registers */
    public function registerdevice($id, $deviceid)
    {
        $sql = "UPDATE REGISTRATIONS  SET deviceid= " . $deviceid . ", status='" . UPDATE_CARD . "' 
		                               WHERE id = " . $id . " AND status IN  ('" . UPDATE_CARD . "','" . DELETED_FROM_USER . "') ;";
        
        $stmt = sqlsrv_query($this->connms, $sql);
        if ($stmt === false) {
            $this->log->error("Error in query preparation/execution: registerdevice fo id=" . $id . " >RESTAPI");
            return FALSE;
        }
        
        return TRUE;
    }

    public function __destruct()
    {}
}
?>