<?php
header("Content-Type: application/json; charset=utf-8");

try {
    require_once __DIR__ . "/db.php";

    $sql = "SELECT p.product_id AS id,
                   c.category_name AS category,
                   p.name,
                   p.description,
                   p.price,
                   p.selling_unit AS unit,
                   p.image
            FROM products AS p
            LEFT JOIN categories AS c ON c.category_id = p.category_id
            ORDER BY p.product_id DESC";

    $result = $conn->query($sql);
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    echo json_encode(
        $products,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(
        ["error" => "Unable to load products from the database."],
        JSON_UNESCAPED_UNICODE
    );
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
