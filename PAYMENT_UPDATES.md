# Payment Section Updates

## Changes Made

### 1. Removed Pay Type Field
- Removed the "Pay Type" dropdown from the Payment Details section in `paperwork.php`
- This field was previously used to set the rate period globally

### 2. Currency Always USD
- Changed currency display from variable `$` to fixed `USD`
- Updated CSS class from `.rate-unit` to `.rate-currency`
- Currency is now always USD and cannot be changed

### 3. Individual Period Selection
- Each rate (Client Bill Rate and Pay Rate) now has its own period selector
- Available periods: /hour, /day, /week, /month, /year, /project, custom
- Example format: "50 USD /hour on 1099"

### 4. Database Changes
- Removed `placement_type` column from `paperworkdetails` table
- Rate format: `[amount] USD [/period] on [tax_term]`
- Example: "3 USD /hour on 1099"

### 5. JavaScript Updates
- Added `combineClientRate()` and `combinePayRate()` functions
- These functions create the combined rate string format
- Event listeners added to update combined rates when fields change
- Removed old `setupPayTypeListener()` function

### Files Modified
1. `paperwork.php` - Main form file
2. `process_form.php` - Already handled combined rates correctly
3. `paperworkedit.php` - Already compatible with new format
4. `update_database.php` - Script to update database structure
5. `update_payment_structure.sql` - SQL script for manual database updates

### How to Apply Database Changes
Run the database update script:
```bash
php update_database.php
```

Or manually execute the SQL script:
```sql
-- Run the contents of update_payment_structure.sql
```

### Rate Format Examples
- Client Bill Rate: "75 USD /hour on W2"
- Pay Rate: "50 USD /hour on 1099"
- Monthly Rate: "8000 USD /month on Corp-to-Corp"
- Daily Rate: "400 USD /day on 1099"

### Compatibility
- The `paperworkedit.php` file already supports parsing the new format
- Existing records will be automatically updated to the new format
- The system maintains backward compatibility with existing data
