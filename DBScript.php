<html>
  <body>
    <?php
    // Get key values for database connection
    $hostname = getenv("HOSTNAME");
    $dbname = getenv("DBNAME");
    $username = getenv("USERNAME");
    $password = getenv("PASSWORD");
    $port = getenv("DBPORT");
    
    // GET variables (some are always included)
    $ID = $_GET["DeviceID"];
    $date = $_GET["DateTime"];
    
    if($_GET["Distance"]) {
      $distance = $_GET["Distance"];
      $beaconID = $_GET["BeaconID"];
      $table = "FTM";    // Got table
    }
    else if($_GET["XAcceleration"])	{
        $XAccel = $_GET["XAcceleration"];
	$YAccel = $_GET["YAcceleration"];
	$ZAccel = $_GET["ZAcceleration"];
	$table = "Accelerometer";	// Got table
    }
            
    // Create connection and insert into table
    $conn = new mysqli($hostname, $username, $password, $dbname, $port);
    if ($conn->connect_error) 
      die("Connection failed: " . $conn->connect_error);

    if($table == "FTM")  {
      $stmt = $conn->prepare("INSERT INTO FTM (DeviceID, DateTime, Distance, BeaconID) VALUES (?, ?, ?, ?);");
      $stmt->bind_param("ssds", $ID, $date, $distance, $beaconID);
    }
    else if($table == "Accelerometer")	{
        $stmt = $conn->prepare("INSERT INTO Acceletometer (DeviceID, DateTime, XAcceleration, YAcceleration, ZAcceleration) VALUES (?, ?, ?, ?, ?)");
	$stmt->bind_param("ssddd", $ID, $date, $XAccel, $YAccel, $ZAccel);
    }
    
    $query = $stmt->execute();
    // Check for erros
    // if($query === TRUE)
    //   echo "Change made successfully";
    // else
    //   echo "An error ocurred: ". $conn->error;
        
    // Close the connection
    $stmt->close();
    $conn->close();
    ?> 
  </body>
</html>
