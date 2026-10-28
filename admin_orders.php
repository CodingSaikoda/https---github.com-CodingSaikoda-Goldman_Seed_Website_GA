<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security.php";
require_admin();

if ($_SERVER["REQUEST_METHOD"] !== "GET" && $_SERVER["REQUEST_METHOD"] !== "DELETE") {
    http_response_code(405);
    header("Allow: GET, DELETE");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    require_same_origin();
    require_csrf_token();

    $requestBody = file_get_contents("php://input");
    if ($requestBody === false || strlen($requestBody) > 1000) {
        http_response_code(413);
        echo json_encode(["error" => "The order request is too large."]);
        exit;
    }

    try {
        $request = json_decode($requestBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        http_response_code(400);
        echo json_encode(["error" => "The request body must be valid JSON."]);
        exit;
    }

    $orderId = $request["order_id"] ?? null;
    if ((!is_string($orderId) && !is_int($orderId)) ||
        !ctype_digit((string)$orderId) || (int)$orderId < 1) {
        http_response_code(422);
        echo json_encode(["error" => "A valid order is required."]);
        exit;
    }
    $orderId = (int)$orderId;
}

try {
    require_once __DIR__ . "/db.php";

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $result = $conn->query(
            "SELECT o.customer_order_id, o.user_id, u.full_name, u.email,
                    o.total_amount, o.status, o.created_at,
                    oi.product_id, oi.product_name, oi.selling_unit,
                    oi.quantity, oi.unit_price
             FROM customer_order AS o
             INNER JOIN users AS u ON u.user_id = o.user_id
             LEFT JOIN order_item AS oi ON oi.customer_order_id = o.customer_order_id
             ORDER BY o.created_at DESC, o.customer_order_id DESC, oi.order_item_id"
        );

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $id = (int)$row["customer_order_id"];
            if (!isset($orders[$id])) {
                $orders[$id] = [
                    "order_id" => $id,
                    "user_id" => (int)$row["user_id"],
                    "customer_name" => $row["full_name"],
                    "customer_email" => $row["email"],
                    "total_amount" => $row["total_amount"],
                    "status" => $row["status"],
                    "created_at" => $row["created_at"],
                    "items" => []
                ];
            }
            if ($row["product_id"] !== null) {
                $orders[$id]["items"][] = [
                    "product_name" => $row["product_name"],
                    "selling_unit" => $row["selling_unit"],
                    "quantity" => (int)$row["quantity"],
                    "unit_price" => $row["unit_price"]
                ];
            }
        }
        $conn->close();
        echo json_encode(array_values($orders), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $conn->begin_transaction();
    $check = $conn->prepare(
        "SELECT customer_order_id FROM customer_order WHERE customer_order_id = ? FOR UPDATE"
    );
    $check->bind_param("i", $orderId);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        $check->close();
        $conn->rollback();
        http_response_code(404);
        echo json_encode(["error" => "The order was not found."]);
        exit;
    }
    $check->close();

    $deleteItems = $conn->prepare(
        "DELETE FROM order_item WHERE customer_order_id = ?"
    );
    $deleteItems->bind_param("i", $orderId);
    $deleteItems->execute();
    $deleteItems->close();

    $deleteOrder = $conn->prepare(
        "DELETE FROM customer_order WHERE customer_order_id = ?"
    );
    $deleteOrder->bind_param("i", $orderId);
    $deleteOrder->execute();
    $deleteOrder->close();

    $conn->commit();
    $conn->close();
    echo json_encode(["success" => true]);
} catch (mysqli_sql_exception $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    error_log("Admin order operation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "The order could not be processed."]);
}
?>
