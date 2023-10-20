<html>
  <body>
    <?php
    define("ACCEL_1", 1);
    define("ACCEL_2", 2);
    define("ACCEL_3", 3);
    define("HS_THRESHOLD", 25.2);

    function getDirChangesAndNumSprints($lastDateTime, $result, $lastXAccel, $lastYAccel, $lastTrackerID, $conn, $lastEntry)  {
      $row = $result->fetch_assoc();  // Next row (second most recent row, in this case)

      $secondLastXAccel = $row["XAcceleration"];
      $secondLastYAccel = $row["YAcceleration"];
      //* Check for direction change, if second most recent entry of this device has XAcceleration or YAcceleration with an opposite sign to what as just inserted, it's a change
      if(($secondLastXAccel < 0 and $lastXAccel > 0) or ($secondLastXAccel > 0 and $lastXAccel < 0) or ($secondLastYAccel < 0 and $lastYAccel > 0) or ($secondLastYAccel > 0 and $lastYAccel < 0))  {
        //* Got a direction change, add to the DB
        // First get current DirectionChanges value
        $query = "SELECT DirectionChanges FROM SessionStats WHERE DeviceID='{$lastTrackerID}';"; //! Should only be 1
        $currentDirChanges = $conn->query($query);
        $currentDirChanges = $currentDirChanges->fetch_assoc();
        $currentDirChanges = $currentDirChanges["DirectionChanges"] + 1;  // Increment it

        $stmt = $conn->prepare("UPDATE SessionStats SET DirectionChanges={$currentDirChanges} WHERE DeviceID='{$lastTrackerID}';");
        $stmt->execute();
      }

      //* Check if velocity between last and second to last entry is above HS_THRESHOLD
      // First check time difference
      $thisEntryDateTime = new DateTime($row["Datetime"]);
      $timeDiff = $lastDateTime->getTimestamp() - $thisEntryDateTime->getTimestamp();

      $accelSum = $lastXAccel + $lastYAccel;
	    
      // Get initial velocity and calculate current velocity
      $initialVelocity = $row["Velocity"];
      $velocity = $initialVelocity + ($accelSum * $timeDiff);

      // Put this velocity in the current entry (up until now it should be -1)
      $stmt = $conn->prepare("UPDATE Accelerometer SET Velocity={$velocity} WHERE Entry='{$lastEntry}';");

      // If it's above the threshold, add it to NumSprints
      if(abs($velocity) >= HS_THRESHOLD)	{
        $query = "SELECT NumSprints FROM SessionStats WHERE DeviceID='{$lastTrackerID}';"; //! Should only be 1
      	$results = $conn->query($query);
      	$row = $results->fetch_assoc();

	      $numSprints = $row["NumSprints"] + 1;
	      $stmt = $conn->prepare("UPDATE SessionStats SET NumSprints={$numSprints} WHERE DeviceID='{$lastTrackerID}';");
      }
      $stmt->execute();
    }

    function getAccelLevel($XAccel, $YAccel, $conn, $lastTrackerID)  {
      $XAccelAbs = abs($XAccel);
      $YAccelAbs = abs($YAccel);

      $accelSum = $XAccelAbs + $YAccelAbs;

      // Check number of counts this trackerID has
      $query = "SELECT CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3 FROM SessionStats WHERE DeviceID='{$lastTrackerID}';"; //! Should only be 1
      $results = $conn->query($query);
      $row = $results->fetch_assoc();

      $currentCount1 = $row["CountAccelerationLevel1"];
      $currentCount2 = $row["CountAccelerationLevel2"];
      $currentCount3 = $row["CountAccelerationLevel3"];

      // Check if sum of accelerations is higher than thresholds
      if($accelSum >= ACCEL_1 and $accelSum < ACCEL_2)  {
        $count = $currentCount1 + 1;
        $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel1={$count} WHERE DeviceID='{$lastTrackerID}';");
      }
      else if($accelSum >= ACCEL_2 and $accelSum < ACCEL_3) {
        $count = $currentCount2 + 1;
        $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel2={$count} WHERE DeviceID='{$lastTrackerID}';");
      }
      else if($accelSum >= ACCEL_3) {
        $count = $currentCount3 + 1;
        $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel3={$count} WHERE DeviceID='{$lastTrackerID}';");
      }
      $stmt->execute();
    }

    function getAvgVelocityAccel($result, $conn, $lastTrackerID, $lastXAccel, $lastYAccel, $lastDateTime) {
      $avgVelocity = 0;
      $avgAccel = 0;
      $iter = 1;

      while($row = $result->fetch_assoc())  {
        echo "on getAvgVelocityAccel, checking row with entry " . $row["Entry"];
        //* Calculate avg velocity and acceleration in last minute
        // Check to see if this entry is within 1 minute of last one
        $thisEntryDateTime = new DateTime($row["Datetime"]);

        $timeDiff = $lastDateTime->getTimestamp() - $thisEntryDateTime->getTimestamp();
        echo "interval to +" . $iter . " is " . $timeDiff;

        if(intval($timeDiff) < 60)  {
          $avgVelocity = $avgVelocity;
        }

        $iter = $iter + 1;
      }
    }


    // Get key values for database connection
    $hostname = getenv("HOSTNAME");
    $dbname = getenv("DBNAME");
    $username = getenv("USERNAME");
    $password = "AVNS_pUW1PjbbNkYyctEw7Ym";
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
            
    // Create connection to DB
    $conn = new mysqli($hostname, $username, $password, $dbname, $port);
    if ($conn->connect_error) 
      die("Connection failed: " . $conn->connect_error);

    if($table == "FTM")  {
      $stmt = $conn->prepare("INSERT INTO FTM (DeviceID, DateTime, Distance, BeaconID) VALUES (?, ?, ?, ?);");
      $stmt->bind_param("ssds", $ID, $date, $distance, $beaconID);
    }
    else if($table == "Accelerometer")	{
      $stmt = $conn->prepare("INSERT INTO Accelerometer (DeviceID, DateTime, XAcceleration, YAcceleration, ZAcceleration, Velocity) VALUES (?, ?, ?, ?, ?, ?)");
	    $stmt->bind_param("ssdddd", $ID, $date, $XAccel, $YAccel, $ZAccel, -1); // Velocity is -1 for now, to be calculated later
    }
    $query = $stmt->execute();
    // Check for erros
    //  if($query === TRUE)
    //    echo "Change made successfully";
    //  else
    //    echo "An error ocurred: ". $conn->error;


    // Update other tables
    if($table == "Accelerometer") {# Last entry was in Accelerometer, check for direction change, average velocity and acceleration in last min, acceleration level, NumSprints
      $query = "SELECT * FROM Accelerometer WHERE Entry = (SELECT MAX(Entry) FROM Accelerometer);";
      $result = $conn->query($query);

      // Output data, should only be 1 row
      if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $lastEntry = intval($row["Entry"]);
        $lastTrackerID = $row["DeviceID"];
        $lastDateTime= new DateTime($row["Datetime"]);
        $lastXAccel = intval($row["XAcceleration"]);
        $lastYAccel = intval($row["YAcceleration"]);
        $lastZAccel = intval($row["ZAcceleration"]);

        // Get levels of acceleration, ignoring ZAccel
        getAccelLevel($lastXAccel, $lastYAccel, $conn, $lastTrackerID);

	      //TODO here check if there is any more rows, might be the first

        // Calculate things
        $query = "SELECT * FROM Accelerometer WHERE DeviceID='{$lastTrackerID}' ORDER BY Entry DESC;";
        $result = $conn->query($query);

        $row = $result->fetch_assoc();  // Ignore the just inserted row

        getDirChangesAndNumSprints($lastDateTime, $result, $lastXAccel, $lastYAccel, $lastTrackerID, $conn, $lastEntry);
        //getAvgVelocityAccel($result, $conn, $lastTrackerID, $lastXAccel, $lastYAccel, $lastDateTime);
      }
      else {
        echo "Got " . $result->num_rows . "rows, expecting 1"; 
      }
    }
    //else if($table == "FTM")
      //TODO algoritmo


    // Close the connection
    $stmt->close();
    $conn->close();
    ?> 
  </body>
</html>
