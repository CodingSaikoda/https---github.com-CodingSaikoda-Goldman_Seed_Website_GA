<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security.php";
require_admin();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

try {
    require_once __DIR__ . "/db.php";
    $result = $conn->query(
        "SELECT message_id, name, email, message, created_at
         FROM contact_messages
         ORDER BY created_at DESC, message_id DESC"
    );
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $conn->close();

    echo json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (mysqli_sql_exception $error) {
    error_log("Unable to load contact messages: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Contact messages could not be loaded."]);
}
?>
