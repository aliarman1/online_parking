-- Option 1: Create missing users for the orphaned bookings
-- First, identify the missing user IDs
SET @missing_user_id = 14; -- Replace with the actual missing user ID from your error

-- Insert a placeholder user for each missing user ID
INSERT INTO users (id, username, email, password, role, created_at, updated_at)
VALUES (@missing_user_id, CONCAT('user', @missing_user_id), CONCAT('user', @missing_user_id, '@example.com'), 
        '$2y$10$6VrGiDX6voZH4uCZJVmUL.3fWPJymMHfkn8tnDrTF40GdHbOEQXDC', 'User', NOW(), NOW());

-- Option 2: Delete orphaned bookings (use with caution)
-- DELETE FROM bookings WHERE user_id NOT IN (SELECT id FROM users);

-- Option 3: Update orphaned bookings to reference an existing user
-- UPDATE bookings SET user_id = 1 WHERE user_id NOT IN (SELECT id FROM users);

-- After fixing the data, you can add the constraints
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `parking_slots` (`id`) ON DELETE CASCADE;
