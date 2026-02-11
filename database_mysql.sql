-- MySQL Database Setup for Makerspace KKG

-- Create Database
CREATE DATABASE IF NOT EXISTS makerspace_kkg;
USE makerspace_kkg;

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL CHECK (role IN ('HEAD_ADMIN','INVENTORY_ADMIN')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(255) UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Inventory Locations Table
CREATE TABLE IF NOT EXISTS inventory_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT
);

-- Inventory Stock Table
CREATE TABLE IF NOT EXISTS inventory_stock (
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    qty_on_hand DECIMAL(10, 2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(10, 2),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, location_id),
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES inventory_locations (id) ON DELETE CASCADE
);

-- Admin Tasks Table
CREATE TABLE IF NOT EXISTS admin_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'OPEN' CHECK (status IN ('OPEN','IN_PROGRESS','DONE')),
    created_by_admin_id INT REFERENCES admins (id) ON DELETE SET NULL,
    assigned_to_admin_id INT REFERENCES admins (id) ON DELETE SET NULL,
    visible_to_role VARCHAR(50) NOT NULL DEFAULT 'HEAD_ADMIN' CHECK (visible_to_role IN ('HEAD_ADMIN','INVENTORY_ADMIN')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);

-- Inventory Transactions Table
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT,
    txn_type VARCHAR(50) NOT NULL CHECK (txn_type IN ('RECEIPT','ISSUE','ADJUSTMENT','TRANSFER_IN','TRANSFER_OUT')),
    qty_delta DECIMAL(10, 2) NOT NULL,
    reference TEXT,
    performed_by_admin_id INT REFERENCES admins (id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    FOREIGN KEY (location_id) REFERENCES inventory_locations (id) ON DELETE SET NULL
);

-- Reports Table
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_name VARCHAR(255),
    about_text TEXT,
    is_important BOOLEAN NOT NULL DEFAULT FALSE,
    status VARCHAR(50) NOT NULL DEFAULT 'NEW' CHECK (status IN ('NEW','IN_PROGRESS','RESOLVED')),
    handled_by_admin_id INT REFERENCES admins (id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);

-- Create Indexes
CREATE INDEX idx_inventory_stock_product ON inventory_stock (product_id);
CREATE INDEX idx_inventory_stock_location ON inventory_stock (location_id);
CREATE INDEX idx_admin_tasks_created_by ON admin_tasks (created_by_admin_id);
CREATE INDEX idx_admin_tasks_assigned_to ON admin_tasks (assigned_to_admin_id);

-- Insert Sample Products
INSERT INTO products (sku, name, description, unit, is_active) VALUES
('SKU-3DP-001', '3D-printer', 'Prusa i3 MK3S+ 3D Printer', 'unit', TRUE),
('SKU-LSR-001', 'Laser Cutter', 'CO2 Laser Cutter 40W', 'unit', TRUE),
('SKU-CNC-001', 'CNC Mill', 'CNC Milling Machine', 'unit', TRUE),
('SKU-SLD-001', 'Soldering Station', 'Digital Soldering Station', 'unit', TRUE),
('SKU-VIN-001', 'Vinyl Cutter', 'Vinyl Cutter 24" with Stand', 'unit', TRUE),
('SKU-ELC-001', 'Electronics Bench', 'Electronics Workbench with Tools', 'unit', TRUE);

-- Insert Sample Admin
INSERT INTO admins (full_name, email, password_hash, role, is_active) VALUES
('Admin User', 'admin@makerspace.local', '$2y$10$placeholder', 'HEAD_ADMIN', TRUE);

-- Insert Sample Inventory Location
INSERT INTO inventory_locations (name, description) VALUES
('Main Storage', 'Main storage area'),
('Workshop Floor', 'Active workspace');
