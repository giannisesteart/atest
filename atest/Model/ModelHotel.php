<?php

namespace Model\Hotel;

/*
 * PASSBBOOK REST Api Version 1.0
 * Model Class realizes calls forHotels
 *
 */
class ModelHotel
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

    /* validate Guestcode and find the Hotelnumber */
    public function hoteldata($hotelcode)
    {
        $sql = "SELECT * FROM HOTELS  WHERE code=" . $hotelcode . "  ;";
        $stmt = sqlsrv_query($this->connms, $sql);
        
        if ($stmt === false) {
            $this->log->error("Error in query preparation/execution:>RESTAPI");
            return NULL;
        }
        if ($rec = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
            return array(
                'title' => $rec['title'],
                'icon' => $rec['icon'],
                'phoneNumber' => $rec['phoneNumber'],
                'htlemail' => $rec['email']
            );
        
        $this->log->error("Hotelcode/ " . $hotelcode . " doesnt exists:>RESTAPI");
        return NULL;
    }

    public function __destruct()
    {}
}
?>