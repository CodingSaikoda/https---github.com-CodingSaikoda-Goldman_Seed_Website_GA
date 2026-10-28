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

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), "", [
        "expires" => time() - 42000,
        "path" => $cookie["path"],
        "domain" => $cookie["domain"],
        "secure" => $cookie["secure"],
        "httponly" => $cookie["httponly"],
        "samesite" => "Strict"
    ]);
}
session_destroy();

echo json_encode(["success" => true]);
?>
