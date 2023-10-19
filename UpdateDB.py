import mysql.connector
import sys
import os

mydb = mysql.connector.connect(
  host=os.environ["HOSTNAME"],
  user=os.environ["USERNAME"],
  password=os.environ["PASSWORD"],
  database=os.environ["DBNAME"],
  port=os.environ["DBPORT"]
)

mycursor = mydb.cursor()

#if(sys.argv[1] == "FTM"): # Last entry was in FTM, check for distance

if(sys.argv[1] == "Accelerometer"): # Last entry was in Accelerometer, check for direction change, average velocity and acceleration in last min, acceleration level, NumSprints
  query = "SELECT * FROM Accelerometer WHERE Entry = (SELECT MAX(Entry) FROM Accelerometer);"
  mycursor.execute(query)
  lastRow = mycursor.fetchall() # Should only be 1 row

  lastEntry = lastRow["Entry"]
  lastTrackerID = lastRow["DeviceID"]
  lastDateTime= lastRow["DateTime"]
  lastXAccel = lastRow["XAcceleration"]
  lastYAccel = lastRow["YAcceleration"]
  lastZAccel = lastRow["ZAcceleration"]
