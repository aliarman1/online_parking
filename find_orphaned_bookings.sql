-- Find bookings with user_id values that don't exist in the users table
SELECT b.* 
FROM bookings b
LEFT JOIN users u ON b.user_id = u.id
WHERE u.id IS NULL;
