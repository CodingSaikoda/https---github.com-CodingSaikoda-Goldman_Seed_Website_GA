<?php
ini_set("session.use_strict_mode", "1");
session_set_cookie_params([
    "httponly" => true,
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "samesite" => "Strict",
    "path" => "/"
]);
session_start();

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function require_admin(): void
{
    if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? null) !== "admin") {
        http_response_code(401);
        echo json_encode(["error" => "Administrator sign-in is required."]);
        exit;
    }
}

function require_csrf_token(): void
{
    $requestToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    if (!is_string($requestToken) ||
        !hash_equals($_SESSION["csrf_token"], $requestToken)) {
        http_response_code(403);
        echo json_encode(["error" => "Your session expired. Refresh the page and try again."]);
        exit;
    }
}

function require_same_origin(): void
{
    $origin = $_SERVER["HTTP_ORIGIN"] ?? "";
    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        ? "https"
        : "http";
    $expectedOrigin = $scheme . "://" . ($_SERVER["HTTP_HOST"] ?? "");

    if (!is_string($origin) || !hash_equals($expectedOrigin, $origin)) {
        http_response_code(403);
        echo json_encode(["error" => "This request is not allowed."]);
        exit;
    }
}
?>
