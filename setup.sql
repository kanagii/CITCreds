-- =====================================================
--  Canteen System — Database Setup
--  Database: citcreds1
--  Run this in phpMyAdmin or MySQL CLI
-- =====================================================

USE citcreds1;

-- ── USERS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    user_type   ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    balance     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── PRODUCT ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product (
    product_id  INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock       INT           NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── TRANSACTION ────────────────────────────────────
CREATE TABLE IF NOT EXISTS transaction (
    transaction_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT           NOT NULL,
    processed_by        INT           NOT NULL DEFAULT 1,
    total_amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    transaction_date    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ── TRANSACTION ITEM ───────────────────────────────
CREATE TABLE IF NOT EXISTS transaction_item (
    item_id         INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id  INT           NOT NULL,
    product_id      INT           NOT NULL,
    quantity        INT           NOT NULL DEFAULT 1,
    subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (transaction_id) REFERENCES transaction(transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)     REFERENCES product(product_id)         ON DELETE CASCADE
);

-- ── SAMPLE DATA (optional, delete if not needed) ───
INSERT INTO users (name, user_type, balance) VALUES
    ('Ana Reyes',   'student', 500.00),
    ('Ben Santos',  'student', 320.50),
    ('Maria Cruz',  'teacher', 1200.00),
    ('Jose Lim',    'student', 150.00);

INSERT INTO product (name, price, stock) VALUES
    ('Fried Rice',     45.00, 20),
    ('Chicken Adobo',  65.00, 15),
    ('Soda (Regular)', 20.00, 50),
    ('Pancit Canton',  55.00,  8),
    ('Buko Juice',     25.00, 30);
