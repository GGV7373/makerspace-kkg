-- Migration: create plotter orders table
CREATE TABLE IF NOT EXISTS plotter_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    requester_admin_id INT NULL,
    contact_email VARCHAR(255) NULL,
    width_mm INT NOT NULL,
    height_mm INT NOT NULL,
    material VARCHAR(100) DEFAULT 'paper',
    copies INT NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'NEW',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_plotter_orders_product (product_id),
    INDEX idx_plotter_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
