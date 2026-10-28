<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security.php";

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Sign in to use your saved basket."]);
    exit;
}

function get_user_cart_id(mysqli $conn, int $userId): int
{
    $statement = $conn->prepare("SELECT cart_id FROM cart WHERE user_id = ? LIMIT 1");
    $statement->bind_param("i", $userId);
    $statement->execute();
    $statement->bind_result($cartId);
    $found = $statement->fetch();
    $statement->close();

    if ($found) {
        return (int)$cartId;
    }

    $statement = $conn->prepare("INSERT IGNORE INTO cart (user_id) VALUES (?)");
    $statement->bind_param("i", $userId);
    $statement->execute();
    $statement->close();

    $statement = $conn->prepare("SELECT cart_id FROM cart WHERE user_id = ? LIMIT 1");
    $statement->bind_param("i", $userId);
    $statement->execute();
    $statement->bind_result($cartId);
    $found = $statement->fetch();
    $statement->close();
    if (!$found) {
        throw new RuntimeException("A saved basket could not be created.");
    }
    return (int)$cartId;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET" &&
    $_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: GET, POST");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_same_origin();
    require_csrf_token();

    $body = file_get_contents("php://input");
    if ($body === false || strlen($body) > 1000) {
        http_response_code(413);
        echo json_encode(["error" => "The basket request is too large."]);
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
        !in_array($request["action"] ?? null, ["add", "remove"], true)) {
        http_response_code(400);
        echo json_encode(["error" => "Choose a valid basket action."]);
        exit;
    }

    $productId = $request["product_id"] ?? null;
    if ((!is_string($productId) && !is_int($productId)) ||
        !ctype_digit((string)$productId) || (int)$productId < 1) {
        http_response_code(422);
        echo json_encode(["error" => "A valid product is required."]);
        exit;
    }
    $productId = (int)$productId;
}

try {
    require_once __DIR__ . "/db.php";
    require_once __DIR__ . "/customer_db.php";
    require_once __DIR__ . "/commerce_db.php";
    ensure_customer_table($conn);
    ensure_customer_commerce_schema($conn);

    $userId = (int)$_SESSION["user_id"];
    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $statement = $conn->prepare(
            "SELECT ci.product_id, ci.quantity
             FROM cart AS c
             INNER JOIN cart_item AS ci ON ci.cart_id = c.cart_id
             INNER JOIN products AS p ON p.product_id = ci.product_id
             WHERE c.user_id = ?
             ORDER BY ci.cart_item_id"
        );
        $statement->bind_param("i", $userId);
        $statement->execute();
        $statement->bind_result($productId, $quantity);
        $items = [];
        while ($statement->fetch()) {
            $items[] = [
                "productId" => (string)$productId,
                "quantity" => (int)$quantity
            ];
        }
        $statement->close();
        $conn->close();
        echo json_encode($items);
        exit;
    }

    $cartId = get_user_cart_id($conn, $userId);
    if ($request["action"] === "add") {
        $productCheck = $conn->prepare(
            "SELECT product_id FROM products WHERE product_id = ? LIMIT 1"
        );
        $productCheck->bind_param("i", $productId);
        $productCheck->execute();
        $productCheck->store_result();
        if ($productCheck->num_rows === 0) {
            $productCheck->close();
            http_response_code(404);
            echo json_encode(["error" => "This product is no longer available."]);
            exit;
        }
        $productCheck->close();

        $quantityCheck = $conn->prepare(
            "SELECT quantity FROM cart_item WHERE cart_id = ? AND product_id = ?"
        );
        $quantityCheck->bind_param("ii", $cartId, $productId);
        $quantityCheck->execute();
        $quantityCheck->bind_result($currentQuantity);
        $hasItem = $quantityCheck->fetch();
        $quantityCheck->close();
        if ($hasItem && (int)$currentQuantity >= 99) {
            http_response_code(422);
            echo json_encode(["error" => "You can add up to 99 of this product."]);
            exit;
        }

        $statement = $conn->prepare(
            "INSERT INTO cart_item (cart_id, product_id, quantity)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + 1, 99)"
        );
        $statement->bind_param("ii", $cartId, $productId);
        $statement->execute();
        $statement->close();
    } else {
        $statement = $conn->prepare(
            "DELETE ci FROM cart_item AS ci
             INNER JOIN cart AS c ON c.cart_id = ci.cart_id
             WHERE c.user_id = ? AND ci.product_id = ?"
        );
        $statement->bind_param("ii", $userId, $productId);
        $statement->execute();
        $statement->close();
    }

    $conn->close();
    echo json_encode(["success" => true]);
} catch (mysqli_sql_exception $error) {
    error_log("Customer basket operation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Your basket could not be updated. Try again."]);
} catch (RuntimeException $error) {
    error_log("Customer basket setup failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $error->getMessage()]);
}
?>
