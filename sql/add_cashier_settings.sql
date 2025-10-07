-- Migration: Add cashier_settings column to restaurants table
-- This will store all cashier preferences as JSON

ALTER TABLE restaurants
ADD COLUMN IF NOT EXISTS cashier_settings TEXT NULL
COMMENT 'JSON object storing cashier preferences (theme, sounds, display, numpad, workflow)';

-- Set default cashier settings for existing restaurants
UPDATE restaurants
SET cashier_settings = JSON_OBJECT(
    'theme', JSON_OBJECT('mode', 'colorful'),
    'sounds', JSON_OBJECT('clicks', false, 'success', true),
    'display', JSON_OBJECT('textSize', 'medium', 'currencyFormat', 'RM0.00'),
    'numpad', JSON_OBJECT('layout', 'calculator'),
    'workflow', JSON_OBJECT(
        'defaultPayment', 'cash',
        'autoClear', true,
        'quickAmounts', JSON_ARRAY(10, 20, 50)
    )
)
WHERE cashier_settings IS NULL OR cashier_settings = '';
