-- Update Users and Products tables for GPS integration
USE thrift_store;

ALTER TABLE users 
ADD COLUMN country VARCHAR(100) AFTER preferred_location,
ADD COLUMN city VARCHAR(100) AFTER country,
ADD COLUMN latitude DECIMAL(10, 8) AFTER city,
ADD COLUMN longitude DECIMAL(11, 8) AFTER latitude;

ALTER TABLE products
ADD COLUMN country VARCHAR(100) AFTER location,
ADD COLUMN city VARCHAR(100) AFTER country,
ADD COLUMN latitude DECIMAL(10, 8) AFTER city,
ADD COLUMN longitude DECIMAL(11, 8) AFTER latitude;

-- Index for faster filtering and potential spatial queries
CREATE INDEX idx_user_country ON users(country);
CREATE INDEX idx_product_country ON products(country);
CREATE INDEX idx_user_city ON users(city);
CREATE INDEX idx_product_city ON products(city);
