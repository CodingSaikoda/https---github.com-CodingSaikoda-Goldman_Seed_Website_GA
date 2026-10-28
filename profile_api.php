<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security.php";
require_once __DIR__ . "/customer_db.php";

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Sign in to view your account profile."]);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];
if ($method !== "GET" && $method !== "POST") {
    http_response_code(405);
    header("Allow: GET, POST");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

if ($method === "POST") {
    require_same_origin();
    require_csrf_token();

    $body = file_get_contents("php://input");
    if ($body === false || strlen($body) > 5000) {
        http_response_code(413);
        echo json_encode(["error" => "The profile details are too large."]);
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
        !is_string($request["full_name"] ?? null) ||
        !is_string($request["phone_no"] ?? null) ||
        !is_string($request["avatar"] ?? null)) {
        http_response_code(422);
        echo json_encode(["error" => "Enter a valid name, phone number, and avatar."]);
        exit;
    }

    $fullName = trim($request["full_name"]);
    $phoneNo = trim($request["phone_no"]);
    $nameLength = function_exists("mb_strlen")
        ? mb_strlen($fullName, "UTF-8")
        : strlen($fullName);
    $phoneLength = function_exists("mb_strlen")
        ? mb_strlen($phoneNo, "UTF-8")
        : strlen($phoneNo);

    if ($fullName === "" || $nameLength > 120 ||
        $phoneLength > 30 ||
        ($phoneNo !== "" && !preg_match('/^[0-9+().\-\s]+$/D', $phoneNo))) {
        http_response_code(422);
        echo json_encode(["error" => "Name must be 1–120 characters. Enter a valid phone number of up to 30 characters."]);
        exit;
    }

    $avatarOptions = customer_profile_avatar_options();
    $avatar = $request["avatar"];
    if (!isset($avatarOptions[$avatar])) {
        http_response_code(422);
        echo json_encode(["error" => "Choose one of the available profile pictures."]);
        exit;
    }
}

try {
    require_once __DIR__ . "/db.php";
    require_once __DIR__ . "/customer_db.php";
    ensure_customer_table($conn);

    $userId = (int)$_SESSION["user_id"];
    if ($method === "GET") {
        $statement = $conn->prepare(
            "SELECT full_name, email, phone_no, avatar
             FROM users
             WHERE user_id = ?
             LIMIT 1"
        );
        $statement->bind_param("i", $userId);
        $statement->execute();
        $statement->bind_result($fullName, $email, $phoneNo, $avatar);
        if (!$statement->fetch()) {
            $statement->close();
            http_response_code(404);
            echo json_encode(["error" => "The account could not be found."]);
            exit;
        }
        $statement->close();

        $avatarOptions = customer_profile_avatar_options();
        if (!isset($avatarOptions[$avatar])) {
            $avatar = "farmer";
        }
        $conn->close();

        echo json_encode([
            "full_name" => $fullName,
            "email" => $email,
            "phone_no" => $phoneNo ?? "",
            "avatar" => $avatar,
            "avatar_options" => $avatarOptions,
            "csrf_token" => $_SESSION["csrf_token"]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $statement = $conn->prepare(
        "UPDATE users
         SET full_name = ?, phone_no = ?, avatar = ?
         WHERE user_id = ?"
    );
    $statement->bind_param("sssi", $fullName, $phoneNo, $avatar, $userId);
    $statement->execute();
    $statement->close();
    $conn->close();

    $_SESSION["user_name"] = $fullName;
    $_SESSION["user_avatar"] = $avatar;
    echo json_encode([
        "success" => true,
        "full_name" => $fullName,
        "phone_no" => $phoneNo,
        "avatar" => $avatar,
        "avatar_url" => $avatarOptions[$avatar]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (mysqli_sql_exception $error) {
    error_log("Customer profile operation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "The profile could not be saved. Check the database connection."]);
} catch (RuntimeException $error) {
    error_log("Customer profile schema check failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $error->getMessage()]);
}
?>
