CREATE DATABASE IF NOT EXISTS chez_ketchup
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE chez_ketchup;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin', 'manager', 'cashier') NOT NULL DEFAULT 'cashier',
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id VARCHAR(60) PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE menu_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  price INT UNSIGNED NOT NULL,
  max_price INT UNSIGNED NULL,
  image_url VARCHAR(255) NULL,
  emoji VARCHAR(16) NULL,
  available BOOLEAN NOT NULL DEFAULT TRUE,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  popular BOOLEAN NOT NULL DEFAULT FALSE,
  daily_special BOOLEAN NOT NULL DEFAULT FALSE,
  stock_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  stock_qty INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 3,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_menu_category FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  phone VARCHAR(40) NOT NULL UNIQUE,
  default_address VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE delivery_staff (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  access_code_hash VARCHAR(255) NULL,
  status ENUM('available', 'busy', 'absent') NOT NULL DEFAULT 'available',
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(32) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NULL,
  customer_name VARCHAR(140) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  address VARCHAR(255) NOT NULL,
  note TEXT NULL,
  fulfillment ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
  delivery_zone_id BIGINT UNSIGNED NULL,
  scheduled_at DATETIME NULL,
  status ENUM('pending', 'prep', 'delivery', 'done', 'cancelled') NOT NULL DEFAULT 'pending',
  subtotal INT UNSIGNED NOT NULL DEFAULT 0,
  delivery_fee INT UNSIGNED NOT NULL DEFAULT 0,
  discount_total INT UNSIGNED NOT NULL DEFAULT 0,
  promo_code VARCHAR(40) NULL,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  delivery_staff_id BIGINT UNSIGNED NULL,
  cancellation_reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_orders_delivery_staff FOREIGN KEY (delivery_staff_id) REFERENCES delivery_staff(id),
  CONSTRAINT fk_orders_user FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  menu_item_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  options_text TEXT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price INT UNSIGNED NOT NULL,
  line_total INT UNSIGNED NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  method ENUM('cash', 'orange_money', 'wave') NOT NULL DEFAULT 'orange_money',
  provider VARCHAR(80) NULL,
  reference VARCHAR(120) NULL,
  checkout_url VARCHAR(255) NULL,
  amount INT UNSIGNED NOT NULL,
  status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending', 'prep', 'delivery', 'done', 'cancelled') NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(id)
);

CREATE TABLE contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  message TEXT NOT NULL,
  handled BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NULL,
  channel ENUM('whatsapp', 'sms') NOT NULL DEFAULT 'whatsapp',
  recipient VARCHAR(40) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
  provider VARCHAR(80) NULL,
  provider_reference VARCHAR(120) NULL,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  user_name VARCHAR(140) NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details TEXT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

<<<<<<< HEAD
=======
CREATE TABLE app_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE business_hours (
  weekday TINYINT UNSIGNED PRIMARY KEY,
  open_time TIME NULL,
  close_time TIME NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE delivery_zones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  fee INT UNSIGNED NOT NULL DEFAULT 0,
  minimum_order INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_minutes INT UNSIGNED NOT NULL DEFAULT 45,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE menu_item_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  menu_item_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  price_delta INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_option_menu_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

CREATE TABLE promo_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  label VARCHAR(140) NOT NULL,
  type ENUM('percent', 'fixed') NOT NULL DEFAULT 'fixed',
  value INT UNSIGNED NOT NULL DEFAULT 0,
  minimum_order INT UNSIGNED NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customer_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

ALTER TABLE promo_codes ADD COLUMN expires_at DATETIME NULL AFTER active;
ALTER TABLE promo_codes ADD COLUMN max_uses INT UNSIGNED NULL AFTER expires_at;
ALTER TABLE promo_codes ADD COLUMN used_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER max_uses;

>>>>>>> 5f92b50 (mise a jour)
CREATE INDEX idx_orders_status_created ON orders(status, created_at);
CREATE INDEX idx_orders_phone ON orders(phone);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_orders_customer ON orders(customer_id);
CREATE INDEX idx_orders_delivery_staff ON orders(delivery_staff_id);
CREATE INDEX idx_orders_delivery_zone ON orders(delivery_zone_id);
CREATE INDEX idx_orders_created ON orders(created_at);
CREATE INDEX idx_payments_order ON payments(order_id);
CREATE INDEX idx_payments_reference ON payments(reference);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_notifications_order ON notifications(order_id);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX idx_menu_items_category ON menu_items(category_id);
CREATE INDEX idx_delivery_staff_phone ON delivery_staff(phone);
