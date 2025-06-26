-- SQL script to update payment structure
-- Remove placement_type column as it's no longer needed
-- The currency is now always USD and set in the individual period selectors

-- First, check if the column exists before dropping it
SET @sql = CONCAT('ALTER TABLE paperworkdetails DROP COLUMN IF EXISTS placement_type');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update any existing records that might have NULL values for rates
-- to ensure they have proper format
UPDATE paperworkdetails 
SET clientrate = CONCAT(COALESCE(SUBSTRING_INDEX(clientrate, ' ', 1), '0'), ' USD /hour on W2')
WHERE clientrate IS NOT NULL 
  AND clientrate != '' 
  AND clientrate NOT LIKE '% USD %';

UPDATE paperworkdetails 
SET payrate = CONCAT(COALESCE(SUBSTRING_INDEX(payrate, ' ', 1), '0'), ' USD /hour on W2')
WHERE payrate IS NOT NULL 
  AND payrate != '' 
  AND payrate NOT LIKE '% USD %';

-- Add a comment to document the change
ALTER TABLE paperworkdetails 
COMMENT = 'Updated: Removed placement_type column. Currency is now always USD, set via period selectors.';
