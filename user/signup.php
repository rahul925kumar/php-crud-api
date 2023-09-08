<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
header("Access-Control-Allow-Origin: *"); // Change * to your specific domain if needed
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: User-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
  header("Access-Control-Allow-Headers: User-Authorization, Content-Type, X-Requested-With, access-control-allow-origin");
  http_response_code(200);
  exit;
}

require_once('../db_config.php');
$secret = 'sec!ReT423*&';
require '../vendor/autoload.php';
use ReallySimpleJWT\Token;

if ($_SERVER['REQUEST_METHOD'] == "POST") {

  $expected_keys = ['name', 'email', 'password'];

  $missing_keys = array_diff($expected_keys, array_keys($_POST));

  if (!empty($missing_keys)) {
    $missing_key = reset($missing_keys);
    $data = ["error" => true, "message" => "The '$missing_key' key is missing."];
    echo json_encode($data);
    die;
  }


  $missingKeys = [];
  foreach ($_POST as $key => $value) {
    if (empty($value)) {
      $missingKeys[] = $key;
    }
  }

  if (!empty($missingKeys)) {
    $data = ["error" => true, "message" => "The following keys have missing values: " . implode(', ', $missingKeys)];
    echo json_encode($data);
    die;
  }

  if (is_valid_email($_POST['email'])) {

    if (checkUserExists($_POST['email'])) {
      $data = ["error" => true, "message" => "Email already exsits"];
      echo json_encode($data);
      die;
    } else {
      $insert = 'INSERT INTO `users` (`name`, `email`, `password`) VALUES ("' . $_POST['name'] . '", "' . $_POST['email'] . '", "' . password_hash($_POST['password'], PASSWORD_DEFAULT) . '")';
      $result = mysqli_query($mysqli, $insert);
      $last_inserted_id = mysqli_insert_id($mysqli);


      $userId = $last_inserted_id;
      $expiration = time() + 86400;
      $issuer = 'localhost';

      $token = Token::create($userId, $secret, $expiration, $issuer);
      
      $response = ["error" => false, "token" => $token, 'expires_at' => '24 hour from now'];
      echo json_encode($response);
      die;
    }
  } else {
    $data = ["error" => true, "message" => "Invalid email address"];
    echo json_encode($data);
    die;
  }

  exit();
} else {
  http_response_code(405);
  die("Method not allowed");
}


function is_valid_email($email)
{
  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    list($username, $domain) = explode('@', $email);
    if (checkdnsrr($domain, 'MX')) {
      return true;
    }
  }
  return false;
}

function checkUserExists($email)
{
  global $mysqli;
  $query = "SELECT * FROM `users` WHERE `email` = '" . $email . "'";
  $result = mysqli_query($mysqli, $query);

  if ($result) {
    if ($result->num_rows < 1) {
      return false;
    } else {
      return true;
    }
  } else {
    echo "Error executing the query: " . mysqli_error($mysqli);
  }
}
