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

$body = file_get_contents("php://input");
if ($body === false || strlen($body) > 5000) {
    http_response_code(413);
    echo json_encode(["error" => "The sign-in details are too large."]);
    exit;
}

try {
    $request = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    http_response_code(400);
    echo json_encode(["error" => "The request body must be valid JSON."]);
    exit;
}

if (!is_array($request) ||
    !is_string($request["email"] ?? null) ||
    !is_string($request["password"] ?? null) ||
    strlen($request["email"]) > 254 ||
    strlen($request["password"]) > 72) {
    http_response_code(422);
    echo json_encode(["error" => "Enter a valid email address and password."]);
    exit;
}

$userEmail = strtolower(trim($request["email"]));
$userPassword = $request["password"];
if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL) || strlen($userPassword) < 12) {
    http_response_code(422);
    echo json_encode(["error" => "Enter a valid email address and password of at least 12 characters."]);
    exit;
}

try {
    require_once __DIR__ . "/db.php";
    require_once __DIR__ . "/customer_db.php";
    ensure_customer_table($conn);

    $statement = $conn->prepare(
        "SELECT user_id, full_name, password, role
         FROM users WHERE email = ? LIMIT 1"
    );
    $statement->bind_param("s", $userEmail);
    $statement->execute();
    $statement->bind_result($userId, $fullName, $passwordHash, $userRole);
    $accountFound = $statement->fetch();
    $passwordValid = $accountFound && password_verify($userPassword, $passwordHash);
    $statement->close();
    $conn->close();

    if (!$accountFound || !$passwordValid) {
        http_response_code(401);
        echo json_encode(["error" => "The email or password is incorrect."]);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION["user_id"] = (int)$userId;
    $_SESSION["user_name"] = $fullName;
    $_SESSION["user_email"] = $userEmail;
    $_SESSION["user_role"] = $userRole;
    unset($_SESSION["admin_id"]);
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    if ($userRole === "admin") {
        $redirect = "product.html";
    } elseif (($request["return_to"] ?? "") === "profile") {
        $redirect = "profile.html";
    } elseif (($request["return_to"] ?? "") === "product") {
        $redirect = "product.html";
    } else {
        $redirect = "home.html";
    }

    echo json_encode([
        "success" => true,
        "role" => $userRole,
        "redirect" => $redirect
    ]);
} catch (mysqli_sql_exception $error) {
    error_log("Account sign-in failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Sign-in is unavailable. Check the database connection."]);
} catch (RuntimeException $error) {
    error_log("Account sign-in schema check failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $error->getMessage()]);
}
?>
