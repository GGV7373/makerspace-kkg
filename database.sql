/* Simple Inventory & Reporting schema derived from the ERD
	 - PostgreSQL-flavored DDL
*/

-- Admins
CREATE TABLE IF NOT EXISTS admins (
	id SERIAL PRIMARY KEY,
	full_name VARCHAR(255) NOT NULL,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	role VARCHAR(50) NOT NULL CHECK (role IN ('HEAD_ADMIN','INVENTORY_ADMIN')),
	is_active BOOLEAN NOT NULL DEFAULT TRUE,
	created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Products
CREATE TABLE IF NOT EXISTS products (
	id SERIAL PRIMARY KEY,
	sku VARCHAR(255) UNIQUE,
	name VARCHAR(255) NOT NULL,
	description TEXT,
	unit VARCHAR(50),
	is_active BOOLEAN NOT NULL DEFAULT TRUE,
	created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Admin tasks
CREATE TABLE IF NOT EXISTS admin_tasks (
	id SERIAL PRIMARY KEY,
	title VARCHAR(255) NOT NULL,
	description TEXT,
	status VARCHAR(50) NOT NULL DEFAULT 'OPEN' CHECK (status IN ('OPEN','IN_PROGRESS','DONE')),
	created_by_admin_id INTEGER REFERENCES admins (id) ON DELETE SET NULL,
	assigned_to_admin_id INTEGER REFERENCES admins (id) ON DELETE SET NULL,
	visible_to_role VARCHAR(50) NOT NULL DEFAULT 'HEAD_ADMIN' CHECK (visible_to_role IN ('HEAD_ADMIN','INVENTORY_ADMIN')),
	created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
	updated_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_admin_tasks_created_by ON admin_tasks (created_by_admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_tasks_assigned_to ON admin_tasks (assigned_to_admin_id);

-- Inventory locations
CREATE TABLE IF NOT EXISTS inventory_locations (
	id SERIAL PRIMARY KEY,
	name VARCHAR(255) NOT NULL UNIQUE,
	description TEXT
);

-- Inventory stock (composite PK: product + location)
CREATE TABLE IF NOT EXISTS inventory_stock (
	product_id INTEGER NOT NULL,
	location_id INTEGER NOT NULL,
	qty_on_hand NUMERIC NOT NULL DEFAULT 0,
	reorder_level NUMERIC,
	updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
	PRIMARY KEY (product_id, location_id),
	FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
	FOREIGN KEY (location_id) REFERENCES inventory_locations (id) ON DELETE CASCADE
);

-- Inventory transactions
CREATE TABLE IF NOT EXISTS inventory_transactions (
	id SERIAL PRIMARY KEY,
	product_id INTEGER NOT NULL REFERENCES products (id) ON DELETE RESTRICT,
	location_id INTEGER REFERENCES inventory_locations (id) ON DELETE SET NULL,
	txn_type VARCHAR(50) NOT NULL CHECK (txn_type IN ('RECEIPT','ISSUE','ADJUSTMENT','TRANSFER_IN','TRANSFER_OUT')),
	qty_delta NUMERIC NOT NULL,
	reference TEXT,
	performed_by_admin_id INTEGER REFERENCES admins (id) ON DELETE SET NULL,
	created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Reports
CREATE TABLE IF NOT EXISTS reports (
	id SERIAL PRIMARY KEY,
	reporter_name VARCHAR(255),
	about_text TEXT,
	is_important BOOLEAN NOT NULL DEFAULT FALSE,
	status VARCHAR(50) NOT NULL DEFAULT 'NEW' CHECK (status IN ('NEW','IN_PROGRESS','RESOLVED')),
	handled_by_admin_id INTEGER REFERENCES admins (id) ON DELETE SET NULL,
	created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
	updated_at TIMESTAMPTZ
);

-- Indexes to help lookups
CREATE INDEX IF NOT EXISTS idx_inventory_stock_product ON inventory_stock (product_id);
CREATE INDEX IF NOT EXISTS idx_inventory_stock_location ON inventory_stock (location_id);

