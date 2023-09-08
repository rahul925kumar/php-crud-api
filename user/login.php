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

if ($_SERVER['REQUEST_METHOD'] === "POST") {

  $expected_keys = ['email', 'password'];

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

  $email = $_POST['email'];
  $password = $_POST['password'];

  if (is_valid_email($email)) {
    $query = "SELECT * FROM `users` WHERE `email` = ?";
    $stmt = mysqli_prepare($mysqli, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && $user_data = mysqli_fetch_assoc($result)) {
      $hash = $user_data['password'];
      if (password_verify($password, $hash)) {

        $userId = $user_data['id'];
        $expiration = time() + 86400;
        $issuer = 'localhost';

        $token = Token::create($userId, $secret, $expiration, $issuer);

        $response = ["error" => false, "token" => $token, 'expires_at' => '24 hour from now'];
        echo json_encode($response);
        die;
      } else {
        $response = ["error" => true, "message" => "Incorrect Password!"];
        echo json_encode($response);
        die;
      }
    } else {
      $response = ["error" => true, "message" => "Email doesn't exist"];
      echo json_encode($response);
      die;
    }
  } else {
    $response = ["error" => true, "message" => "Invalid email address"];
    echo json_encode($response);
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
