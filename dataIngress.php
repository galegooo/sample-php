//* This file receives IMU and FTM data from the trackers and inserts it into the DB

<html>
<body>
  <?php
  // Get key values for database connection
  $hostname = getenv("HOSTNAME");
  $dbname = getenv("DBNAME");
  $username = getenv("USERNAME");
  $password = getenv("PASS");
  $port = getenv("DBPORT");

  // Get JSON data
  $data = file_get_contents('php://input');
  $json = json_decode($data);
  
  if($json->FTM) {
    $FTM = $json->FTM;
    $table = "FTM";    // Got table
    $FTMinput = array();

    $firstFTMEntry = $FTM[0]; // To update position

    // Get all JSON entries and store them in FTMinput
    foreach($FTM as $FTMdata)  {
      $tempData = array();

      if($FTMdata->DeviceID != "000000000000")  {  // Ignore when MAC address is all zeros
        $deviceID = $FTMdata->DeviceID;
        $datetime = $FTMdata->Datetime;
        $distance = $FTMdata->Distance;
        $beaconID = $FTMdata->BeaconID;

        array_push($tempData, $deviceID, $datetime, $distance, $beaconID);
        array_push($FTMinput, $tempData);
      }
    }
  }
  else if ($json->IMU) {
    $IMU = $json->IMU;
    $table = "IMU";    // Got table
    $IMUInput = array();

    $firstIMUEntry = $IMU[0]; // To update velocity 

    foreach($IMU as $IMUData)  {
      $tempData = array();

      if($IMUData->DeviceID != "000000000000")  {  // Ignore when MAC address is all zeros
        $deviceID = $IMUData->DeviceID;
        $datetime = $IMUData->Datetime;
        $XAccel = $IMUData->XAcceleration;
        $YAccel = $IMUData->YAcceleration;
        $ZAccel = $IMUData->ZAcceleration;
        $XMag = $IMUData->XMagnetic;
        $YMag = $IMUData->YMagnetic;
        $ZMag = $IMUData->ZMagnetic;
        $XAng = $IMUData->XAngular;
        $YAng = $IMUData->YAngular;
        $ZAng = $IMUData->ZAngular;

        array_push($tempData, $deviceID, $datetime, $XAccel, $YAccel, $ZAccel, $XMag, $YMag, $ZMag, $XAng, $YAng, $ZAng);
        array_push($IMUInput, $tempData);
      }
    }
  }
  else {
    // Close the connection
    exit("Failed to find table name");
  }


  // Create connection to DB
  $conn = new mysqli($hostname, $username, $password, $dbname, $port);
  if ($conn->connect_error) 
    exit("Connection failed: " . $conn->connect_error);

  // Start transaction
  $conn->begin_transaction();
  if($table == "FTM")  {
    $stmt = $conn->prepare("INSERT INTO FTM (DeviceID, Datetime, Distance, BeaconID) VALUES (?, ?, ?, ?);");

    foreach($FTMinput as $entry)  {
      file_put_contents("php://stderr", "Parsing FTM entry with datetime {$entry[1]} and distance {$entry[2]}, from tracker {$entry[0]} to beacon {$entry[3]}\n");
      $stmt->bind_param("ssds", $entry[0], $entry[1], $entry[2], $entry[3]);
      $stmt->execute();
    }
  }
  else if($table == "IMU")	{
    $stmt = $conn->prepare("INSERT INTO IMU (DeviceID, Datetime, XAcceleration, YAcceleration, ZAcceleration, XMagnetic, YMagnetic, ZMagnetic, XAngular, YAngular, ZAngular) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach($IMUInput as $entry)  {
      file_put_contents("php://stderr", "Parsing IMU entry from tracker {$entry[0]} with datetime {$entry[1]}; XAccel {$entry[2]}, YAccel {$entry[3]}, ZAccel {$entry[4]}; XMag {$entry[5]}, YMag {$entry[6]}, ZMag {$entry[7]}; XAng {$entry[8]}, YAng {$entry[9]}, ZAng {$entry[10]}\n");
      $stmt->bind_param("ssddddddddd", $entry[0], $entry[1], $entry[2], $entry[3], $entry[4], $entry[5], $entry[6], $entry[7], $entry[8], $entry[9], $entry[10]);
      $stmt->execute();
    }
  }
  $conn->commit();


  // All done, close the connection
  $stmt->close();
  $conn->close();
  ?> 
</body>
</html>
