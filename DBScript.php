<html>
  <body>
    <?php
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
            
    // Create connection and insert into table
    $conn = new mysqli($hostname, $username, $password, $dbname, $port);
    if ($conn->connect_error) 
      die("Connection failed: " . $conn->connect_error);

    if($table == "FTM")  {
      $stmt = $conn->prepare("INSERT INTO FTM (DeviceID, DateTime, Distance, BeaconID) VALUES (?, ?, ?, ?);");
      $stmt->bind_param("ssds", $ID, $date, $distance, $beaconID);
    }
    else if($table == "Accelerometer")	{
      $stmt = $conn->prepare("INSERT INTO Accelerometer (DeviceID, DateTime, XAcceleration, YAcceleration, ZAcceleration) VALUES (?, ?, ?, ?, ?)");
	    $stmt->bind_param("ssddd", $ID, $date, $XAccel, $YAccel, $ZAccel);
    }
    
    $query = $stmt->execute();
    // Check for erros
    //  if($query === TRUE)
    //    echo "Change made successfully";
    //  else
    //    echo "An error ocurred: ". $conn->error;


    // Update other tables
    if($table == "Accelerometer") {# Last entry was in Accelerometer, check for direction change, average velocity and acceleration in last min, acceleration level, NumSprints
      //$command = escapeshellcmd('python3 UpdateDB.py Accelerometer');
      $query = "SELECT * FROM Accelerometer WHERE Entry = (SELECT MAX(Entry) FROM Accelerometer);";
      $result = $conn->query($query);

      // Output data, should only be 1 row
      if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $lastEntry = $row["Entry"];
        $lastTrackerID = $row["DeviceID"];
        $lastDateTime= $row["Datetime"];
        $lastXAccel = $row["XAcceleration"];
        $lastYAccel = $row["YAcceleration"];
        $lastZAccel = $row["ZAcceleration"];      

        // Calculate things
        $query = "SELECT * FROM Accelerometer WHERE DeviceID='{$lastTrackerID}' ORDER BY Entry DESC;";
        $result = $conn->query($query);

        $avgVelocity = 0;
        $avgAccel = 0;
        $iter = 1;
        $row = $result->fetch_assoc();  // Ignore the just inserted row
        while($row = $result->fetch_assoc())  {
          //* Check for direction change, if last entry of this device has XAcceleration or YAcceleration with an opposite sign to what as just inserted, it's a change
          // results are ordered by Entry, so first row is last inserted row of this trackerID
          echo "recent Xaccel was " . $lastXAccel . ", before that is " . $row["XAcceleration"];
          if(($row["XAcceleration"] < 0 and $lastXAccel > 0) or ($row["XAcceleration"] > 0 and $lastXAccel < 0) or ($row["YAcceleration"] < 0 and $lastYAccel > 0) or ($row["YAcceleration"] > 0 and $lastYAccel < 0))  {
            echo "found a direction change";
            // Got a direction change, add to the DB
            // First get current DirectionChanges value
            $query = "SELECT DirectionChanges FROM SessionStats WHERE DeviceID='{$lastTrackerID}';"; //! Should only be 1
            $currentDirChanges = $conn->query($query);
            $currentDirChanges = $currentDirChanges + 1;
            $stmt = $conn->prepare("UPDATE Accelerometer SET DirectionChanges={$currentDirChanges} WHERE DeviceID='{$lastTrackerID}';");
            $stmt->execute();
          }

          //* Calculate avg velocity and acceleration in last minute
          // Check to see if this entry is within 1 minute of last one
          $iterDateTime = $row["Datetime"];
          
          $iterDateTime = new DateTime($iterDateTime);
          $timeDiff = $iterDateTime->diff(new DateTime($lastDateTime));
          echo "interval to +" . $iter . " is";
          echo $timeDiff->format("s \s\\e\c\o\\n\d\s");

          if($timeDiff < 60)  {
            $avgVelocity = $avgVelocity + row["Velocity"];
          }
        }
      }
      else {
        echo "Got " . $result->num_rows . "rows, expecting 1"; 
      }
    }
    //else if($table == "FTM")
      //$command = escapeshellcmd('python3 UpdateDB.py FTM');
    

    //$output = shell_exec($command);


    // Close the connection
    $stmt->close();
    $conn->close();
    ?> 
  </body>
</html>
