<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

try {
    require_once __DIR__ . "/db.php";
    require_once __DIR__ . "/customer_db.php";
    ensure_customer_table($conn);

    $account = null;
    if (isset($_SESSION["user_id"])) {
        $statement = $conn->prepare(
            "SELECT full_name, email, phone_no, avatar
             FROM users
             WHERE user_id = ?
             LIMIT 1"
        );
        $userId = (int)$_SESSION["user_id"];
        $statement->bind_param("i", $userId);
        $statement->execute();
        $statement->bind_result($fullName, $email, $phoneNo, $avatar);
        if ($statement->fetch()) {
            $avatarOptions = customer_profile_avatar_options();
            if (!isset($avatarOptions[$avatar])) {
                $avatar = "farmer";
            }
            $_SESSION["user_name"] = $fullName;
            $_SESSION["user_email"] = $email;
            $_SESSION["user_avatar"] = $avatar;
            $account = [
                "full_name" => $fullName,
                "email" => $email,
                "phone_no" => $phoneNo,
                "avatar" => $avatar,
                "avatar_url" => $avatarOptions[$avatar]
            ];
        } else {
            unset(
                $_SESSION["user_id"],
                $_SESSION["user_name"],
                $_SESSION["user_email"],
                $_SESSION["user_role"],
                $_SESSION["user_avatar"]
            );
        }
        $statement->close();
    }

    $conn->close();

    echo json_encode([
        "authenticated" => isset($_SESSION["user_id"]),
        "role" => $_SESSION["user_role"] ?? null,
        "full_name" => $account["full_name"] ?? null,
        "email" => $account["email"] ?? null,
        "phone_no" => $account["phone_no"] ?? null,
        "avatar" => $account["avatar"] ?? null,
        "avatar_url" => $account["avatar_url"] ?? null,
        "csrf_token" => $_SESSION["csrf_token"]
    ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $error) {
    error_log("Unable to initialize customer accounts: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Customer accounts are unavailable. Check the database connection."]);
} catch (RuntimeException $error) {
    error_log("Customer account table migration failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $error->getMessage()]);
}
?>
