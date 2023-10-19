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


rows = [("FFFFFFFFFFFF", "2017/12/10 11:12:13", 2, -3, 5), ("DEADBEEF", "2018/09/01 01:00:02", 1, 2, -3)] 
sql = "INSERT INTO Accelerometer (DeviceID, DateTime, XAcceleration, YAcceleration, ZAcceleration) VALUES (%s, %s, %s, %s, %s)"
values = ["FFFFFFFFFFFF", "2017/12/10 11:11:11", 3, 3, 3]
mycursor.execute(sql, values)

mydb.commit()
