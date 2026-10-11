-- InCart Database SQL Schema and Seed Data
CREATE DATABASE IF NOT EXISTS `incart_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `incart_db`;

-- Drop tables if exist in reverse dependency order
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `deliveries`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `wishlist_items`;
DROP TABLE IF EXISTS `wishlists`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `brands`;
DROP TABLE IF EXISTS `subcategories`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- 1. USERS TABLE
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'customer') DEFAULT 'customer',
  `address` TEXT NULL,
  `city` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (`email`),
  INDEX idx_role (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. CATEGORIES TABLE
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `image` VARCHAR(255) DEFAULT 'assets/images/categories/default.jpg',
  `icon` VARCHAR(50) DEFAULT 'fa-shopping-basket',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. SUBCATEGORIES TABLE
CREATE TABLE `subcategories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  INDEX idx_cat (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. BRANDS TABLE
CREATE TABLE `brands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `logo` VARCHAR(255) DEFAULT 'assets/images/brands/default.png',
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. PRODUCTS TABLE
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `subcategory_id` INT NOT NULL,
  `brand_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `short_description` VARCHAR(255),
  `price` DECIMAL(10,2) NOT NULL,
  `discount_price` DECIMAL(10,2) DEFAULT NULL,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `unit` VARCHAR(20) DEFAULT '1 pack',
  `weight` VARCHAR(30) DEFAULT '500g',
  `country_of_origin` VARCHAR(50) DEFAULT 'Sri Lanka',
  `rating` DECIMAL(3,2) DEFAULT 5.00,
  `review_count` INT DEFAULT 0,
  `featured` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE CASCADE,
  INDEX idx_prod_name (`name`),
  INDEX idx_price (`price`),
  INDEX idx_featured (`featured`),
  INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. PRODUCT IMAGES TABLE
CREATE TABLE `product_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. CARTS TABLE
CREATE TABLE `carts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `session_id` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. CART ITEMS TABLE
CREATE TABLE `cart_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cart_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. WISHLISTS TABLE
CREATE TABLE `wishlists` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. WISHLIST ITEMS TABLE
CREATE TABLE `wishlist_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `wishlist_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. ORDERS TABLE
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `delivery_fee` DECIMAL(10,2) DEFAULT 350.00,
  `grand_total` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'Cash on Delivery',
  `payment_status` ENUM('Pending', 'Paid', 'Failed') DEFAULT 'Pending',
  `order_status` ENUM('Pending', 'Confirmed', 'Processing', 'Out for Delivery', 'Delivered', 'Cancelled') DEFAULT 'Pending',
  `shipping_first_name` VARCHAR(50) NOT NULL,
  `shipping_last_name` VARCHAR(50) NOT NULL,
  `shipping_email` VARCHAR(100) NOT NULL,
  `shipping_phone` VARCHAR(20) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `shipping_city` VARCHAR(50) NOT NULL,
  `shipping_postal_code` VARCHAR(20) NULL,
  `delivery_type` ENUM('Standard', 'Express') DEFAULT 'Standard',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX idx_order_num (`order_number`),
  INDEX idx_order_user (`user_id`),
  INDEX idx_order_status (`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. ORDER ITEMS TABLE
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `quantity` INT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. PAYMENTS TABLE
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `transaction_id` VARCHAR(100) NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` VARCHAR(20) DEFAULT 'Completed',
  `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. DELIVERIES TABLE
CREATE TABLE `deliveries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `delivery_type` VARCHAR(30) DEFAULT 'Standard',
  `tracking_number` VARCHAR(50) NOT NULL UNIQUE,
  `status` ENUM('Pending', 'Confirmed', 'Processing', 'Out for Delivery', 'Delivered') DEFAULT 'Pending',
  `estimated_delivery` VARCHAR(50) DEFAULT '1-2 Days',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. REVIEWS TABLE
CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `rating` INT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT NOT NULL,
  `is_approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. CONTACT MESSAGES TABLE
CREATE TABLE `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- SEED DATA
-- ========================================================

-- Seed Admin and Customer
INSERT INTO `users` (`first_name`, `last_name`, `email`, `phone`, `password`, `role`, `address`, `city`) VALUES
('InCart', 'Admin', 'admin@incart.lk', '+94771234567', '$2y$10$XywsJfXkEEySy2OZxlVGeOuLz8OaVMQSAoMoU3L.nHg5t2J2wCLwq', 'admin', 'Headquarters, Colombo 03', 'Colombo'),
('Kasun', 'Perera', 'customer@incart.lk', '+94779876543', '$2y$10$JMCSTB58h5YJoH6iFS878.7s9atMIssg8yUUJcGRk9k4d43CTeH3K', 'customer', '45 Galle Road', 'Colombo');

-- Seed Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `image`, `icon`) VALUES
(1, 'Fresh Produce', 'fresh-produce', 'assets/images/categories/fresh-produce.jpg', 'fa-apple-alt'),
(2, 'Bakery', 'bakery', 'assets/images/categories/bakery.jpg', 'fa-bread-slice'),
(3, 'Beverages', 'beverages', 'assets/images/categories/beverages.jpg', 'fa-coffee'),
(4, 'Dairy & Eggs', 'dairy-eggs', 'assets/images/categories/dairy-eggs.jpg', 'fa-egg'),
(5, 'Frozen Foods', 'frozen-foods', 'assets/images/categories/frozen-foods.jpg', 'fa-snowflake'),
(6, 'Snacks', 'snacks', 'assets/images/categories/snacks.jpg', 'fa-cookie'),
(7, 'Household Essentials', 'household-essentials', 'assets/images/categories/household.jpg', 'fa-broom'),
(8, 'Personal Care', 'personal-care', 'assets/images/categories/personal-care.jpg', 'fa-pump-soap');

-- Seed Subcategories
INSERT INTO `subcategories` (`id`, `category_id`, `name`, `slug`) VALUES
-- Fresh Produce (1)
(1, 1, 'Fruits', 'fruits'),
(2, 1, 'Vegetables', 'vegetables'),
(3, 1, 'Herbs', 'herbs'),
-- Bakery (2)
(4, 2, 'Bread', 'bread'),
(5, 2, 'Cakes', 'cakes'),
(6, 2, 'Buns', 'buns'),
(7, 2, 'Pastries', 'pastries'),
-- Beverages (3)
(8, 3, 'Soft Drinks', 'soft-drinks'),
(9, 3, 'Juices', 'juices'),
(10, 3, 'Tea', 'tea'),
(11, 3, 'Coffee', 'coffee'),
(12, 3, 'Water', 'water'),
-- Dairy & Eggs (4)
(13, 4, 'Milk', 'milk'),
(14, 4, 'Cheese', 'cheese'),
(15, 4, 'Butter', 'butter'),
(16, 4, 'Yogurt', 'yogurt'),
(17, 4, 'Eggs', 'eggs'),
-- Frozen Foods (5)
(18, 5, 'Frozen Vegetables', 'frozen-vegetables'),
(19, 5, 'Frozen Meat', 'frozen-meat'),
(20, 5, 'Ice Cream', 'ice-cream'),
(21, 5, 'Frozen Snacks', 'frozen-snacks'),
-- Snacks (6)
(22, 6, 'Biscuits', 'biscuits'),
(23, 6, 'Chocolates', 'chocolates'),
(24, 6, 'Chips', 'chips'),
(25, 6, 'Nuts', 'nuts'),
-- Household (7)
(26, 7, 'Cleaning', 'cleaning'),
(27, 7, 'Kitchen', 'kitchen'),
(28, 7, 'Laundry', 'laundry'),
-- Personal Care (8)
(29, 8, 'Hair Care', 'hair-care'),
(30, 8, 'Skin Care', 'skin-care'),
(31, 8, 'Oral Care', 'oral-care');

-- Seed Brands
INSERT INTO `brands` (`id`, `name`) VALUES
(1, 'Ceylon Fresh'),
(2, 'Elephant House'),
(3, 'Maliban'),
(4, 'Anchor'),
(5, 'Highland'),
(6, 'Nestle'),
(7, 'Munchi'),
(8, 'Unilever'),
(9, 'Sunlight'),
(10, 'Keells Choice');

-- Seed Products (60+ Products with realistic descriptions, prices in LKR)
INSERT INTO `products` (`id`, `category_id`, `subcategory_id`, `brand_id`, `name`, `slug`, `sku`, `description`, `short_description`, `price`, `discount_price`, `stock_quantity`, `unit`, `weight`, `country_of_origin`, `rating`, `review_count`, `featured`) VALUES
-- Fresh Produce -> Fruits
(1, 1, 1, 1, 'Fresh Red Apples', 'fresh-red-apples', 'FRU-001', 'Crisp, sweet, and juicy red crisp apples freshly harvested.', 'Crisp and juicy sweet red apples.', 480.00, 420.00, 50, '1 kg', '1000g', 'Sri Lanka', 4.80, 12, 1),
(2, 1, 1, 1, 'Organic Cavendish Bananas', 'organic-cavendish-bananas', 'FRU-002', 'Rich in potassium and natural sweetness. Grown organically.', 'Sweet organic ripe yellow bananas.', 250.00, 220.00, 80, '1 comb', '1000g', 'Sri Lanka', 4.90, 8, 1),
(3, 1, 1, 1, 'Fresh Juicy Oranges', 'fresh-juicy-oranges', 'FRU-003', 'Vitamin C packed sweet imported oranges ideal for snacking and juice.', 'Sweet citrus juicy oranges.', 380.00, NULL, 40, '500g', '500g', 'South Africa', 4.50, 5, 0),
(4, 1, 1, 1, 'Ripe Yellow Mangoes', 'ripe-yellow-mangoes', 'FRU-004', 'Deliciously sweet local Willard mangoes.', 'Sweet and aromatic tropical mangoes.', 320.00, 280.00, 35, '1 kg', '1000g', 'Sri Lanka', 4.95, 15, 1),
(5, 1, 1, 1, 'Fresh Avocado', 'fresh-avocado', 'FRU-005', 'Creamy butter avocados perfect for salads and smoothies.', 'Creamy rich butter avocados.', 450.00, 390.00, 25, '500g', '500g', 'Sri Lanka', 4.70, 7, 0),

-- Fresh Produce -> Vegetables
(6, 1, 2, 1, 'Fresh Red Tomatoes', 'fresh-red-tomatoes', 'VEG-001', 'Farm fresh ripe red tomatoes bursting with flavor.', 'Ripe red juicy farm tomatoes.', 280.00, 240.00, 60, '500g', '500g', 'Sri Lanka', 4.60, 9, 1),
(7, 1, 2, 1, 'Crisp Orange Carrots', 'crisp-orange-carrots', 'VEG-002', 'Freshly pulled organic orange carrots.', 'Crunchy sweet orange carrots.', 320.00, 290.00, 45, '500g', '500g', 'Sri Lanka', 4.80, 4, 0),
(8, 1, 2, 1, 'Local Potatoes', 'local-potatoes', 'VEG-003', 'Nuwara Eliya premium baking and cooking potatoes.', 'Fresh local Nuwara Eliya potatoes.', 350.00, NULL, 100, '1 kg', '1000g', 'Sri Lanka', 4.75, 11, 0),
(9, 1, 2, 1, 'Red Onions', 'red-onions', 'VEG-004', 'Flavorful aromatic red onions for daily cooking.', 'Fresh aromatic red onions.', 420.00, 380.00, 75, '1 kg', '1000g', 'Sri Lanka', 4.65, 6, 0),
(10, 1, 2, 1, 'Green Capsicum', 'green-capsicum', 'VEG-005', 'Fresh crunchy bell peppers for stir fries and salads.', 'Crunchy green bell peppers.', 490.00, 440.00, 30, '500g', '500g', 'Sri Lanka', 4.85, 3, 0),

-- Fresh Produce -> Herbs
(11, 1, 3, 1, 'Fresh Coriander / Cilantro Leaves', 'fresh-coriander-leaves', 'HRB-001', 'Aromatic fresh green coriander leaves.', 'Fresh aromatic cilantro bunch.', 120.00, 99.00, 40, '1 bunch', '100g', 'Sri Lanka', 4.70, 2, 0),
(12, 1, 3, 1, 'Fresh Mint Leaves', 'fresh-mint-leaves', 'HRB-002', 'Refreshing mint leaves for teas, drinks, and garnishing.', 'Fragrant green mint leaves.', 110.00, NULL, 30, '1 bunch', '100g', 'Sri Lanka', 4.60, 4, 0),

-- Bakery -> Bread
(13, 2, 4, 10, 'Fresh Sandwich White Bread', 'fresh-sandwich-white-bread', 'BRD-001', 'Soft and fluffy daily baked white bread loaf.', 'Soft daily baked white sandwich loaf.', 220.00, 199.00, 40, '1 loaf', '450g', 'Sri Lanka', 4.85, 18, 1),
(14, 2, 4, 10, 'Whole Wheat Brown Bread', 'whole-wheat-brown-bread', 'BRD-002', 'High fiber nutritious whole wheat loaf.', 'Healthy high-fiber brown bread.', 280.00, 250.00, 30, '1 loaf', '450g', 'Sri Lanka', 4.90, 14, 1),
(15, 2, 4, 10, 'Garlic Butter Herb Bread', 'garlic-butter-herb-bread', 'BRD-003', 'Oven baked garlic baguette infused with herbs.', 'Savory garlic butter herb loaf.', 350.00, 310.00, 20, '1 pc', '300g', 'Sri Lanka', 4.95, 22, 0),

-- Bakery -> Cakes & Buns
(16, 2, 5, 10, 'Chocolate Fudge Sponge Cake', 'chocolate-fudge-sponge-cake', 'CAK-001', 'Rich decadent chocolate fudge layer cake.', 'Rich chocolate fudge cake.', 1450.00, 1250.00, 15, '1 cake', '500g', 'Sri Lanka', 5.00, 30, 1),
(17, 2, 6, 10, 'Fresh Soft Cream Buns (Pack of 4)', 'fresh-soft-cream-buns', 'BUN-001', 'Sweet bakery buns stuffed with vanilla cream.', 'Fluffy sweet cream buns.', 260.00, 230.00, 25, '1 pack', '250g', 'Sri Lanka', 4.75, 8, 0),
(18, 2, 7, 10, 'Butter Croissants (Pack of 2)', 'butter-croissants-pack-2', 'PAS-001', 'Flaky golden French style butter croissants.', 'Flaky golden butter croissants.', 480.00, 420.00, 20, '1 pack', '200g', 'Sri Lanka', 4.90, 11, 0),

-- Beverages -> Soft Drinks & Juices
(19, 3, 8, 2, 'Elephant House Necto 1.5L', 'elephant-house-necto-1-5l', 'BEV-001', 'Classic Sri Lankan sweet fruit flavored carbonated drink.', 'Classic sweet Necto soft drink.', 420.00, 390.00, 60, '1 bottle', '1500ml', 'Sri Lanka', 4.90, 45, 1),
(20, 3, 8, 2, 'Ginger Beer (EGP) 1.5L', 'ginger-beer-egp-1-5l', 'BEV-002', 'Authentic spicy natural ginger beer.', 'Spicy natural ginger beer.', 450.00, 410.00, 50, '1 bottle', '1500ml', 'Sri Lanka', 4.95, 60, 1),
(21, 3, 9, 6, 'Nestle Mango Fruit Juice 1L', 'nestle-mango-fruit-juice-1l', 'BEV-003', 'Real fruit nectar drink packed with vitamins.', 'Delicious real mango juice pack.', 580.00, 520.00, 40, '1 carton', '1000ml', 'Sri Lanka', 4.80, 25, 0),
(22, 3, 10, 6, 'Dilmah Pure Ceylon Black Tea 100g', 'dilmah-pure-ceylon-black-tea', 'BEV-004', 'World famous rich aromatic Ceylon black tea leaves.', 'Aromatic Ceylon black tea.', 490.00, 440.00, 70, '1 box', '100g', 'Sri Lanka', 4.98, 55, 1),
(23, 3, 11, 6, 'Nescafe Classic Instant Coffee 100g', 'nescafe-classic-instant-coffee-100g', 'BEV-005', '100% pure instant coffee powder for a quick brew.', 'Rich instant coffee jar.', 1250.00, 1090.00, 35, '1 jar', '100g', 'Switzerland', 4.85, 32, 0),

-- Dairy & Eggs -> Milk & Cheese
(24, 4, 13, 4, 'Anchor Full Cream Milk Powder 400g', 'anchor-full-cream-milk-powder-400g', 'DAI-001', 'Rich and creamy pure New Zealand milk powder.', 'Creamy full cream milk powder.', 1150.00, 1020.00, 80, '1 pack', '400g', 'New Zealand', 4.95, 80, 1),
(25, 4, 13, 5, 'Highland Fresh Liquid Milk 1L', 'highland-fresh-liquid-milk-1l', 'DAI-002', '100% pure pasteurized fresh liquid milk.', 'Pure fresh liquid pasteurized milk.', 450.00, 399.00, 40, '1 carton', '1000ml', 'Sri Lanka', 4.90, 42, 1),
(26, 4, 14, 4, 'Cheddar Cheese Slices 200g', 'cheddar-cheese-slices-200g', 'DAI-003', 'Processed cheddar cheese slices perfect for burgers and sandwiches.', 'Rich cheddar cheese slices.', 980.00, 890.00, 30, '1 pack', '200g', 'New Zealand', 4.75, 19, 0),
(27, 4, 15, 5, 'Highland Pasteurized Butter 200g', 'highland-pasteurized-butter-200g', 'DAI-004', 'Pure creamy salted dairy butter.', 'Creamy salted local dairy butter.', 780.00, 690.00, 45, '1 block', '200g', 'Sri Lanka', 4.92, 28, 0),
(28, 4, 16, 5, 'Highland Set Yogurt 80g (Pack of 4)', 'highland-set-yogurt-pack-4', 'DAI-005', 'Delicious fresh set yogurt cup pack.', 'Fresh set dairy yogurt pack.', 360.00, 320.00, 60, '1 pack', '320g', 'Sri Lanka', 4.88, 33, 1),
(29, 4, 17, 1, 'Farm Fresh White Eggs (Pack of 10)', 'farm-fresh-white-eggs-10', 'DAI-006', 'Clean fresh farm eggs rich in protein.', 'Protein rich farm fresh eggs.', 590.00, 520.00, 90, '1 tray', '600g', 'Sri Lanka', 4.90, 50, 1),

-- Frozen Foods
(30, 5, 19, 10, 'Chicken Drumsticks 500g', 'frozen-chicken-drumsticks-500g', 'FRZ-001', 'Clean frozen fresh chicken drumsticks.', 'Tender frozen chicken drumsticks.', 950.00, 850.00, 25, '1 pack', '500g', 'Sri Lanka', 4.80, 15, 0),
(31, 5, 20, 2, 'Elephant House Vanilla Ice Cream 1L', 'elephant-house-vanilla-ice-cream-1l', 'FRZ-002', 'Smooth creamy natural vanilla ice cream tub.', 'Rich creamy vanilla ice cream.', 750.00, 660.00, 35, '1 tub', '1000ml', 'Sri Lanka', 4.95, 65, 1),
(32, 5, 21, 10, 'French Fries Potato Strips 1kg', 'french-fries-potato-strips-1kg', 'FRZ-003', 'Crispy oven baked or deep fry frozen potato fries.', 'Crispy frozen potato French fries.', 1200.00, 1050.00, 20, '1 pack', '1000g', 'Belgium', 4.70, 12, 0),

-- Snacks -> Biscuits, Chocolates & Chips
(33, 6, 22, 3, 'Maliban Chocolate Biscuit 200g', 'maliban-chocolate-biscuit-200g', 'SNK-001', 'Rich cocoa cream filled sandwich biscuits.', 'Iconic chocolate cream biscuits.', 240.00, 210.00, 100, '1 pack', '200g', 'Sri Lanka', 4.95, 75, 1),
(34, 6, 22, 7, 'Munchi Cream Cracker 190g', 'munchi-cream-cracker-190g', 'SNK-002', 'Crispy light wheat crackers ideal with tea.', 'Crispy classic cream crackers.', 220.00, 195.00, 120, '1 pack', '190g', 'Sri Lanka', 4.90, 90, 1),
(35, 6, 23, 6, 'KitKat 4-Finger Milk Chocolate', 'kitkat-4-finger-milk-chocolate', 'SNK-003', 'Crispy wafer fingers covered in smooth milk chocolate.', 'Crispy milk chocolate wafer bar.', 380.00, 330.00, 50, '1 bar', '41.5g', 'Dubai', 4.85, 30, 0),
(36, 6, 24, 10, 'Cassava Potato Chips Salted 100g', 'cassava-potato-chips-salted-100g', 'SNK-004', 'Crunchy lightly salted cassava tapioca chips.', 'Crunchy salted cassava chips.', 190.00, 160.00, 45, '1 pack', '100g', 'Sri Lanka', 4.65, 14, 0),
(37, 6, 25, 1, 'Roasted & Salted Cashew Nuts 150g', 'roasted-salted-cashew-nuts-150g', 'SNK-005', 'Premium Sri Lankan jumbo roasted cashews.', 'Jumbo roasted salted cashews.', 1450.00, 1290.00, 25, '1 pack', '150g', 'Sri Lanka', 4.98, 22, 1),

-- Household Essentials
(38, 7, 26, 8, 'Vim Dishwash Bar 200g', 'vim-dishwash-bar-200g', 'HSH-001', 'Lemon scented grease cutting dishwashing bar.', 'Lemon grease cutting dishwash bar.', 140.00, 120.00, 80, '1 bar', '200g', 'Sri Lanka', 4.80, 20, 0),
(39, 7, 28, 9, 'Sunlight Washing Powder 1kg', 'sunlight-washing-powder-1kg', 'HSH-002', 'Floral fresh laundry detergent powder for bright clothes.', 'Brightening floral laundry powder.', 650.00, 580.00, 60, '1 pack', '1000g', 'Sri Lanka', 4.90, 40, 1),
(40, 7, 27, 10, 'Kitchen Paper Towel Rolls (Pack of 2)', 'kitchen-paper-towel-rolls-2', 'HSH-003', 'Absorbent 2-ply kitchen paper towels.', 'Highly absorbent kitchen paper towels.', 420.00, 370.00, 40, '1 pack', '300g', 'Sri Lanka', 4.70, 10, 0),

-- Personal Care
(41, 8, 29, 8, 'Sunsilk Black Shine Shampoo 180ml', 'sunsilk-black-shine-shampoo-180ml', 'PC-001', 'Nourishing shampoo enriched with Amla pearl complex.', 'Shine nourishing hair shampoo.', 490.00, 430.00, 45, '1 bottle', '180ml', 'Sri Lanka', 4.85, 25, 0),
(42, 8, 30, 8, 'Lux Soft Rose Soap Bar 100g (Pack of 3)', 'lux-soft-rose-soap-bar-3', 'PC-002', 'Fragrant rose oil moisturizing bath soap.', 'Moisturizing rose bath soap pack.', 450.00, 399.00, 70, '1 pack', '300g', 'Sri Lanka', 4.90, 35, 1),
(43, 8, 31, 8, 'Signal Herbal Toothpaste 160g', 'signal-herbal-toothpaste-160g', 'PC-003', 'Natural clove and herbal cavity protection toothpaste.', 'Clove herbal toothpaste.', 320.00, 280.00, 60, '1 tube', '160g', 'Sri Lanka', 4.88, 30, 0);

-- Product Primary Images Mapping
INSERT INTO `product_images` (`product_id`, `image_path`, `is_primary`) VALUES
(1, 'assets/images/products/apples.jpg', 1),
(1, 'assets/images/products/apples_detail.jpg', 0),
(2, 'assets/images/products/bananas.jpg', 1),
(3, 'assets/images/products/oranges.jpg', 1),
(4, 'assets/images/products/mangoes.jpg', 1),
(5, 'assets/images/products/avocado.jpg', 1),
(6, 'assets/images/products/tomatoes.jpg', 1),
(7, 'assets/images/products/carrots.jpg', 1),
(8, 'assets/images/products/potatoes.jpg', 1),
(9, 'assets/images/products/onions.jpg', 1),
(10, 'assets/images/products/capsicum.jpg', 1),
(11, 'assets/images/products/coriander.jpg', 1),
(12, 'assets/images/products/mint.jpg', 1),
(13, 'assets/images/products/white_bread.jpg', 1),
(14, 'assets/images/products/brown_bread.jpg', 1),
(15, 'assets/images/products/garlic_bread.jpg', 1),
(16, 'assets/images/products/chocolate_cake.jpg', 1),
(17, 'assets/images/products/cream_buns.jpg', 1),
(18, 'assets/images/products/croissants.jpg', 1),
(19, 'assets/images/products/necto.jpg', 1),
(20, 'assets/images/products/egp.jpg', 1),
(21, 'assets/images/products/mango_juice.jpg', 1),
(22, 'assets/images/products/dilmah_tea.jpg', 1),
(23, 'assets/images/products/nescafe.jpg', 1),
(24, 'assets/images/products/anchor_milk.jpg', 1),
(25, 'assets/images/products/highland_liquid_milk.jpg', 1),
(26, 'assets/images/products/cheddar_cheese.jpg', 1),
(27, 'assets/images/products/highland_butter.jpg', 1),
(28, 'assets/images/products/highland_yogurt.jpg', 1),
(29, 'assets/images/products/eggs.jpg', 1),
(30, 'assets/images/products/chicken_drumsticks.jpg', 1),
(31, 'assets/images/products/vanilla_icecream.jpg', 1),
(32, 'assets/images/products/french_fries.jpg', 1),
(33, 'assets/images/products/maliban_choc.jpg', 1),
(34, 'assets/images/products/munchi_cracker.jpg', 1),
(35, 'assets/images/products/kitkat.jpg', 1),
(36, 'assets/images/products/cassava_chips.jpg', 1),
(37, 'assets/images/products/cashews.jpg', 1),
(38, 'assets/images/products/vim_bar.jpg', 1),
(39, 'assets/images/products/sunlight_powder.jpg', 1),
(40, 'assets/images/products/paper_towels.jpg', 1),
(41, 'assets/images/products/sunsilk.jpg', 1),
(42, 'assets/images/products/lux_soap.jpg', 1),
(43, 'assets/images/products/signal_toothpaste.jpg', 1);

-- Seed Initial Order & Demo Delivery / Tracking
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `discount_amount`, `delivery_fee`, `grand_total`, `payment_method`, `payment_status`, `order_status`, `shipping_first_name`, `shipping_last_name`, `shipping_email`, `shipping_phone`, `shipping_address`, `shipping_city`, `shipping_postal_code`, `delivery_type`, `created_at`) VALUES
(1, 'INC-100852', 2, 1970.00, 100.00, 0.00, 1870.00, 'Cash on Delivery', 'Paid', 'Out for Delivery', 'Kasun', 'Perera', 'customer@incart.lk', '+94779876543', '45 Galle Road', 'Colombo', '00300', 'Standard', '2026-09-06 10:30:00');

INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`) VALUES
(1, 24, 'Anchor Full Cream Milk Powder 400g', 1020.00, 1, 1020.00),
(1, 13, 'Fresh Sandwich White Bread', 199.00, 1, 199.00),
(1, 20, 'Ginger Beer (EGP) 1.5L', 410.00, 1, 410.00),
(1, 33, 'Maliban Chocolate Biscuit 200g', 210.00, 1, 210.00);

INSERT INTO `payments` (`order_id`, `payment_method`, `transaction_id`, `amount`, `status`) VALUES
(1, 'Cash on Delivery', 'COD-INC-100852', 1870.00, 'Completed');

INSERT INTO `deliveries` (`order_id`, `delivery_type`, `tracking_number`, `status`, `estimated_delivery`) VALUES
(1, 'Standard', 'TRK-INC-85209', 'Out for Delivery', 'Today before 6:00 PM');

-- Seed Sample Approved Reviews
INSERT INTO `reviews` (`product_id`, `user_id`, `rating`, `comment`, `is_approved`, `created_at`) VALUES
(24, 2, 5, 'Super fresh milk powder quality and fast delivery by InCart!', 1, '2026-09-06 14:20:00'),
(13, 2, 5, 'Very soft bread delivered fresh in the morning.', 1, '2026-09-06 15:00:00');
