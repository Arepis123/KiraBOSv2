-- Add color field to categories table for custom color management
-- Run this script to add color functionality to category management

-- First, check if color column already exists
-- If it doesn't exist, add it
ALTER TABLE categories 
ADD COLUMN IF NOT EXISTS color VARCHAR(7) DEFAULT '#FF6B6B' AFTER icon;

-- Update existing categories with default colors if they don't have colors
UPDATE categories SET color = CASE 
    WHEN LOWER(name) LIKE '%food%' OR LOWER(name) LIKE '%meal%' OR LOWER(name) LIKE '%main%' THEN '#FF6B6B'
    WHEN LOWER(name) LIKE '%drink%' OR LOWER(name) LIKE '%beverage%' OR LOWER(name) LIKE '%coffee%' OR LOWER(name) LIKE '%tea%' THEN '#4ECDC4'
    WHEN LOWER(name) LIKE '%dessert%' OR LOWER(name) LIKE '%sweet%' OR LOWER(name) LIKE '%cake%' THEN '#FFE66D'
    WHEN LOWER(name) LIKE '%appetizer%' OR LOWER(name) LIKE '%starter%' THEN '#74B9FF'
    WHEN LOWER(name) LIKE '%snack%' OR LOWER(name) LIKE '%side%' THEN '#A29BFE'
    ELSE '#FF6B6B'
END
WHERE color IS NULL OR color = '';

-- Create index on color for better performance
CREATE INDEX IF NOT EXISTS idx_categories_color ON categories(color);