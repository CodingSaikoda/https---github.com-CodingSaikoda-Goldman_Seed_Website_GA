<?php
function ensure_customer_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS users (
            user_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone_no VARCHAR(30) NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'customer',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $columnResult = $conn->query("SHOW COLUMNS FROM users");
    $columns = [];
    while ($column = $columnResult->fetch_assoc()) {
        $columns[$column["Field"]] = $column;
    }

    if (!isset($columns["user_id"], $columns["full_name"], $columns["email"],
        $columns["password"], $columns["created_at"])) {
        throw new RuntimeException("The existing users table is missing a required account column.");
    }

    if (!isset($columns["phone_no"])) {
        $conn->query("ALTER TABLE users ADD phone_no VARCHAR(30) NULL");
    } elseif (strtolower($columns["phone_no"]["Type"]) !== "varchar(30)" ||
        $columns["phone_no"]["Null"] !== "YES") {
        $conn->query("ALTER TABLE users MODIFY phone_no VARCHAR(30) NULL");
    }

    if (!isset($columns["role"])) {
        $conn->query(
            "ALTER TABLE users
             ADD role VARCHAR(20) NOT NULL DEFAULT 'customer'"
        );
    }

    if (!isset($columns["avatar"])) {
        $conn->query(
            "ALTER TABLE users
             ADD avatar VARCHAR(30) NOT NULL DEFAULT 'farmer'"
        );
    } elseif (strtolower($columns["avatar"]["Type"]) !== "varchar(30)" ||
        $columns["avatar"]["Null"] !== "NO" ||
        $columns["avatar"]["Default"] !== "farmer") {
        $conn->query(
            "ALTER TABLE users
             MODIFY avatar VARCHAR(30) NOT NULL DEFAULT 'farmer'"
        );
    }

    if (strtolower($columns["email"]["Type"]) !== "varchar(254)" ||
        strtolower($columns["email"]["Collation"] ?? "") !== "ascii_general_ci") {
        $invalidEmailResult = $conn->query(
            "SELECT email FROM users
             WHERE CHAR_LENGTH(email) > 254 OR email REGEXP '[^ -~]'
             LIMIT 1"
        );
        if ($invalidEmailResult->num_rows > 0) {
            throw new RuntimeException(
                "Existing users.email values must be valid ASCII email addresses of 254 characters or fewer before migration."
            );
        }

        $conn->query(
            "ALTER TABLE users
             MODIFY email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL"
        );
    }

    $indexResult = $conn->query("SHOW INDEX FROM users");
    $hasUniqueEmailIndex = false;
    while ($index = $indexResult->fetch_assoc()) {
        if ($index["Key_name"] === "uq_users_email" &&
            (int)$index["Non_unique"] === 0 &&
            $index["Column_name"] === "email") {
            $hasUniqueEmailIndex = true;
            break;
        }
    }

    if (!$hasUniqueEmailIndex) {
        $duplicateResult = $conn->query(
            "SELECT LOWER(TRIM(email)) AS normalized_email
             FROM users
             GROUP BY normalized_email
             HAVING COUNT(*) > 1
             LIMIT 1"
        );
        if ($duplicateResult->num_rows > 0) {
            throw new RuntimeException(
                "Duplicate email addresses must be resolved before adding the unique users.email index."
            );
        }
        $conn->query("ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)");
    }
}

function customer_profile_avatar_options(): array
{
    return [
        "farmer" => "images/avatars/farmer.svg",
        "gardener" => "images/avatars/gardener.svg",
        "baker" => "images/avatars/baker.svg",
        "ranger" => "images/avatars/ranger.svg",
        "artist" => "images/avatars/artist.svg",
        "sunshine" => "images/avatars/sunshine.svg"
    ];
}
?>
