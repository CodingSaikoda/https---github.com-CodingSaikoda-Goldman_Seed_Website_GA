<?php
function ensure_customer_commerce_schema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cart (
            cart_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_cart_user (user_id),
            CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cart_item (
            cart_item_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            cart_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            UNIQUE KEY uq_cart_item_product (cart_id, product_id),
            CONSTRAINT fk_cart_item_cart FOREIGN KEY (cart_id) REFERENCES cart (cart_id),
            CONSTRAINT fk_cart_item_product FOREIGN KEY (product_id) REFERENCES products (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS customer_order (
            customer_order_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_customer_order_user (user_id),
            CONSTRAINT fk_customer_order_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS order_item (
            order_item_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            customer_order_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(50) NOT NULL,
            selling_unit VARCHAR(200) NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            KEY idx_order_item_order (customer_order_id),
            KEY idx_order_item_product (product_id),
            CONSTRAINT fk_order_item_order FOREIGN KEY (customer_order_id)
                REFERENCES customer_order (customer_order_id),
            CONSTRAINT fk_order_item_product FOREIGN KEY (product_id)
                REFERENCES products (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $cartDuplicates = $conn->query(
        "SELECT user_id FROM cart GROUP BY user_id HAVING COUNT(*) > 1 LIMIT 1"
    );
    if ($cartDuplicates->num_rows > 0) {
        throw new RuntimeException(
            "Multiple carts exist for a user. Resolve them before enabling persistent carts."
        );
    }
    $cartIndexes = $conn->query("SHOW INDEX FROM cart");
    $hasUniqueCartUser = false;
    while ($index = $cartIndexes->fetch_assoc()) {
        if ($index["Key_name"] === "uq_cart_user" &&
            (int)$index["Non_unique"] === 0 &&
            $index["Column_name"] === "user_id") {
            $hasUniqueCartUser = true;
            break;
        }
    }
    if (!$hasUniqueCartUser) {
        $conn->query("ALTER TABLE cart ADD UNIQUE KEY uq_cart_user (user_id)");
    }

    $itemDuplicates = $conn->query(
        "SELECT cart_id, product_id
         FROM cart_item
         GROUP BY cart_id, product_id
         HAVING COUNT(*) > 1
         LIMIT 1"
    );
    if ($itemDuplicates->num_rows > 0) {
        throw new RuntimeException(
            "Duplicate products exist in a cart. Resolve them before enabling persistent carts."
        );
    }
    $itemIndexes = $conn->query("SHOW INDEX FROM cart_item");
    $hasUniqueCartProduct = false;
    while ($index = $itemIndexes->fetch_assoc()) {
        if ($index["Key_name"] === "uq_cart_item_product" &&
            (int)$index["Non_unique"] === 0 &&
            $index["Column_name"] === "cart_id") {
            $hasUniqueCartProduct = true;
            break;
        }
    }
    if (!$hasUniqueCartProduct) {
        $conn->query(
            "ALTER TABLE cart_item
             ADD UNIQUE KEY uq_cart_item_product (cart_id, product_id)"
        );
    }

    $orderColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM customer_order");
    while ($column = $result->fetch_assoc()) {
        $orderColumns[$column["Field"]] = $column;
    }
    if (!isset($orderColumns["total_amount"])) {
        throw new RuntimeException("The customer_order table is missing total_amount.");
    }
    if (strtolower($orderColumns["total_amount"]["Type"]) !== "decimal(10,2)") {
        $conn->query(
            "ALTER TABLE customer_order
             MODIFY total_amount DECIMAL(10,2) NOT NULL"
        );
    }
    if (!isset($orderColumns["status"])) {
        $conn->query(
            "ALTER TABLE customer_order
             ADD status VARCHAR(30) NOT NULL DEFAULT 'pending'"
        );
    }
    if (!isset($orderColumns["created_at"])) {
        $conn->query(
            "ALTER TABLE customer_order
             ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
        );
    }

    $orderItemColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM order_item");
    while ($column = $result->fetch_assoc()) {
        $orderItemColumns[$column["Field"]] = $column;
    }
    if (!isset($orderItemColumns["unit_price"])) {
        throw new RuntimeException("The order_item table is missing unit_price.");
    }
    if (strtolower($orderItemColumns["unit_price"]["Type"]) !== "decimal(10,2)") {
        $conn->query(
            "ALTER TABLE order_item
             MODIFY unit_price DECIMAL(10,2) NOT NULL"
        );
    }
    if (!isset($orderItemColumns["product_name"])) {
        $conn->query(
            "ALTER TABLE order_item
             ADD product_name VARCHAR(50) NOT NULL DEFAULT ''"
        );
    }
    if (!isset($orderItemColumns["selling_unit"])) {
        $conn->query(
            "ALTER TABLE order_item
             ADD selling_unit VARCHAR(200) NOT NULL DEFAULT ''"
        );
    }
}
?>
