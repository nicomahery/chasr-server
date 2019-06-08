<?php

// written by sqall
// twitter: https://twitter.com/sqall01
// blog: https://h4des.org/blog
// github: https://github.com/sqall01
// 
// Licensed under the GNU Affero General Public License, version 3.

require_once(__DIR__ . "/config/config.php");
require_once(__DIR__ . "/lib/helper.php");
require_once(__DIR__ . "/lib/objects.php");

// Set global settings.
header("Content-type: application/json");
date_default_timezone_set("UTC");

// Start session.
$cookie_conf = session_get_cookie_params();
session_set_cookie_params($cookie_conf["lifetime"], // lifetime
                          $cookie_conf["path"], // path
                          $cookie_conf["domain"], // domain
                          TRUE, // secure
                          TRUE); // httponly
session_start();

// Check if needed data is given.
if(!isset($_GET["mode"])) {
    $result = array();
    $result["code"] = ErrorCodes::ILLEGAL_MSG_ERROR;
    $result["msg"] = "Mode not set.";
    die(json_encode($result));
}
if($_GET["mode"] !== "devices"
   && !isset($_GET["device"])) {
    $result = array();
    $result["code"] = ErrorCodes::ILLEGAL_MSG_ERROR;
    $result["msg"] = "Device is not set.";
    die(json_encode($result));
}

$mysqli = new mysqli(
    $config_mysql_server,
    $config_mysql_username,
    $config_mysql_password,
    $config_mysql_database,
    $config_mysql_port);

if($mysqli->connect_errno) {
    $result = array();
    $result["code"] = ErrorCodes::DATABASE_ERROR;
    $result["msg"] = $mysqli->connect_error;
    die(json_encode($result));
}

// Get user id.
$user_id = auth_user($mysqli);
if($user_id === -1 || $user_id === -4) {
    chasr_session_destroy();
    $result = array();
    $result["code"] = ErrorCodes::AUTH_ERROR;
    $result["msg"] = "Wrong user or password.";
    die(json_encode($result));
}
else if($user_id === -2) {
    chasr_session_destroy();
    $result = array();
    $result["code"] = ErrorCodes::DATABASE_ERROR;
    $result["msg"] = "Database error during authentication.";
    die(json_encode($result));
}
else if($user_id === -3) {
    chasr_session_destroy();
    $result = array();
    $result["code"] = ErrorCodes::SESSION_EXPIRED;
    $result["msg"] = "Authenticated session expired.";
    die(json_encode($result));
}

// Check if the mode is supported.
switch($_GET["mode"]) {
    case "last":
    case "view":
    case "devices":
        break;
    default:
        $result = array();
        $result["code"] = ErrorCodes::ILLEGAL_MSG_ERROR;
        $result["msg"] = "Mode unknown.";
        die(json_encode($result));
}

// Fetch data.
$fetched_data_result = NULL;
switch($_GET["mode"]) {
    case "last":

        // Get limit of gps positions.
        $limit = 1;
        if(isset($_GET["limit"])) {
            $limit = intval($_GET["limit"]);
        }
        if($limit < 1) {
            $limit = 1;
        }
        if($limit > 1000) {
            $limit = 1000;
        }

        // Get gps positions.
        $select_gps = "SELECT "
                      . "utctime,"
                      . "iv,"
                      . "latitude,"
                      . "longitude,"
                      . "altitude,"
                      . "speed,"
                      . "device_name "
                      . "FROM chasr_gps "
                      . "WHERE users_id="
                      . intval($user_id)
                      . " AND device_name='"
                      . $mysqli->real_escape_string($_GET["device"])
                      . "' "
                      . " ORDER BY utctime DESC"
                      . " LIMIT "
                      . $limit;

        $fetched_data_result = $mysqli->query($select_gps);
        if(!$fetched_data_result) {
            $result = array();
            $result["code"] = ErrorCodes::DATABASE_ERROR;
            $result["msg"] = $mysqli->error;
            die(json_encode($result));
        }
        break;

    case "view":

        // Check if the time interval is given.
        if(!isset($_GET["start"])
           || !isset($_GET["end"])) {

            $result = array();
            $result["code"] = ErrorCodes::ILLEGAL_MSG_ERROR;
            $result["msg"] = "Time interval not set.";
            die(json_encode($result));
        }

        // Get gps positions.
        $select_gps = "SELECT "
                      . "utctime,"
                      . "iv,"
                      . "latitude,"
                      . "longitude,"
                      . "altitude,"
                      . "speed,"
                      . "device_name "
                      . "FROM chasr_gps "
                      . "WHERE users_id="
                      . intval($user_id)
                      . " AND device_name='"
                      . $mysqli->real_escape_string($_GET["device"])
                      . "' "
                      . " AND utctime >= "
                      . intval($_GET["start"])
                      . " AND utctime <= "
                      . intval($_GET["end"])
                      . " ORDER BY utctime ASC";
        $fetched_data_result = $mysqli->query($select_gps);
        if(!$fetched_data_result) {
            $result = array();
            $result["code"] = ErrorCodes::DATABASE_ERROR;
            $result["msg"] = $mysqli->error;
            die(json_encode($result));
        }
        break;

    case "devices":
        // Get all devices positions.
        $select_devices = "SELECT DISTINCT "
                          . "device_name "
                          . "FROM chasr_gps "
                          . "WHERE users_id="
                          . intval($user_id)
                          . " ORDER BY device_name ASC";
        $fetched_data_result = $mysqli->query($select_devices);
        if(!$fetched_data_result) {
            $result = array();
            $result["code"] = ErrorCodes::DATABASE_ERROR;
            $result["msg"] = $mysqli->error;
            die(json_encode($result));
        }
        break;

    default:
        $result = array();
        $result["code"] = ErrorCodes::ILLEGAL_MSG_ERROR;
        $result["msg"] = "Mode unknown.";
        die(json_encode($result));
}

if($fetched_data_result === NULL) {
    $result = array();
    $result["code"] = ErrorCodes::DATABASE_ERROR;
    $result["msg"] = "No data.";
    die(json_encode($result));
}

// Prepare data array to return.
switch($_GET["mode"]) {
    case "devices":
        $output_data = array();
        while($row = $fetched_data_result->fetch_assoc()) {
            $element = array("device_name" => $row["device_name"]);
            // Append element to array.
            $output_data[] = $element;
        }
        break;

    default:
        $output_data = array();
        while($row = $fetched_data_result->fetch_assoc()) {
            $element = array("device_name" => $row["device_name"],
                "utctime" => intval($row["utctime"]),
                "iv" => $row["iv"],
                "lat" => $row["latitude"],
                "lon" => $row["longitude"],
                "alt" => $row["altitude"],
                "speed" => $row["speed"]);
            // Append element to array.
            $output_data[] = $element;
        }
}

$result = array();
$result["code"] = ErrorCodes::NO_ERROR;
$result["data"] = $output_data;
$result["msg"] = "Success.";
echo json_encode($result);

?>