<html>
  <body>
    <?php
    define("ACCEL_1", 1);
    define("ACCEL_2", 2);
    define("ACCEL_3", 3);
    define("HS_THRESHOLD", 25.2);

    function getDirChangesAndNumSprints($date, $result, $XAccel, $YAccel, $ID, $conn, $entry)  {
      $query = "SELECT * FROM Accelerometer WHERE DeviceID='{$ID}' ORDER BY Datetime DESC;";
      $result = $conn->query($query);

      if ($result->num_rows == 1) {
        // This was the first entry of this tracker, velocity is 0
        $stmt = $conn->prepare("UPDATE Accelerometer SET Velocity=0 WHERE Entry='{$entry}';");
        $stmt->execute();
      }
      else  {
        // Ignore rows until the one with Entry=$entry (multiple instances of this file may be running, last entry is not necessarily the one inserted by this instance)
        do {
          $row = $result->fetch_assoc();
          $currentEntry = $row["Entry"];
        } while($currentEntry != $entry);

        $row = $result->fetch_assoc();  // Check next row, the chronologically previous one

        $currentXAccel = $row["XAcceleration"];
        $currentYAccel = $row["YAcceleration"];
        //* Check for direction change, if second most recent entry of this device has XAcceleration or YAcceleration with an opposite sign to what as just inserted, it's a change
        if(($currentXAccel < 0 and $XAccel > 0) or ($currentXAccel > 0 and $XAccel < 0) or ($currentYAccel < 0 and $YAccel > 0) or ($currentYAccel > 0 and $YAccel < 0))  {
          //* Got a direction change, add to the DB
          // First get current DirectionChanges value
          $query = "SELECT DirectionChanges FROM SessionStats WHERE DeviceID='{$ID}';"; //! Should only be 1 or 0
          $currentDirChanges = $conn->query($query);

          if($currentDirChanges->num_rows == 0)	{
            // No SessionStats for this DeviceID, create new
            //TODO needs to fetch name
            $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$ID}', 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 'TEST', 0, 0, 0, 0, 0, 0, 0);");
          }
          else  {
            $currentDirChanges = $currentDirChanges->fetch_assoc();
            $currentDirChanges = $currentDirChanges["DirectionChanges"] + 1;  // Increment it

            $stmt = $conn->prepare("UPDATE SessionStats SET DirectionChanges={$currentDirChanges} WHERE DeviceID='{$ID}';");
          }
          $stmt->execute();
        }

        //* Check if velocity between last and second to last entry is above HS_THRESHOLD
        // First check time difference
        $currentDatetime = new DateTime($row["Datetime"]);
        $date = new DateTime($date);
        $timeDiff = $date->getTimestamp() - $currentDatetime->getTimestamp();

        //* Do median value between current accel and last entry accel
        $accelSum = sqrt(pow($XAccel, 2) + pow($YAccel, 2));
        $currentAccelSum = sqrt(pow($currentXAccel, 2) + pow($currentYAccel, 2));
        $medianAccel = ($accelSum + $currentAccelSum) / 2;
        
        // Get initial velocity and calculate current velocity
        $initialVelocity = $row["Velocity"];
        $velocity = $initialVelocity + ($medianAccel * $timeDiff);

        // Put this velocity in the current entry (up until now it should be -1)
        $stmt = $conn->prepare("UPDATE Accelerometer SET Velocity={$velocity} WHERE Entry='{$entry}';");
        $stmt->execute();

        // If it's above the threshold, add it to NumSprints
        if(abs($velocity) >= HS_THRESHOLD)	{
          $query = "SELECT NumSprints FROM SessionStats WHERE DeviceID='{$ID}';"; //! Should only be 1
          $results = $conn->query($query);

          if($results->num_rows == 0)	{
            // No SessionStats for this DeviceID, create new
            //TODO needs to fetch name
            $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$ID}', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 'TEST', 0, 0, 0, 1, 0, 0, 0);");
            $stmt->execute();
          }
          else  {
            $row = $results->fetch_assoc();

            $numSprints = $row["NumSprints"] + 1;
            $stmt = $conn->prepare("UPDATE SessionStats SET NumSprints={$numSprints} WHERE DeviceID='{$ID}';");
            $stmt->execute();
          }
        }
      }
    }

    function getAccelLevel($XAccel, $YAccel, $conn, $ID)  {
      $XAccelAbs = abs($XAccel);
      $YAccelAbs = abs($YAccel);

      $accelSum = $XAccelAbs + $YAccelAbs;

      // Check if sum of accelerations is higher than thresholds
      if($accelSum >= ACCEL_1 and $accelSum < ACCEL_2)  {
        // Check number of counts this trackerID has
        $query = "SELECT CountAccelerationLevel1 FROM SessionStats WHERE DeviceID='{$ID}';"; //! Should only be 1 or 0
        $results = $conn->query($query);

        if($results->num_rows == 0)	{
          // No SessionStats for this DeviceID, create new
          //TODO needs to fetch name
          $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$ID}', 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 'TEST', 0, 0, 0, 0, 0, 0, 0);");
        }
        else  {
          $row = $results->fetch_assoc();

          $currentCount = $row["CountAccelerationLevel1"];

          $count = $currentCount + 1;
          $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel1={$count} WHERE DeviceID='{$ID}';");
        }

        $stmt->execute();
      }
      else if($accelSum >= ACCEL_2 and $accelSum < ACCEL_3) {
        $query = "SELECT CountAccelerationLevel2 FROM SessionStats WHERE DeviceID='{$ID}';"; //! Should only be 1 or 0
        $results = $conn->query($query);

        if($results->num_rows == 0)	{
          // No SessionStats for this DeviceID, create new
          //TODO needs to fetch name
          $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$ID}', 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 'TEST', 0, 0, 0, 0, 0, 0, 0);");
        }
        else  {
          $row = $results->fetch_assoc();

          $currentCount = $row["CountAccelerationLevel2"];

          $count = $currentCount + 1;
          $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel2={$count} WHERE DeviceID='{$ID}';");
        }

        $stmt->execute();
      }
      else if($accelSum >= ACCEL_3) {
        $query = "SELECT CountAccelerationLevel3 FROM SessionStats WHERE DeviceID='{$ID}';"; //! Should only be 1 or 0
        $results = $conn->query($query);

        if($results->num_rows == 0)	{
          // No SessionStats for this DeviceID, create new
          //TODO needs to fetch name
          $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$lastTrackerID}', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 'TEST', 0, 0, 0, 0, 0, 0, 0);");
        }
        else  {
          $row = $results->fetch_assoc();

          $currentCount = $row["CountAccelerationLevel3"];

          $count = $currentCount + 1;
          $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel3={$count} WHERE DeviceID='{$ID}';");
        }

        $stmt->execute();
      }
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
    $password = getenv("PASS");
    $port = getenv("DBPORT");

    $data = file_get_contents('php://input');
    $json = json_decode($data);
    //print_r($data);
    //echo "bruh is " . $json->bruh;
    //echo "nepia is " . $json->nepia;
    // Get POST
    if($_POST["FTM"]) {
      print_r("ye we in FTM");
      echo "ftm";
      $FTM = array();
      $table = "FTM";    // Got table

      
      //$distance = $_GET["Distance"];  CHANGE
      //$beaconID = $_GET["BeaconID"];
      
    }
    else if ($_POST["Accelerometer"]) {
      $Accelerometer = array();
      print_r("ye we in Accel");
      echo "accel";
      $table = "Accelerometer";    // Got table

    }
    else if($json->FTM){
    	echo "FTM com json";
    }
    else {
        echo "nope";
    }


    // GET variables (some are always included)
    // $ID = $_GET["DeviceID"];
    // $date = $_GET["Datetime"];
    
    // if($_GET["Distance"]) {
    //   $distance = $_GET["Distance"];
    //   $beaconID = $_GET["BeaconID"];
    //   $table = "FTM";    // Got table
    // }
    // else if($_GET["XAcceleration"])	{
    //   $XAccel = $_GET["XAcceleration"];
    //   $YAccel = $_GET["YAcceleration"];
    //   $ZAccel = $_GET["ZAcceleration"];
    //   $table = "Accelerometer";	// Got table
    // }
            
    // // Create connection to DB
    // $conn = new mysqli($hostname, $username, $password, $dbname, $port);
    // if ($conn->connect_error) 
    //   die("Connection failed: " . $conn->connect_error);

    // if($table == "FTM")  {
    //   $stmt = $conn->prepare("INSERT INTO FTM (DeviceID, Datetime, Distance, BeaconID) VALUES (?, ?, ?, ?);");
    //   $stmt->bind_param("ssds", $ID, $date, $distance, $beaconID);
    // }
    // else if($table == "Accelerometer")	{
    //   $stmt = $conn->prepare("INSERT INTO Accelerometer (DeviceID, Datetime, XAcceleration, YAcceleration, ZAcceleration, Velocity) VALUES (?, ?, ?, ?, ?, ?)");
    //   $velocity = -1;
	  //   $stmt->bind_param("ssdddd", $ID, $date, $XAccel, $YAccel, $ZAccel, $velocity); // Velocity is -1 for now, to be calculated later
    // }
    // $query = $stmt->execute();
    // Check for erros
    //  if($query === TRUE)
    //    echo "Change made successfully";
    //  else
    //    echo "An error ocurred: ". $conn->error;


    // Update tables
    // if($table == "Accelerometer") {# Last entry was in Accelerometer, check for direction change, average velocity and acceleration in last min, acceleration level, NumSprints
    //   $query = "SELECT Entry FROM Accelerometer WHERE DeviceID='{$ID}' AND Datetime='{$date}' AND XAcceleration={$XAccel} AND ZAcceleration={$ZAccel} AND Velocity=-1;";  //! not using YAccel because for some reason the SQL query didn't work
    //   $result = $conn->query($query);

    //   // Output data, should only be 1 row
    //   if ($result->num_rows == 1) {
    //     $row = $result->fetch_assoc();
    //     $entry = intval($row["Entry"]);

    //     // Get levels of acceleration, ignoring ZAccel
    //     getAccelLevel($XAccel, $YAccel, $conn, $ID);

    //     getDirChangesAndNumSprints($date, $result, $XAccel, $YAccel, $ID, $conn, $entry);
    //     //getAvgVelocityAccel($result, $conn, $lastTrackerID, $lastXAccel, $lastYAccel, $lastDateTime);
    //   }
    //   else {
    //     echo "Got " . $result->num_rows . "rows, expecting 1"; 
    //   }
    // }
    //else if($table == "FTM")
      //TODO algoritmo


    // Close the connection
    $stmt->close();
    $conn->close();
    ?> 
  </body>
</html>
