  <html>
  <body>
    <?php
    define("ACCEL_1", 1);
    define("ACCEL_2", 2);
    define("ACCEL_3", 3);
    define("HS_THRESHOLD", 25.2);

    function getDirChangesAndNumSprints($conn, $entry, $lastEntry)  {
      $IDs = array();
      // Check all new entries, get different DeviceIDs
      for ($iter = $entry; $iter <= $lastEntry; $iter++)  {
        $query = "SELECT DeviceID FROM Accelerometer WHERE Entry='{$iter}';"; //! Not using ZAcceleration
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $deviceID = $row["DeviceID"];

        if(array_search($deviceID, IDs) == false) // Add it to the list if a new MAC is found
          array_push($IDs, $deviceID);
      }

      foreach($IDs as $deviceID)  { // Cycle through different MAC addresses
        $query = "SELECT Entry, Datetime, XAcceleration, YAcceleration, Velocity FROM Accelerometer WHERE DeviceID='{$deviceID}' ORDER BY Datetime ASC;";
        $result = $conn->query($query);

        if ($result->num_rows == 1) {
          // This was the first entry of this tracker, velocity is 0
          $row = $result->fetch_assoc();
          $entry = $row["Entry"];
          $stmt = $conn->prepare("UPDATE Accelerometer SET Velocity=0 WHERE Entry='{$entry}';");
          $stmt->execute();
        }
        else  {
          // Ignore rows until one with Entry >= $entry
          do {
            $row = $result->fetch_assoc();
            $currentEntry = $row["Entry"];
            if($currentEntry < $entry) {
              $previousXAccel = $row["XAcceleration"];  // Keep this for future comparison
              $previousYAccel = $row["YAcceleration"];
              $previousDatetime = $row["Datetime"];
              $previousVelocity = $row["Velocity"];
            }
          } while($currentEntry < $entry);
  

          for ($iter = $entry; $iter <= $entry + $result->num_rows - 1; $iter++)  {
            $currentXAccel = $row["XAcceleration"];
            $currentYAccel = $row["YAcceleration"];
            //* Check for direction change, if previous entry of this device has XAcceleration or YAcceleration with an opposite sign to the current one, it's a change
            if(($currentXAccel < 0 and $previousXAccel > 0) or ($currentXAccel > 0 and $previousXAccel < 0) or ($currentYAccel < 0 and $previousYAccel > 0) or ($currentYAccel > 0 and $previousYAccel < 0))  {
              //* Got a direction change, add to the DB
              // First get current DirectionChanges value
              $query = "SELECT DirectionChanges FROM SessionStats WHERE DeviceID='{$deviceID}';"; //! Should only be 1 or 0
              $currentDirChanges = $conn->query($query);
    
              if($currentDirChanges->num_rows == 0)	{
                // No SessionStats for this DeviceID, create new
                //TODO needs to fetch name
                $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$deviceID}', 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, '{$deviceID}', 0, 0, 0, 0, 0, 0, 0);");
              }
              else  {
                $currentDirChanges = $currentDirChanges->fetch_assoc();
                $currentDirChanges = $currentDirChanges["DirectionChanges"] + 1;  // Increment it
    
                $stmt = $conn->prepare("UPDATE SessionStats SET DirectionChanges={$currentDirChanges} WHERE DeviceID='{$deviceID}';");
              }
              $stmt->execute();
            }
    
            //* Check if velocity between last and current entry is above HS_THRESHOLD
            // First check time difference
            $currentDatetime = new DateTime($row["Datetime"]);
            $previousDatetime = new DateTime($previousDatetime);
            $timeDiff = $currentDatetime->getTimestamp() - $previousDatetime->getTimestamp();
    
            //* Do median value between current accel and last entry accel
            $previousAccelSum = sqrt(pow($previousXAccel, 2) + pow($previousYAccel, 2));
            $currentAccelSum = sqrt(pow($currentXAccel, 2) + pow($currentYAccel, 2));
            $medianAccel = ($previousAccelSum + $currentAccelSum) / 2;
            
            //* Calculate current velocity
            $velocity = $previousVelocity + ($medianAccel * $timeDiff);
    
            //* Put this velocity in the current entry (up until now it should be -1)
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
      }
    }

    function getAccelLevels($conn, $entry, $lastEntry)  {
      for ($iter = $entry; $iter <= $lastEntry; $iter++)  {
        // Get next row
        $query = "SELECT DeviceID, XAcceleration, YAcceleration FROM Accelerometer WHERE Entry='{$iter}';"; //! Not using ZAcceleration
        $result = $conn->query($query);
        $row = $result->fetch_assoc();
        $deviceID = $row["DeviceID"];
        $XAccel = floatval($row["XAcceleration"]);
        $XAccel = floatval($row["XAcceleration"]);

        $accelSum = sqrt(pow($XAccelAbs, 2) + pow($YAccelAbs, 2));

        // Check if sum of accelerations is higher than thresholds
        if($accelSum >= ACCEL_1 and $accelSum < ACCEL_2)  {
          // Check number of counts this trackerID has
          $query = "SELECT CountAccelerationLevel1 FROM SessionStats WHERE DeviceID='{$deviceID}';"; //! Should only be 1 or 0
          $results = $conn->query($query);

          if($results->num_rows == 0)	{
            // No SessionStats for this DeviceID, create new
            //TODO needs to fetch name, right now using MAC as name
            $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$deviceID}', 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, '{$deviceID}', 0, 0, 0, 0, 0, 0, 0);");
          }
          else  {
            $row = $results->fetch_assoc();

            $currentCount = $row["CountAccelerationLevel1"];

            $count = $currentCount + 1;
            $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel1={$count} WHERE DeviceID='{$deviceID}';");
          }

          $stmt->execute();
        }
        else if($accelSum >= ACCEL_2 and $accelSum < ACCEL_3) {
          $query = "SELECT CountAccelerationLevel2 FROM SessionStats WHERE DeviceID='{$deviceID}';"; //! Should only be 1 or 0
          $results = $conn->query($query);

          if($results->num_rows == 0)	{
            // No SessionStats for this DeviceID, create new
            //TODO needs to fetch name, right now using MAC as name
            $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$deviceID}', 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, '{$deviceID}', 0, 0, 0, 0, 0, 0, 0);");
          }
          else  {
            $row = $results->fetch_assoc();

            $currentCount = $row["CountAccelerationLevel2"];

            $count = $currentCount + 1;
            $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel2={$count} WHERE DeviceID='{$deviceID}';");
          }

          $stmt->execute();
        }
        else if($accelSum >= ACCEL_3) {
          $query = "SELECT CountAccelerationLevel3 FROM SessionStats WHERE DeviceID='{$ID}';"; //! Should only be 1 or 0
          $results = $conn->query($query);

          if($results->num_rows == 0)	{
            // No SessionStats for this DeviceID, create new
            //TODO needs to fetch name
            $stmt = $conn->prepare("INSERT INTO SessionStats (DeviceID, Distance, DistanceWalk, DistanceHS, DistanceSprint, DistanceLastMin, DirectionChanges, AverageVelocityLastMin, CountAccelerationLevel1, CountAccelerationLevel2, CountAccelerationLevel3, AverageAccelerationLastMin, Name, DistanceJog, DistanceRacing, Intensity, NumSprints, NumDecceleration1, NumDecceleration2, NumDecceleration3) VALUES ('{$deviceID}', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, '{$deviceID}', 0, 0, 0, 0, 0, 0, 0);");
          }
          else  {
            $row = $results->fetch_assoc();

            $currentCount = $row["CountAccelerationLevel3"];

            $count = $currentCount + 1;
            $stmt = $conn->prepare("UPDATE SessionStats SET CountAccelerationLevel3={$count} WHERE DeviceID='{$deviceID}';");
          }

          $stmt->execute();
        }
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

    // Get JSON data
    $data = file_get_contents('php://input');
    $json = json_decode($data);
    //file_put_contents( "php://stderr", $data);
    
    if($json->FTM) {
      $FTM = $json->FTM;
      $table = "FTM";    // Got table
      $FTMinput = array();

      $firstFTMEntry = $FTM[0]; // To update position

      // Get all JSON entries and store them in FTMinput
      foreach($FTM as $FTMdata)  {
        $tempData = array();

        $deviceID = $FTMdata->DeviceID;
        $datetime = $FTMdata->Datetime;
        $distance = $FTMdata->Distance;
        $beaconID = $FTMdata->BeaconID;

        array_push($tempData, $deviceID, $datetime, $distance, $beaconID);
        array_push($FTMinput, $tempData);
      }
    }
    else if ($json->Accelerometer) {
      $Accelerometer = $json->Accelerometer;
      $table = "Accelerometer";    // Got table
      $Accelinput = array();

      $firstAccelEntry = $Accelerometer[0]; // To update velocity 

      foreach($Accelerometer as $Acceldata)  {
        $tempData = array();

        $deviceID = $Acceldata->DeviceID;
        $datetime = $Acceldata->Datetime;
        $XAccel = $Acceldata->XAcceleration;
        $YAccel = $Acceldata->YAcceleration;
        $ZAccel = $Acceldata->ZAcceleration;
        $velocity = $Acceldata->Velocity;   // This is always -1

        array_push($tempData, $deviceID, $datetime, $XAccel, $YAccel, $ZAccel, $velocity);
        array_push($Accelinput, $tempData);
      }
    }
    else {
      echo "Failed to find table name";
    }


    // Create connection to DB
    $conn = new mysqli($hostname, $username, $password, $dbname, $port);
    if ($conn->connect_error) 
      die("Connection failed: " . $conn->connect_error);

    // Start transaction
    $conn->begin_transaction();
    if($table == "FTM")  {
      $stmt = $conn->prepare("INSERT INTO FTM (DeviceID, Datetime, Distance, BeaconID) VALUES (?, ?, ?, ?);");

      foreach($FTMinput as $entry)  {
        $stmt->bind_param("ssds", $entry[0], $entry[1], $entry[2], $entry[3]);
        $stmt->execute();
      }
    }
    else if($table == "Accelerometer")	{
      $stmt = $conn->prepare("INSERT INTO Accelerometer (DeviceID, Datetime, XAcceleration, YAcceleration, ZAcceleration, Velocity) VALUES (?, ?, ?, ?, ?, ?)");

      foreach($Accelinput as $entry)  {
        $stmt->bind_param("ssdddd", $entry[0], $entry[1], $entry[2], $entry[3], $entry[4], $entry[5]);
        $stmt->execute();
      }
    }
    $conn->commit();
    // $query = $stmt->execute();
    // // Check for erros
    //  if($query === TRUE)
    //    echo "Change made successfully";
    //  else
    //    echo "An error ocurred: ". $conn->error;


    // Update tables
    if($table == "Accelerometer") {# Last entry was in Accelerometer, check for direction change, average velocity and acceleration in last min, acceleration level, NumSprints
      // Get entry of first row
      $query = "SELECT Entry FROM Accelerometer WHERE DeviceID='{$firstAccelEntry->DeviceID}' AND Datetime='{$firstAccelEntry->Datetime}' AND XAcceleration={$firstAccelEntry->XAcceleration} AND ZAcceleration={$firstAccelEntry->ZAcceleration} AND Velocity=-1;";  //! not using YAccel because for some reason the SQL query didn't work
      $result = $conn->query($query);

      // Should only be 1 row
      if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $entry = intval($row["Entry"]);
        $lastEntry = $entry + sizeof($Accelinput) - 1;
        // Get levels of acceleration
        getAccelLevels($conn, $entry, $lastEntry);

        getDirChangesAndNumSprints($conn, $entry, $lastEntry);
        //getAvgVelocityAccel($result, $conn, $lastTrackerID, $lastXAccel, $lastYAccel, $lastDateTime);
      }
      else {
        echo "Got " . $result->num_rows . "rows, expecting 1"; 
      }
    }
    else if($table == "FTM")
      //TODO algoritmo


    // Close the connection
    $stmt->close();
    $conn->close();
    ?> 
  </body>
</html>
