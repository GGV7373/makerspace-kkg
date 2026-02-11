# Backend Setup Guide

## Installation Steps

### 1. Set Up MySQL Database

1. Open MySQL command line or phpMyAdmin
2. Execute the SQL from `database_mysql.sql`:
   ```sql
   source database_mysql.sql;
   ```
   OR copy and paste the contents into your MySQL client

This will create:
- Database: `makerspace_kkg`
- Tables: `products`, `admins`, `inventory_locations`, `inventory_stock`, etc.
- Sample products and admin user

### 2. Configure Database Connection

Edit `config.php` with your database credentials:

```php
define('DB_HOST', 'localhost');      // MySQL host
define('DB_USER', 'root');           // MySQL username
define('DB_PASS', '');               // MySQL password
define('DB_NAME', 'makerspace_kkg'); // Database name
```

### 3. Start PHP Server

From the project root directory, run:

```bash
php -S localhost:8000
```

This starts a local development server on `http://localhost:8000`

### 4. Verify API Endpoint

Open browser and test the API:
- http://localhost:8000/api/products.php

You should get a JSON response with all products from the database.

## File Structure

```
makerspace-kkg/
├── index.html              # Main page
├── index.js                # Frontend logic (now fetches from API)
├── config.php              # Database configuration
├── database_mysql.sql      # Database setup script
└── api/
    └── products.php        # API endpoint for products
```

## How It Works

1. **Frontend** (`index.js`): 
   - Calls `loadProducts()` on page load
   - Fetches from `api/products.php`
   - Renders products from database

2. **Backend** (`api/products.php`):
   - Connects to MySQL via `config.php`
   - Queries `products` table
   - Returns JSON array of products

3. **Database** (`database_mysql.sql`):
   - Stores product information
   - Supports admin accounts, inventory tracking, and reporting

## Adding More Products

To add products to the database, insert them into the `products` table:

```sql
INSERT INTO products (sku, name, description, unit, is_active) VALUES
('SKU-XXX-001', 'Product Name', 'Description', 'unit', TRUE);
```

They will automatically appear on the website after a page refresh.

## Troubleshooting

**Products not showing?**
- Check PHP server is running
- Check database credentials in `config.php`
- Check MySQL database is created and has data
- Open browser console for errors

**API returning error?**
- Verify `api/products.php` endpoint exists
- Check database connection in `config.php`
- Check PHP error logs

**CORS issues?**
- The `config.php` already has CORS headers enabled for development
