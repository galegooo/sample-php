<html>
<body>
  <?php
  error_log("inside DataIngress.php")

  // Get key values for database connection
  $hostname = getenv("HOSTNAME");
  $dbname = getenv("DBNAME");
  $username = getenv("USERNAME");
  $password = getenv("PASS");
  $port = getenv("DBPORT");

  // Get JSON data sent from device
  $data = file_get_contents('php://input');
  $json = json_decode($data);

  //? Data can either be for table FTM or table IMU
  if($json->FTM) {
    error_log("got FTM")
    $FTM = $json->FTM;
    $table = "FTM";
    $FTMInput = array();  // Array to store all entries

    $firstFTMEntry = $FTM[0]; //! To ignore JSON header (needed? need to test)

    // Get all JSON entries and store them in FTMInput
    foreach($FTM as $FTMData)  {
      $tempData = array();

      $deviceID = $FTMData->DeviceID;
      $datetime = $FTMData->Datetime;
      $distance = $FTMData->Distance;
      $beaconID = $FTMData->BeaconID;

      array_push($tempData, $deviceID, $datetime, $distance, $beaconID);
      array_push($FTMInput, $tempData);
    }
  }
  else if ($json->IMU) {
    error_log("got IMU")
    $IMU = $json->IMU;
    $table = "IMU";
    $IMUInput = array();  // Array to store all entries

    $firstIMUEntry = $IMU[0]; //! To ignore JSON header (needed? need to test)

    // Get all JSON entries and store them in IMUInput
    foreach($IMU as $IMUData)  {
      $tempData = array();

      $deviceID = $IMUData->DeviceID;
      $datetime = $IMUData->Datetime;
      $XAcc = $IMUData->XAcceleration;
      $YAcc = $IMUData->YAcceleration;
      $ZAcc = $IMUData->ZAcceleration;
      $XMag = $IMUData->XMagnetic;
      $YMag = $IMUData->YMagnetic;
      $ZMag = $IMUData->ZMagnetic;
      $XAng = $IMUData->XAngular;
      $YAng = $IMUData->YAngular;
      $ZAng = $IMUData->ZAngular;

      array_push($tempData, $deviceID, $datetime, $XAcc, $YAcc, $ZAcc, $XMag, $YMag, $ZMag, $XAng, $YAng, $ZAng);
      array_push($IMUInput, $tempData);
    }
  }
  else 
    die("Unrecognized table name");
  

  //? Create connection to DB
  $conn = new mysqli($hostname, $username, $password, $dbname, $port);
  if ($conn->connect_error) 
    die("Connection failed: " . $conn->connect_error);

  // Start transaction
  $conn->begin_transaction();
  if($table == "FTM")  {
    $stmt = $conn->prepare("INSERT INTO FTM (DeviceID, Datetime, Distance, BeaconID) VALUES (?, ?, ?, ?);");

    foreach($FTMInput as $entry)  {
      $stmt->bind_param("ssds", $entry[0], $entry[1], $entry[2], $entry[3]);
      $stmt->execute();
    }
  }
  else if($table == "IMU")	{
    $stmt = $conn->prepare("INSERT INTO IMU (DeviceID, Datetime, XAcceleration, YAcceleration, ZAcceleration, XMagnetic, YMagnetic, ZMagnetic, XAngular, YAngular, ZAngular) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach($IMUInput as $entry)  {
      $stmt->bind_param("ssddddddddd", $entry[0], $entry[1], $entry[2], $entry[3], $entry[4], $entry[5], $entry[6], $entry[7], $entry[8], $entry[9], $entry[10]);
      $stmt->execute();
    }
  }
  $conn->commit();

  // Close the connection
  $stmt->close();
  $conn->close();
  ?>
</body>
</html>