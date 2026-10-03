-- =====================================================================
--  LavaLust Product Management System  -  Aiven MySQL setup script
--  Run this in your Aiven MySQL database (DBeaver / MySQL Workbench /
--  Aiven web console "Query"). Select your database (default: defaultdb)
--  BEFORE running.  It is safe to run more than once.
--
--  This is the SAME structure that "php lava migration run" creates,
--  so you can use EITHER this script OR the migration command.
-- =====================================================================

-- 1) migrations (tracks which migrations were applied)
CREATE TABLE IF NOT EXISTS `migrations` (
    `id`         INT      NOT NULL AUTO_INCREMENT,
    `migration`  INT      NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `migration_unique` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) users
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(100) NOT NULL,
    `email`      VARCHAR(255) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','moderator','user') NOT NULL DEFAULT 'user',
    `is_active`  TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username_unique` (`username`),
    KEY `email_idx` (`email`),
    KEY `role_idx` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) refresh_tokens
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token`      TEXT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `jti`        TEXT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) products  (required by Laboratory Exercise No. 6)
CREATE TABLE IF NOT EXISTS `products` (
    `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_name` VARCHAR(100)  NOT NULL,
    `description`  TEXT          NULL,
    `price`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    `quantity`     INT(11)       NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Mark migrations 000-003 as applied so "php lava migration run"
--    will not try to create the tables again.
INSERT IGNORE INTO `migrations` (`migration`) VALUES (0), (1), (2), (3);

-- 6) Demo login account  ->  email: admin@example.com   password: Admin@12345
--    (CHANGE THIS PASSWORD after your demo / screenshots)
INSERT INTO `users` (`username`, `email`, `password`, `role`)
SELECT 'admin', 'admin@example.com', '$2y$10$NJ1mdo0VOEGxqHil01SRyeSqgNyRnzrq7ROLcWQfzqiJdzPdkGOGa', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin@example.com');

-- 7) Sample products (optional)
INSERT INTO `products` (`product_name`, `description`, `price`, `quantity`)
SELECT * FROM (
    SELECT 'Wireless Mouse'   AS product_name, '2.4GHz ergonomic wireless mouse' AS description, 499.00 AS price, 25 AS quantity UNION ALL
    SELECT 'Mechanical Keyboard', 'Blue-switch mechanical keyboard, USB',          1899.50, 12 UNION ALL
    SELECT 'USB-C Hub',          '7-in-1 USB-C hub with HDMI and card reader',     1250.00, 8
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `products`);

-- 8) Verify
SHOW TABLES;
SELECT id, product_name, price, quantity, created_at FROM products;
