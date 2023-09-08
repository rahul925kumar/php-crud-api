<?php

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

require_once('db_config.php');
$secret = 'sec!ReT423*&';
require 'vendor/autoload.php';

use ReallySimpleJWT\Token;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
  $headers = apache_request_headers();
  $auth_check = userAuthentication($headers, $secret);
  if ($auth_check) {
    $user_id = $auth_check['user_id'];
    $expected_keys = ['first_name', 'last_name', 'email', 'phone', 'city', 'country', 'pincode', 'address', 'gender', 'course'];
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
    

    if (
      empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email']) || empty($_POST['phone']) || empty($_POST['city']) || empty($_POST['country']) || empty($_POST['pincode']) || empty($_POST['address']) || empty($_POST['gender'])
    ) {
      $data = ["error" => true, "message" => "Fill all mandatory fields"];
      die;
    } else {
      $insert = "INSERT INTO `customers` (`user_id`, `first_name`, `last_name`, `email`, `phone`, `city`, `country`, `pincode`, `address`, `gender`, `course`)
      VALUES ('" . $user_id . "', '" . $_POST['first_name'] . "', '" . $_POST['last_name'] . "', '" . $_POST['email'] . "', '" . $_POST['phone'] . "', '" . $_POST['city'] . "', '" . $_POST['country'] . "', '" . $_POST['pincode'] . "', '" . $_POST['address'] . "', '" . $_POST['gender'] . "', '" . $_POST['course'] . "');";
      $result = mysqli_query($mysqli, $insert);
      if ($result) {
        $response = ["error" => false, "message" => "Success"];
        echo json_encode($response);
        die;
      }
    }
  }
  exit();
} else if ($_SERVER['REQUEST_METHOD'] == "GET") {
  $headers = apache_request_headers();
  $auth_check = userAuthentication($headers, $secret);

  if ($auth_check) {
    $user_id = $auth_check['user_id'];

    if (isset($_GET['search']) && !empty($_GET['search'])) {
      $keyword = $_GET['search'];
      $escapedKeyword = mysqli_real_escape_string($mysqli, '%' . $keyword . '%');
      $query = "SELECT * FROM customers WHERE user_id = $user_id AND 
                (first_name LIKE '$escapedKeyword' OR
                 last_name LIKE '$escapedKeyword' OR
                 email LIKE '$escapedKeyword' OR
                 phone LIKE '$escapedKeyword' OR
                 city LIKE '$escapedKeyword' OR
                 country LIKE '$escapedKeyword' OR
                 pincode LIKE '$escapedKeyword' OR
                 address LIKE '$escapedKeyword' OR
                 gender LIKE '$escapedKeyword' OR
                 course LIKE '$escapedKeyword');";

      $result = mysqli_query($mysqli, $query);

      if ($result->num_rows) {
        $records = mysqli_fetch_all($result, MYSQLI_ASSOC);

        $response = [
          "error" => false,
          "message" => "Success",
          "data" => $records
        ];
      } else {
        $response = [
          "error" => true,
          "message" => "No record found."
        ];
      }
    } elseif (isset($_GET['id']) && !empty($_GET['id'])) {
      $customer_id = $_GET['id'];
      $query = "SELECT * FROM `customers` WHERE `user_id` = $user_id AND `id` = $customer_id;";
      $result = mysqli_query($mysqli, $query);

      if ($result->num_rows) {
        $row = mysqli_fetch_assoc($result);

        $response = [
          "error" => false,
          "message" => "Success",
          "data" => $row
        ];
      } else {
        $response = [
          "error" => true,
          "message" => "No record found."
        ];
      }
    } elseif (isset($_GET['sort_by']) && !empty($_GET['sort_by'])) {
      $keyword = $_GET['sort_by'] == 'name' ? 'first_name' : $_GET['sort_by'];
      $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? intval($_GET['limit']) : 10;
      $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
      $offset = ($page - 1) * $limit;

      $query = "SELECT * FROM `customers` WHERE `user_id` = $user_id ORDER BY `customers`.`$keyword` ASC
          LIMIT $limit OFFSET $offset;";
      $result = mysqli_query($mysqli, $query);

      $totalRecordsQuery = "SELECT COUNT(*) AS total FROM `customers` WHERE `user_id` = $user_id;";
      $totalRecordsResult = mysqli_query($mysqli, $totalRecordsQuery);
      $totalRecords = mysqli_fetch_assoc($totalRecordsResult)['total'];
      if ($result->num_rows) {
        $records = mysqli_fetch_all($result, MYSQLI_ASSOC);

        $response = [
          "error" => false,
          "message" => "Success",
          "data" => $records,
          "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total_records" => $totalRecords
          ]
        ];
      } else {
        $response = [
          "error" => true,
          "message" => "No record found."
        ];
      }
    } else {
      $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? intval($_GET['limit']) : 10;
      $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;

      $offset = ($page - 1) * $limit;
      $query = "SELECT * FROM `customers` WHERE `user_id` = $user_id LIMIT $limit OFFSET $offset;";
      $result = mysqli_query($mysqli, $query);

      if ($result) {
        $records = mysqli_fetch_all($result, MYSQLI_ASSOC);

        $totalRecordsQuery = "SELECT COUNT(*) AS total FROM `customers` WHERE `user_id` = $user_id;";
        $totalRecordsResult = mysqli_query($mysqli, $totalRecordsQuery);
        $totalRecords = mysqli_fetch_assoc($totalRecordsResult)['total'];

        $response = [
          "error" => false,
          "message" => "Success",
          "data" => $records,
          "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total_records" => $totalRecords
          ]
        ];
      }
    }

    if (isset($response)) {
      http_response_code(200);
      echo json_encode($response);
      die;
    }
  }
} else if ($_SERVER['REQUEST_METHOD'] == "DELETE") {
  $headers = apache_request_headers();
  $auth_check = userAuthentication($headers, $secret);
  if ($auth_check) {
    $user_id = $auth_check['user_id'];
    if (isset($_GET['id'])) {
      $id = $_GET['id'];
      $delete = "DELETE FROM `customers` WHERE `id`=" . $id . " AND `user_id`=" . $user_id . ";";
      $result = mysqli_query($mysqli, $delete);
      if ($result) {
        if (mysqli_affected_rows($mysqli) > 0) {
          $response = [
            "error" => false,
            "message" => "Record deleted successfully."
          ];
        } else {
          $response = [
            "error" => true,
            "message" => "Record not found or already deleted."
          ];
        }
      } else {
        // An error occurred during the delete operation
        $response = [
          "error" => true,
          "message" => "Error deleting the record."
        ];
      }

      echo json_encode($response);
      die;
    } else {
      $response = ["error" => true, "message" => "ID parameter is missing"];
      echo json_encode($response);
      die;
    }
  }
} else if ($_SERVER['REQUEST_METHOD'] == "PUT") {
  $headers = apache_request_headers();
  $auth_check = userAuthentication($headers, $secret);
  if ($auth_check) {
    $user_id = $auth_check['user_id'];
    if (isset($_GET['id'])) {
      $id = $_GET['id'];
      $rawData = file_get_contents('php://input');
      preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
      if (isset($matches[1])) {
        $boundary = $matches[1];
        $formData = [];
        $parts = explode('--' . $boundary, $rawData);
        foreach ($parts as $part) {
          if (empty(trim($part))) continue;
          list($contentDisposition, $data) = explode("\r\n\r\n", $part, 2);
          preg_match('/name="([^"]+)"/', $contentDisposition, $matches);
          if (isset($matches[1])) {
            $name = $matches[1];
            $formData[$name] = trim($data);
          }
        }

        $query = "UPDATE `customers` SET
        `first_name` = '" . $formData['first_name'] . "',
        `last_name` = '" . $formData['last_name'] . "',
        `email` = '" . $formData['email'] . "',
        `phone` = '" . $formData['phone'] . "',
        `city` = '" . $formData['city'] . "',
        `country` = '" . $formData['country'] . "',
        `pincode` = " . $formData['pincode'] . ",
        `address` = '" . $formData['address'] . "',
        `gender` = '" . $formData['gender'] . "',
        `course` = '" . $formData['course'] . "'
        WHERE `customers`.`id` = " . $id . ";";
        try {
          $result = mysqli_query($mysqli, $query);

          if ($result) {
            $response = ["error" => false, "message" => "Success"];
            echo json_encode($response);
            die;
          } else {
            $error_message = mysqli_error($mysqli);
            throw new Exception($error_message);
          }
        } catch (Exception $e) {
          // Handle the exception here
          $error_message = $e->getMessage();

          $response = ["error" => true, "message" => "Error: $error_message"];
          echo json_encode($response);
        }
      } else {
        // Handle missing boundary
        http_response_code(400); // Bad Request
        echo 'Invalid request format.';
      }

      die;
    }
  }
} else {
  http_response_code(405);
  die("Method not allowed");
}

function userAuthentication($headers, $secret)
{
  if (array_key_exists('User-Authorization', $headers)) {
    $token = str_replace('Bearer ', '', $headers['User-Authorization']);

    $validate = Token::validate($token, $secret);
    if ($validate) {
      $payload = Token::getPayload($token);
      return $payload;
    } else {
      $data = ["error" => true, "message" => "Token validation failed"];
      echo json_encode($data);
      die;
    }
  } else {
    $response = ["error" => true, "message" => "Authorization Failed"];
    echo json_encode($response);
    die;
  }
}
