-- Add image_url column to products table
ALTER TABLE products ADD COLUMN image_url LONGTEXT;

-- Update sample products with image URLs (base64 encoded or external URLs)
-- If you want to use external URLs instead, use this:
-- UPDATE products SET image_url = 'https://via.placeholder.com/320x220?text=3D-printer' WHERE id = 1;
