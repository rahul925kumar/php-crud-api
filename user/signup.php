<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
require_once('../db_config.php');
$secret = 'sec!ReT423*&';
require '../vendor/autoload.php';
use ReallySimpleJWT\Token;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  if (empty($_POST['email']) || empty($_POST['name']) || empty($_POST['password'])) {
    $data = ["error" => true, "message" => "Fill all mandatory fields"];
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



      /* $payload = array(
        "user_id" => $last_inserted_id,
        "email" => $_POST['email'],
        "exp" => time() + 86400,
      );

      $token = Firebase\JWT\JWT::encode($payload, $secret_key, 'HS256'); */

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
