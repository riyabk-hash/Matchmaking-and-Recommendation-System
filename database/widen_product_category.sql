-- Allow multiple categories per product (comma-separated, e.g. women,home_decor,accessories)
USE thrift_store;
ALTER TABLE products MODIFY COLUMN category VARCHAR(255) NULL;
