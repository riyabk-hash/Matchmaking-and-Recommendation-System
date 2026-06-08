-- Ensure FULLTEXT index for search
USE thrift_store;

-- Check if index exists
SHOW INDEX FROM products WHERE Key_name = 'ft_search';

-- Add if missing (safe to run multiple times)
ALTER TABLE products ADD FULLTEXT INDEX ft_search (title, description, category);

-- Verify
SHOW INDEX FROM products LIKE '%ft_search%';

-- Test query
SELECT id, title, category, MATCH(title, description, category) AGAINST('shoes' IN NATURAL LANGUAGE MODE) as relevance
FROM products WHERE MATCH(title, description, category) AGAINST('shoes' IN NATURAL LANGUAGE MODE)
AND status = 'active';

