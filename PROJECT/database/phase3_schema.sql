-- Phase 3 Database Schema for QUDRIX CRM
-- Added tables: bookings, booking_travelers, booking_itineraries, group_bookings, booking_confirmations

ALTER TABLE `packages` ADD COLUMN `duration_days` INT DEFAULT 7;
ALTER TABLE `packages` ADD COLUMN `max_group_size` INT DEFAULT 0;

CREATE TABLE `bookings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `lead_id` BIGINT UNSIGNED,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `package_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `group_booking_id` BIGINT UNSIGNED,
  `booking_number` VARCHAR(50) UNIQUE NOT NULL,
  `booking_type` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `travel_date` TIMESTAMP NOT NULL,
  `return_date` TIMESTAMP NOT NULL,
  `number_of_travelers` INT NOT NULL,
  `total_amount` DECIMAL(12, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `payment_status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `confirmation_date` TIMESTAMP NULL,
  `special_requests` JSON,
  `visa_required` BOOLEAN DEFAULT false,
  `notes` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`group_booking_id`) REFERENCES `group_bookings`(`id`) ON DELETE SET NULL,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_travel_date` (`travel_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_travelers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `gender` VARCHAR(20) NOT NULL,
  `passport_number` VARCHAR(100) NOT NULL,
  `passport_expiry` DATE NOT NULL,
  `national_id` VARCHAR(100),
  `nationality` VARCHAR(2) NOT NULL,
  `traveler_type` VARCHAR(50) NOT NULL,
  `is_primary_contact` BOOLEAN DEFAULT false,
  `emergency_contact` VARCHAR(255) NOT NULL,
  `emergency_phone` VARCHAR(20) NOT NULL,
  `room_preference` VARCHAR(255),
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  
  INDEX `idx_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_itineraries` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `day_number` INT NOT NULL,
  `date` DATE NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `activity_type` VARCHAR(50) NOT NULL,
  `activity_name` VARCHAR(255) NOT NULL,
  `description` LONGTEXT,
  `start_time` TIME,
  `end_time` TIME,
  `hotel_name` VARCHAR(255),
  `meal_type` VARCHAR(50),
  `transportation_type` VARCHAR(50),
  `notes` LONGTEXT,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_day_number` (`day_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_bookings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `group_leader_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `group_name` VARCHAR(255) NOT NULL,
  `total_members` INT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `description` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_leader_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_group_leader_id` (`group_leader_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_confirmations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `confirmed_by` BIGINT UNSIGNED NOT NULL,
  `confirmation_number` VARCHAR(50) UNIQUE NOT NULL,
  `confirmation_date` TIMESTAMP NOT NULL,
  `confirmation_method` VARCHAR(50) NOT NULL,
  `reference_code` VARCHAR(255),
  `provider_confirmation_id` VARCHAR(255),
  `notes` LONGTEXT,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
