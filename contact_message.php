<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

require_same_origin();
require_csrf_token();

$requestBody = file_get_contents("php://input");
if ($requestBody === false || strlen($requestBody) > 20000) {
    http_response_code(413);
    echo json_encode(["error" => "The contact message is too large."]);
    exit;
}

try {
    $request = json_decode($requestBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    http_response_code(400);
    echo json_encode(["error" => "The request body must be valid JSON."]);
    exit;
}

if (!is_array($request) ||
    !is_string($request["name"] ?? null) ||
    !is_string($request["email"] ?? null) ||
    !is_string($request["message"] ?? null)) {
    http_response_code(422);
    echo json_encode(["error" => "Enter your name, a valid email address, and a message."]);
    exit;
}

$name = trim($request["name"]);
$email = strtolower(trim($request["email"]));
$message = trim($request["message"]);
$nameLength = function_exists("mb_strlen")
    ? mb_strlen($name, "UTF-8")
    : strlen($name);
$messageLength = function_exists("mb_strlen")
    ? mb_strlen($message, "UTF-8")
    : strlen($message);

if ($name === "" || $nameLength > 120 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 ||
    $message === "" || $messageLength > 5000) {
    http_response_code(422);
    echo json_encode(["error" => "Check your name, email address, and message."]);
    exit;
}

try {
    require_once __DIR__ . "/db.php";
    $conn->query(
        "CREATE TABLE IF NOT EXISTS contact_messages (
            message_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(300) NOT NULL,
            email VARCHAR(300) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_contact_messages_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $userId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
    $statement = $conn->prepare(
        "INSERT INTO contact_messages (user_id, name, email, message)
         VALUES (?, ?, ?, ?)"
    );
    $statement->bind_param("isss", $userId, $name, $email, $message);
    $statement->execute();
    $statement->close();
    $conn->close();

    echo json_encode(["success" => true]);
} catch (mysqli_sql_exception $error) {
    error_log("Contact message submission failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Your message could not be saved. Please try again later."]);
}
?>
