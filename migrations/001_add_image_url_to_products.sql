-- Migration: Add image_url column to products table for MySQL
-- This allows storing data URLs for product images (up to 4GB)

ALTER TABLE products ADD COLUMN image_url LONGTEXT NULL COLLATE utf8mb4_unicode_ci;
