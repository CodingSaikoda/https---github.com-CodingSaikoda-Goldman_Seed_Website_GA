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
require_admin();
require_csrf_token();

function remove_product_image(string $imagePath): void
{
    if (!preg_match('/^uploads\/products\/[a-f0-9]{32}\.jpg$/D', $imagePath)) {
        return;
    }

    $fullPath = __DIR__ . DIRECTORY_SEPARATOR .
        str_replace("/", DIRECTORY_SEPARATOR, $imagePath);
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

$requestBody = file_get_contents("php://input");
if ($requestBody === false || strlen($requestBody) > 2000000) {
    http_response_code(413);
    echo json_encode(["error" => "The submitted product data is too large."]);
    exit;
}

try {
    $request = json_decode($requestBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    http_response_code(400);
    echo json_encode(["error" => "The request body must be valid JSON."]);
    exit;
}

if (!is_array($request) || !isset($request["action"]) || !is_string($request["action"])) {
    http_response_code(400);
    echo json_encode(["error" => "A valid product action is required."]);
    exit;
}

try {
    require_once __DIR__ . "/db.php";

    if ($request["action"] === "create_category") {
        $categoryName = $request["category_name"] ?? null;
        if (!is_string($categoryName)) {
            http_response_code(422);
            echo json_encode(["error" => "Enter a valid category name."]);
            exit;
        }

        $categoryName = trim($categoryName);
        $categoryLength = function_exists("mb_strlen")
            ? mb_strlen($categoryName, "UTF-8")
            : strlen($categoryName);
        if ($categoryName === "" || $categoryLength > 300) {
            http_response_code(422);
            echo json_encode(["error" => "Category names must be between 1 and 300 characters."]);
            exit;
        }

        $duplicateCheck = $conn->prepare(
            "SELECT category_id
             FROM categories
             WHERE LOWER(TRIM(category_name)) = LOWER(?)
             LIMIT 1"
        );
        $duplicateCheck->bind_param("s", $categoryName);
        $duplicateCheck->execute();
        $duplicateCheck->store_result();
        if ($duplicateCheck->num_rows > 0) {
            $duplicateCheck->close();
            http_response_code(409);
            echo json_encode(["error" => "This category already exists."]);
            exit;
        }
        $duplicateCheck->close();

        $statement = $conn->prepare(
            "INSERT INTO categories (category_name) VALUES (?)"
        );
        $statement->bind_param("s", $categoryName);
        $statement->execute();
        $categoryId = (int)$conn->insert_id;
        $statement->close();
        $conn->close();

        echo json_encode([
            "success" => true,
            "category" => [
                "id" => $categoryId,
                "name" => $categoryName
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($request["action"] === "delete_category") {
        $categoryId = $request["category_id"] ?? null;
        if ((!is_string($categoryId) && !is_int($categoryId)) ||
            !ctype_digit((string)$categoryId) || (int)$categoryId < 1) {
            http_response_code(422);
            echo json_encode(["error" => "Choose a valid category to delete."]);
            exit;
        }
        $categoryId = (int)$categoryId;

        $categoryCheck = $conn->prepare(
            "SELECT category_name FROM categories WHERE category_id = ? LIMIT 1"
        );
        $categoryCheck->bind_param("i", $categoryId);
        $categoryCheck->execute();
        $categoryCheck->bind_result($categoryName);
        $categoryExists = $categoryCheck->fetch();
        $categoryCheck->close();
        if (!$categoryExists) {
            http_response_code(404);
            echo json_encode(["error" => "This category no longer exists. Refresh the list and try again."]);
            exit;
        }

        $productCheck = $conn->prepare(
            "SELECT COUNT(*) FROM products WHERE category_id = ?"
        );
        $productCheck->bind_param("i", $categoryId);
        $productCheck->execute();
        $productCheck->bind_result($productCount);
        $productCheck->fetch();
        $productCheck->close();
        if ((int)$productCount > 0) {
            http_response_code(409);
            echo json_encode([
                "error" => "This category is assigned to products. Reassign or remove those products before deleting it."
            ]);
            exit;
        }

        $statement = $conn->prepare(
            "DELETE FROM categories WHERE category_id = ?"
        );
        $statement->bind_param("i", $categoryId);
        $statement->execute();
        $statement->close();
        $conn->close();

        echo json_encode([
            "success" => true,
            "category_id" => $categoryId,
            "category_name" => $categoryName
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($request["action"] === "delete") {
        $deleteProduct = $request["product"] ?? null;
        $productId = $request["id"] ??
            (is_array($deleteProduct) ? ($deleteProduct["id"] ?? null) : null);
        if (!is_string($productId) && !is_int($productId)) {
            http_response_code(400);
            echo json_encode(["error" => "A valid product ID is required."]);
            exit;
        }

        $productId = (string)$productId;
        if (!ctype_digit($productId) || (int)$productId < 1) {
            http_response_code(400);
            echo json_encode(["error" => "A valid product ID is required."]);
            exit;
        }

        $selectStatement = $conn->prepare("SELECT image FROM products WHERE product_id = ?");
        $numericProductId = (int)$productId;
        $selectStatement->bind_param("i", $numericProductId);
        $selectStatement->execute();
        $selectStatement->bind_result($oldImage);
        $productFound = $selectStatement->fetch();
        $selectStatement->close();
        if (!$productFound) {
            http_response_code(404);
            echo json_encode(["error" => "The product was not found."]);
            exit;
        }

        $statement = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $statement->bind_param("i", $numericProductId);
        $statement->execute();
        $statement->close();
        remove_product_image((string)$oldImage);
        $conn->close();
        echo json_encode(["success" => true]);
        exit;
    }

    if (!in_array($request["action"], ["create", "update"], true) ||
        !isset($request["product"]) || !is_array($request["product"])) {
        http_response_code(400);
        echo json_encode(["error" => "A valid product action and product are required."]);
        exit;
    }

    $product = $request["product"];
    $name = $product["name"] ?? null;
    $category = $product["category"] ?? null;
    $description = $product["description"] ?? null;
    $unit = $product["unit"] ?? null;
    $priceInput = $product["price"] ?? null;
    $image = $product["image"] ?? "";

    if (!is_string($name) || !is_string($category) ||
        !is_string($description) || !is_string($unit) ||
        !is_string($image)) {
        http_response_code(422);
        echo json_encode(["error" => "Check the product name, category, description, price, unit, and image."]);
        exit;
    }

    $name = trim($name);
    $category = trim($category);
    $description = trim($description);
    $unit = trim($unit);

    $characterLength = static function (string $value): int {
        if (function_exists("mb_strlen")) {
            return mb_strlen($value, "UTF-8");
        }
        return strlen($value);
    };

    if ($name === "" || $characterLength($name) > 50 ||
        $category === "" || $characterLength($category) > 300 ||
        $description === "" || strlen($description) > 1200 ||
        $unit === "" || $characterLength($unit) > 200 ||
        (!is_string($priceInput) && !is_int($priceInput) && !is_float($priceInput)) ||
        !is_numeric($priceInput) || !is_finite((float)$priceInput) ||
        (float)$priceInput < 0 || (float)$priceInput > 99999999.99 ||
        strlen($image) > 1500000) {
        http_response_code(422);
        echo json_encode(["error" => "Check the product name, category, description, price, unit, and image."]);
        exit;
    }

    $productId = null;
    if ($request["action"] === "update") {
        $productId = $product["id"] ?? null;
        if ((!is_string($productId) && !is_int($productId)) ||
            !ctype_digit((string)$productId) || (int)$productId < 1) {
            http_response_code(400);
            echo json_encode(["error" => "A valid product ID is required."]);
            exit;
        }
        $productId = (int)$productId;
    }

    $isUploadedImage = preg_match('/^data:image\/jpeg;base64,[A-Za-z0-9+\/=]+$/D', $image) === 1;
    $validImage = $image === "" ||
        $isUploadedImage ||
        (filter_var($image, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $image)) ||
        preg_match('/^(?!\/{2})\/[A-Za-z0-9._~!$&\'()*+,;=:@%\/-]*$/D', $image) ||
        preg_match('/^(?!.*\.\.)[A-Za-z0-9._~!$&\'()*+,;=@%\/-]+$/D', $image);
    if (!$validImage) {
        http_response_code(422);
        echo json_encode(["error" => "Use a valid image URL, site path, or uploaded JPEG image."]);
        exit;
    }
    if (!$isUploadedImage && strlen($image) > 300) {
        http_response_code(422);
        echo json_encode(["error" => "Image URLs and paths must be 300 characters or fewer."]);
        exit;
    }

    $categoryStatement = $conn->prepare(
        "SELECT category_id FROM categories WHERE category_name = ? LIMIT 1"
    );
    $categoryStatement->bind_param("s", $category);
    $categoryStatement->execute();
    $categoryStatement->bind_result($categoryId);
    $categoryFound = $categoryStatement->fetch();
    $categoryStatement->close();

    if (!$categoryFound) {
        http_response_code(422);
        echo json_encode(["error" => "The selected category does not exist in the database."]);
        exit;
    }

    $newImagePath = null;
    if ($isUploadedImage) {
        $imageContents = base64_decode(substr($image, strlen("data:image/jpeg;base64,")), true);
        $imageInfo = $imageContents === false ? false : @getimagesizefromstring($imageContents);
        if ($imageContents === false || strlen($imageContents) > 1000000 ||
            $imageInfo === false || $imageInfo["mime"] !== "image/jpeg") {
            http_response_code(422);
            echo json_encode(["error" => "The uploaded image is not a valid JPEG or is too large."]);
            exit;
        }

        $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . "uploads" .
            DIRECTORY_SEPARATOR . "products";
        if (!is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException("Product image directory could not be created.");
        }

        $imageFilename = bin2hex(random_bytes(16)) . ".jpg";
        $newImagePath = "uploads/products/" . $imageFilename;
        $savedImage = file_put_contents(
            $uploadDirectory . DIRECTORY_SEPARATOR . $imageFilename,
            $imageContents,
            LOCK_EX
        );
        if ($savedImage === false) {
            throw new RuntimeException("Product image could not be saved.");
        }
        $image = $newImagePath;
    }

    $price = (string)$priceInput;
    if ($request["action"] === "create") {
        $statement = $conn->prepare(
            "INSERT INTO products (category_id, name, description, price, selling_unit, image)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $statement->bind_param("isssss", $categoryId, $name, $description, $price, $unit, $image);
        $statement->execute();
        $productId = $conn->insert_id;
        $statement->close();
    } else {
        $numericProductId = $productId;
        $oldImageStatement = $conn->prepare("SELECT image FROM products WHERE product_id = ?");
        $oldImageStatement->bind_param("i", $numericProductId);
        $oldImageStatement->execute();
        $oldImageStatement->bind_result($oldImage);
        $productExists = $oldImageStatement->fetch();
        $oldImageStatement->close();
        if (!$productExists) {
            if ($newImagePath !== null) {
                remove_product_image($newImagePath);
            }
            http_response_code(404);
            echo json_encode(["error" => "The product was not found."]);
            exit;
        }

        $statement = $conn->prepare(
            "UPDATE products
             SET category_id = ?, name = ?, description = ?, price = ?, selling_unit = ?, image = ?
             WHERE product_id = ?"
        );
        $statement->bind_param(
            "isssssi",
            $categoryId,
            $name,
            $description,
            $price,
            $unit,
            $image,
            $numericProductId
        );
        $statement->execute();
        $statement->close();
        if ($oldImage !== $image) {
            remove_product_image((string)$oldImage);
        }
    }

    $conn->close();
    echo json_encode(["success" => true, "id" => (string)$productId]);
} catch (RuntimeException $error) {
    if (isset($newImagePath) && is_string($newImagePath)) {
        remove_product_image($newImagePath);
    }
    error_log("Product image operation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "The product image could not be saved. Check server permissions and try again."]);
} catch (mysqli_sql_exception $error) {
    if (isset($newImagePath) && is_string($newImagePath)) {
        remove_product_image($newImagePath);
    }
    error_log("Product database operation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "The product could not be saved. Check the database schema and try again."]);
}
?>
