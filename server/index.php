<?php
include_once ('../config_db.inc.php');
include_once ('../sec_funcs.php');
require 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
date_default_timezone_set('America/Los_Angeles');
header("Access-Control-Allow-Origin: *");

$app->get('/holiday', function () use ($app) {
	$app->response()->header('Content-Type', 'application/json');
	$page = $_GET['page']; // get the requested page
	$limit = $_GET['rows']; // get how many rows we want to have into the grid
	$sidx = $_GET['sidx']; // get index row - i.e. user click to sort
	$sord = $_GET['sord']; // get the direction
	if(!$sidx) {
		$sidx =1;
	}
	$conn = getconnection();
	$whereClause = "WHERE 1=1";
	$searchOn = trim($_REQUEST['_search']);
	if($searchOn=='true') {
		$sarr = $_REQUEST;
		foreach( $sarr as $k=>$v) {
			switch ($k) {
				case 'holiname':
					$whereClause .= " AND ".$k." LIKE '%".$v."%'";
					break;
				case 'hid':
					$whereClause .= " AND ".$k." = ".$v;
					break;
				case 'holidate':
					$whereClause .= " AND ".$k." LIKE '".$v."'";
					break;
			}
		}
	}
    $count_query  = "SELECT COUNT(*) AS count FROM holiday ".$whereClause;
    $count_result = $conn->query($count_query);
    $count_row = mysqli_fetch_row($count_result);
    $count = $count_row[0];
	if($count > 0) {
		$total_pages = ceil($count/$limit);
	} else {
		$total_pages = 0;
	}
	if ($page > $total_pages) {
		$page=$total_pages;
	}
	$start = $limit*$page - $limit; // do not put $limit*($page - 1)
	if($start < 0){
		$start = 0;
	}
	$query = "SELECT * FROM holiday ".$whereClause." ORDER BY $sidx $sord LIMIT $start , $limit";
    $result = $conn->query($query);
    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $conn->close();
	$response['page'] = $page;
	$response['total'] = $total_pages;
	$response['records'] = $count;
	$response['rows'] = $rows;
    echo json_encode($response);
});

$app->post('/holiday', function () use ($app) { //BASED ON DRIVER POST
	$app->response()->header('Content-Type', 'application/json');
	$conn = getConnection();
 	$hid = $_POST['id'];
	$oper = $_POST['oper'];
	$action = "ADD";
	if($hid != '_empty' && $oper == 'edit') {
		$action = "EDIT";
	} else if($hid != '_empty' && $oper == 'del') {
		$action = "DELETE";
	}
	if($action == "DELETE"){
		$query = "DELETE FROM holiday WHERE hid = ?";
		$stmt = $conn->prepare($query);
		$stmt->bind_param("s", $hid);
		$stmt->execute();
		$response = array("errcode" => 0, "msg" => "Old entry deleted");
	} else {
		$holiname =  $_POST['holiname'];
		$holiname = str_replace("'", "‘", $holiname); // APOSTROPHE FIX
		$holidate = $_POST['holidate'];
	$isDataValid = true;
	if(strlen($holiname) < 3) {
		//$app->response->setStatus(400);
		//$response = array("errcode" => 101, "msg" => "Holiday Name should be at least 3 characters");
		//$isDataValid = false; //UNCOMMENT TO ENFORCE HOLINAME LENGTH > 3.
	}
	if(strlen($holidate) != 10) {  // NOTE CHANGE, NOW MUST BE 10 CHARS LONG
		$app->response->setStatus(400);
		$response = array("errcode" => 101, "msg" => "Holiday Date should be 10 characters (mm-dd-yyyy");
		$isDataValid = false;
	}
	if($isDataValid) {
		$count_query = "SELECT COUNT(*) AS count FROM holiday WHERE holiname = '{$holiname}'";
		if($action == "EDIT") {
			$count_query .= " and hid != $hid";
		}
		$count_result = $conn->query($count_query);
		$count_row = mysqli_fetch_row($count_result);
		$count = $count_row[0];
		if ($count > 0) {
			$app->response->setStatus(400);
			$response = array("errcode" => 101, "msg" => "Duplicate Holiday Name"); // COPIED THIS FROM THE DRIVER, COMMENT TO MAKE NAMES NOT 'UNIQUE'
		} else { // IF ^ COMMENT THIS TOO
			if($action == "ADD") {
				$query = "INSERT INTO holiday (holiname, holidate) VALUES(?,?)";
				$stmt = $conn->prepare($query);
				$stmt->bind_param("ss", $holiname, $holidate);
				$stmt->execute();
				$response = array("errcode" => 0, "msg" => "New entry added");
			} else if($action == "EDIT") {
				$query = "UPDATE holiday SET holiname=?, holidate=? WHERE hid = ?";
				$stmt = $conn->prepare($query);
				$stmt->bind_param("sss", $holiname, $holidate, $hid);
				$stmt->execute();
				$response = array("errcode" => 0, "msg"=>"Old entry updated");
			}
		} // IF MAKING NAMES NOT UNIQUE, COMMENT THIS OUT TOO.
	}
	}
	$conn->close();
     echo json_encode($response);
});

$app->get('/ionic', function () use ($app) {
	$app->response()->header('Content-Type', 'application/json', 'Access-Control-Allow-Origin: *');
	$conn = getconnection();
	$start = $_GET['start'];
	$end = $_GET['end'];
	$clause = 'deleted = 0';
	if (isset($_GET['phrase'])) {
		$phrase = $_GET['phrase'];
		$clause = "deleted = 0 AND username LIKE '%".$phrase."%'";
	}

	$query = "SELECT * FROM driver_logins WHERE $clause ORDER BY id LIMIT $start, $end";
	$result = $conn->query($query);
	$rows = array();
	while ($row = mysqli_fetch_assoc($result)) {
			$rows[] = $row;
	}
	$response['rows'] = $rows;
	$count_query  = "SELECT COUNT(*) AS count FROM driver_logins where $clause";
	$count_result = $conn->query($count_query);
	$count_row = mysqli_fetch_row($count_result);
	$count = $count_row[0];
	$response['count'] = $count;
	$conn->close();
	echo json_encode($response);
});

$app->get('/password', function () use ($app) {
	$app->response()->header('Content-Type', 'application/json', 'Access-Control-Allow-Origin: *');
	$conn = getconnection();
	$user = $_GET['user'];
	$pass = $_GET['pass'];
	$query = "SELECT passwd FROM driver_logins WHERE username = '{$user}'";
	$result = $conn->query($query);
	$result_row = mysqli_fetch_row($result);
	$correctPass = $result_row[0];
	if ($pass == $correctPass) {
		$response = "correct";
		$conn->close();
		echo json_encode($response);
	}
	else {
		$response = "incorrect";
		$conn->close();
		echo json_encode($response);
	}
});

$app->post('/ionic', function () use ($app) {
	$app->response()->header('Content-Type', 'application/json', 'Access-Control-Allow-Origin: *');
	$conn = getconnection();
	$user = $_GET['user'];
	$pass = $_GET['pass'];
	$count_query = "SELECT COUNT(*) AS count FROM driver_logins where username = '{$user}'";
	$count_result = $conn->query($count_query);
	$count_row = mysqli_fetch_row($count_result);
	$count = $count_row[0];
	if ($count > 0) {
		$query = "DELETE from driver_logins WHERE username = ?";
		$stmt = $conn->prepare($query);
		$stmt->bind_param("s", $user);
		$stmt->execute();
	}
	$query = "INSERT INTO driver_logins (username, passwd) VALUES(?,?)";
	$stmt = $conn->prepare($query);
	$stmt->bind_param("ss", $user, $pass);
	$stmt->execute();
	$response = array("errcode" => 0, "msg"=>"New entry added");
	$conn->close();
	echo json_encode($response);
});

$app->get('/driver', function () use ($app) {
	$app->response()->header('Content-Type', 'application/json');
	$page = $_GET['page']; // get the requested page
	$limit = $_GET['rows']; // get how many rows we want to have into the grid
	$sidx = $_GET['sidx']; // get index row - i.e. user click to sort
	$sord = $_GET['sord']; // get the direction
	if(!$sidx) {
		$sidx =1;
	}
	$conn = getconnection();
	$whereClause = "WHERE 1=1"; // AND deleted=0";  // uncomment to only show rows with deleted=0
	$searchOn = trim($_REQUEST['_search']);
	if($searchOn=='true') {
		$sarr = $_REQUEST;
		foreach( $sarr as $k=>$v) {
			switch ($k) {
				case 'username':
					$whereClause .= " AND ".$k." LIKE '%".$v."%'";
					break;
				case 'passwd':
					$whereClause .= " AND ".$k." = ".$v;
					break;
				case 'id':
					$whereClause .= " AND ".$k." LIKE '".$v."'";
					break;
			}
		}
	}
    $count_query  = "SELECT COUNT(*) AS count FROM driver_logins ".$whereClause;
    $count_result = $conn->query($count_query);
    $count_row = mysqli_fetch_row($count_result);
    $count = $count_row[0];
	if($count > 0) {
		$total_pages = ceil($count/$limit);
	} else {
		$total_pages = 0;
	}
	if ($page > $total_pages) {
		$page=$total_pages;
	}
	$start = $limit*$page - $limit; // do not put $limit*($page - 1)
	if($start < 0){
		$start = 0;
	}
	$query = "SELECT * FROM driver_logins ".$whereClause." ORDER BY $sidx $sord LIMIT $start , $limit";
    $result = $conn->query($query);
    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    $conn->close();
	$response['page'] = $page;
	$response['total'] = $total_pages;
	$response['records'] = $count;
	$response['rows'] = $rows;
    echo json_encode($response);
});

$app->post('/driver', function () use ($app) {
	$app->response()->header('Content-Type', 'application/json');
	$conn = getconnection();
	$id = $_POST['id'];
	$oper = $_POST['oper'];
	$action = "ADD";		//grabs id and oper from post, id may not exist
	if($id != '_empty' && $oper == 'edit'){
		$action = "EDIT";				// if there is an id, and oper = edit, then edit
	} else if($id != 0 && $oper == 'del'){
		$action = "DELETE";			// else if there is an id but oper = del, then delete
	}
	if($action == "DELETE"){ // delete is the simplest one, deletes given id and gives response
			$query = "DELETE from driver_logins WHERE id = ?";
			$stmt = $conn->prepare($query);
			$stmt->bind_param("s", $id);
			$stmt->execute();
			$response = array("errcode" => 0, "msg"=>"Old entry deleted");
	} else {	//for either add or edit, there will always be name and passwd, but if adding
		$name = $_POST['username'];	 // from the SQLtoMySQL, need a few more vars.
		$passwd = $_POST['passwd'];
		if (isset($_POST['sqlid'])) {
			$sqlid = $_POST['sqlid'];
		}
		if (isset($_POST['date'])) {
			$date = $_POST['date'];
		}
		if (isset($_POST['deleted'])) {
			$deleted = $_POST['deleted'];
		}
        $isDataValid = true;
        if(strlen($name) < 3) {
			$app->response->setStatus(400);
			$response = array("errcode" => 101, "msg"=>"Username should be atleast 3 characters");
            $isDataValid = false;
		}

        if(strlen($passwd) < 5) {
			$app->response->setStatus(400);
			$response = array("errcode" => 101, "msg"=>"Password should be atleast 5 characters");
            $isDataValid = false;
		}

        if($isDataValid) {
            $count_query = "SELECT COUNT(*) AS count FROM driver_logins where username = '{$name}'";
            if($action == "EDIT"){
                $count_query .= " and id != $id";
            }
            $count_result = $conn->query($count_query);
            $count_row = mysqli_fetch_row($count_result);
            $count = $count_row[0];

			if($count > 0 && isset($_POST['sqlid'])) {
					$query = "DELETE from driver_logins WHERE username = ?";
					$stmt = $conn->prepare($query);
					$stmt->bind_param("s", $name);
					$stmt->execute();
			}
			if(isset($_POST['sqlid'])) {
				$count_sqlid = "SELECT COUNT(*) AS count FROM driver_logins where sqlid= '{$sqlid}'";
				$count_sresult = $conn->query($count_sqlid);
				$count_srow = mysqli_fetch_row($count_sresult);
				$counts = $count_srow[0];
				if ($counts > 0) {
					$query = "DELETE from driver_logins WHERE sqlid = ?";
					$stmt = $conn->prepare($query);
					$stmt->bind_param("s", $sqlid);
					$stmt->execute();
				}
			}
            if($count > 0 && !isset($_POST['sqlid'])) {
					$app->response->setStatus(400);
					$response = array("errcode" => 101, "msg"=>"Duplicate username");
            } else {
                if($action == "ADD"){
					if (!isset($_POST['sqlid'])) {
						$query = "INSERT INTO driver_logins (username, passwd) VALUES(?,?)";
						$stmt = $conn->prepare($query);
						$stmt->bind_param("ss", $name, $passwd);
						$stmt->execute();
						$response = array("errcode" => 0, "msg"=>"New entry added");
					}
					else {
						$query = "INSERT INTO driver_logins (sqlid, username, passwd, date, deleted)
									VALUES (?,?,?,?,?)";
						$stmt = $conn->prepare($query);
						$stmt->bind_param("sssss", $sqlid, $name, $passwd, $date, $deleted);
						$stmt->execute();
						$response = array("errcode" => 0, "msg"=>"New entry added");
					}
                } else if($action == "EDIT"){
                    $query = "UPDATE driver_logins SET username=?, passwd=? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sss", $name, $passwd, $id);
                    $stmt->execute();
                    $response = array("errcode" => 0, "msg"=>"Old entry updated");
                }
            }
        }
	}
	$conn->close();
    echo json_encode($response);
});

$app->post('/chunk', function () use ($app) { // this is for the SQL -> MySQL transfer of data
	$app->response()->header('Content-Type', 'application/json');
	$conn = getconnection();

	$name = $_POST['username'];	 // grabs the long, comma delimited strings for each column
	$passwd = $_POST['passwd'];
	$sqlid = $_POST['sqlid'];
	$date = $_POST['date'];
	$deleted = $_POST['deleted'];

	$namearray = explode(',', $name); // turns the strings into arrays
	$passwdarray = explode(',', $passwd);
	$sqlidarray = explode(',', $sqlid);
	$datearray = explode(',', $date);
	$deletedarray = explode(',', $deleted);

	for ($i = 0; $i < count($namearray); $i++) { // all arrays have same length, so run for as long as they are

		// this section checks for duplicates in sqlid and username, if found it will delete old one to make
		// room for new one, both sqlid and username are indexed for speed
		$j = 0;
		$count_sqlid = "SELECT COUNT(*) AS count FROM driver_logins where sqlid= '{$sqlidarray[$i]}'";
		//$count_sqlid = "SELECT id FROM driver_logins WHERE sqlid= '{$sqlidarray[$i]}'";
		$count_sresult = $conn->query($count_sqlid);
		$count_srow = mysqli_fetch_row($count_sresult);
		$counts = $count_srow[0];
		if ($counts > 0) {
			$query = "DELETE from driver_logins WHERE sqlid = ?";
			$stmt = $conn->prepare($query);
			$stmt->bind_param("s", $sqlidarray[$i]);
			$stmt->execute();
			$j = 1;
		}
		if ($j == 0) { // uses if loop here to reduce unnecessary queries, if deleted for sqlid, no need to check username
			$count_query = "SELECT COUNT(*) AS count FROM driver_logins where username = '{$namearray[$i]}'";
			$count_result = $conn->query($count_query);
			$count_row = mysqli_fetch_row($count_result);
			$count = $count_row[0];
			if($count > 0) {
				$query = "DELETE from driver_logins WHERE username = ?";
				$stmt = $conn->prepare($query);
				$stmt->bind_param("s", $namearray[$i]);
				$stmt->execute();
			}
		}
		// once old row is deleted if necessary, adds the new user based on i index of each array
		$query = "INSERT INTO driver_logins (sqlid, username, passwd, date, deleted)
									VALUES (?,?,?,?,?)";
		$stmt = $conn->prepare($query);
		$stmt->bind_param("sssss", $sqlidarray[$i], $namearray[$i],
		$passwdarray[$i], $datearray[$i], $deletedarray[$i]);
		$stmt->execute();
		$response = array("errcode" => 0, "msg"=>"New entry added");
	}

	//$response = array("errcode" => 0, "msg" => $namearray[0]);
	$conn->close();
	echo json_encode($response);
});

$app->run();

function getconnection(){
    global $db_host, $db_user, $db_pass, $db_name;
    $conn = mysqli_connect($db_host, $db_user, '', $db_name) or die("Error " . mysqli_error($conn));
    $conn->set_charset("latin1");
    return $conn;
}

?>
