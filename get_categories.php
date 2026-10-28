<?php
header("Content-Type: application/json; charset=utf-8");

try {
    require_once __DIR__ . "/db.php";
    $result = $conn->query(
        "SELECT category_id AS id, category_name AS name
         FROM categories
         ORDER BY category_name"
    );
    $categories = [];

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }

    echo json_encode(
        $categories,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Throwable $error) {
    error_log("Unable to load product categories: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Unable to load product categories."]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
