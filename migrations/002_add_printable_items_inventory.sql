

CREATE TABLE IF NOT EXISTS printable_inventory (
	id INT AUTO_INCREMENT PRIMARY KEY,
	item_id INT NOT NULL,
	size VARCHAR(50),
	color VARCHAR(100),
	quantity INT NOT NULL DEFAULT 0,
	reorder_level INT DEFAULT 10,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	updated_by_admin_id INT,
	UNIQUE KEY unique_item_size_color (item_id, size, color),
	FOREIGN KEY (item_id) REFERENCES printable_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS printable_transactions (
	id INT AUTO_INCREMENT PRIMARY KEY,
	item_id INT NOT NULL,
	size VARCHAR(50),
	color VARCHAR(100),
	qty_change INT NOT NULL,
	reason VARCHAR(100),
	notes TEXT,
	performed_by_admin_id INT,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (item_id) REFERENCES printable_items(id) ON DELETE CASCADE,
	INDEX idx_printable_transactions_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_printable_inventory_item ON printable_inventory(item_id);
