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
if ($requestBody === false || strlen($requestBody) > 5000) {
    http_response_code(413);
    echo json_encode(["error" => "The registration details are too large."]);
    exit;
}

try {
    $request = json_decode($requestBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    http_response_code(400);
    echo json_encode(["error" => "The request body must be valid JSON."]);
    exit;
}

if (!is_array($request)) {
    http_response_code(400);
    echo json_encode(["error" => "Valid registration details are required."]);
    exit;
}

$fullName = $request["full_name"] ?? null;
$emailInput = $request["email"] ?? null;
$customerPassword = $request["password"] ?? null;
$phoneInput = $request["phone_no"] ?? null;

if (!is_string($fullName) || !is_string($emailInput) ||
    !is_string($customerPassword) || !is_string($phoneInput) || strlen($fullName) > 360 ||
    strlen($emailInput) > 254 || strlen($customerPassword) > 72 || strlen($phoneInput) > 90) {
    http_response_code(422);
    echo json_encode(["error" => "Check your name, email address, phone number, and password."]);
    exit;
}

$fullName = trim($fullName);
$customerEmail = strtolower(trim($emailInput));
$phoneNo = trim($phoneInput);

if ($fullName === "" || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL) ||
    strlen($customerPassword) < 12 ||
    $phoneNo === "" || strlen($phoneNo) > 30 ||
    !preg_match('/^[0-9+().\-\s]+$/D', $phoneNo)) {
    http_response_code(422);
    echo json_encode(["error" => "Enter your name, a valid email, a phone number, and a password with 12 to 72 bytes."]);
    exit;
}

$nameLength = function_exists("mb_strlen")
    ? mb_strlen($fullName, "UTF-8")
    : strlen($fullName);
if ($nameLength > 120) {
    http_response_code(422);
    echo json_encode(["error" => "Your name must be 120 characters or fewer."]);
    exit;
}

try {
    require_once __DIR__ . "/db.php";
    require_once __DIR__ . "/customer_db.php";
    ensure_customer_table($conn);

    $passwordHash = password_hash($customerPassword, PASSWORD_DEFAULT);
    $statement = $conn->prepare(
        "INSERT INTO users (full_name, email, password, phone_no, role, created_at)
         VALUES (?, ?, ?, ?, 'customer', NOW())"
    );
    $statement->bind_param("ssss", $fullName, $customerEmail, $passwordHash, $phoneNo);
    $statement->execute();
    $userId = $conn->insert_id;
    $statement->close();
    $conn->close();

    session_regenerate_id(true);
    $_SESSION["user_id"] = $userId;
    $_SESSION["user_name"] = $fullName;
    $_SESSION["user_email"] = $customerEmail;
    $_SESSION["user_role"] = "customer";
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    echo json_encode(["success" => true]);
} catch (mysqli_sql_exception $error) {
    if ($error->getCode() === 1062) {
        http_response_code(409);
        echo json_encode(["error" => "An account with this email already exists. Sign in instead."]);
        exit;
    }

    error_log("Customer registration failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Registration is unavailable. Check the database and try again."]);
} catch (RuntimeException $error) {
    error_log("Customer account table migration failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $error->getMessage()]);
}
?>
