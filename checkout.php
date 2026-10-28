<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Sign in before placing an order."]);
    exit;
}

require_same_origin();
require_csrf_token();

$body = file_get_contents("php://input");
if ($body === false || strlen($body) > 1000) {
    http_response_code(413);
    echo json_encode(["error" => "The checkout request is too large."]);
    exit;
}

try {
    $request = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    http_response_code(400);
    echo json_encode(["error" => "The request body must be valid JSON."]);
    exit;
}

if (!is_array($request)) {
    http_response_code(400);
    echo json_encode(["error" => "The checkout request is invalid."]);
    exit;
}

try {
    require_once __DIR__ . "/db.php";
    require_once __DIR__ . "/customer_db.php";
    require_once __DIR__ . "/commerce_db.php";
    ensure_customer_table($conn);
    ensure_customer_commerce_schema($conn);

    $userId = (int)$_SESSION["user_id"];
    $conn->begin_transaction();

    $cartStatement = $conn->prepare(
        "SELECT ci.product_id, ci.quantity, p.name, p.selling_unit, p.price
         FROM cart AS c
         INNER JOIN cart_item AS ci ON ci.cart_id = c.cart_id
         INNER JOIN products AS p ON p.product_id = ci.product_id
         WHERE c.user_id = ?
         FOR UPDATE"
    );
    $cartStatement->bind_param("i", $userId);
    $cartStatement->execute();
    $cartStatement->bind_result($productId, $quantity, $productName, $sellingUnit, $unitPrice);
    $items = [];
    $totalCents = 0;
    while ($cartStatement->fetch()) {
        $quantity = (int)$quantity;
        if ($quantity < 1 || $quantity > 99) {
            $cartStatement->close();
            $conn->rollback();
            http_response_code(422);
            echo json_encode(["error" => "Basket quantities must be between 1 and 99."]);
            exit;
        }

        $priceCents = (int)round((float)$unitPrice * 100);
        if ($priceCents < 0) {
            $cartStatement->close();
            $conn->rollback();
            http_response_code(422);
            echo json_encode(["error" => "A product in your basket has an invalid price."]);
            exit;
        }
        $lineTotalCents = $priceCents * $quantity;
        if ($lineTotalCents > 9999999999 ||
            $totalCents > 9999999999 - $lineTotalCents) {
            $cartStatement->close();
            $conn->rollback();
            http_response_code(422);
            echo json_encode(["error" => "The order total exceeds the supported amount."]);
            exit;
        }
        $totalCents += $lineTotalCents;
        $items[] = [
            "product_id" => (int)$productId,
            "product_name" => $productName,
            "selling_unit" => $sellingUnit,
            "quantity" => $quantity,
            "unit_price" => number_format($priceCents / 100, 2, ".", "")
        ];
    }
    $cartStatement->close();

    if (count($items) === 0) {
        $conn->rollback();
        http_response_code(422);
        echo json_encode(["error" => "Your basket is empty."]);
        exit;
    }

    $totalAmount = number_format($totalCents / 100, 2, ".", "");
    $status = "pending";
    $orderStatement = $conn->prepare(
        "INSERT INTO customer_order (user_id, total_amount, status, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    $orderStatement->bind_param("iss", $userId, $totalAmount, $status);
    $orderStatement->execute();
    $orderId = (int)$conn->insert_id;
    $orderStatement->close();

    $itemStatement = $conn->prepare(
        "INSERT INTO order_item
            (customer_order_id, product_id, product_name, selling_unit, quantity, unit_price)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    foreach ($items as $item) {
        $itemStatement->bind_param(
            "iissis",
            $orderId,
            $item["product_id"],
            $item["product_name"],
            $item["selling_unit"],
            $item["quantity"],
            $item["unit_price"]
        );
        $itemStatement->execute();
    }
    $itemStatement->close();

    $clearStatement = $conn->prepare(
        "DELETE ci FROM cart_item AS ci
         INNER JOIN cart AS c ON c.cart_id = ci.cart_id
         WHERE c.user_id = ?"
    );
    $clearStatement->bind_param("i", $userId);
    $clearStatement->execute();
    $clearStatement->close();

    $conn->commit();
    $conn->close();
    echo json_encode([
        "success" => true,
        "order_id" => $orderId,
        "total_amount" => $totalAmount,
        "status" => $status
    ]);
} catch (mysqli_sql_exception $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    error_log("Customer checkout failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Your order could not be created. Please try again."]);
} catch (RuntimeException $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    error_log("Customer checkout setup failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $error->getMessage()]);
}
?>
