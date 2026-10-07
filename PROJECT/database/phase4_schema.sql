-- Phase 4 Database Schema for QUDRIX CRM
-- Flights, Hotels, Transport, Destinations, Visa Applications

CREATE TABLE `flights` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `booking_id` BIGINT UNSIGNED,
  `airline_code` VARCHAR(10) NOT NULL,
  `flight_number` VARCHAR(20) UNIQUE NOT NULL,
  `departure_airport` VARCHAR(3) NOT NULL,
  `arrival_airport` VARCHAR(3) NOT NULL,
  `departure_date` TIMESTAMP NOT NULL,
  `arrival_date` TIMESTAMP NOT NULL,
  `departure_time` TIME NOT NULL,
  `arrival_time` TIME NOT NULL,
  `aircraft_type` VARCHAR(100) NOT NULL,
  `total_seats` INT NOT NULL,
  `available_seats` INT NOT NULL,
  `price_per_seat` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `status` VARCHAR(50) DEFAULT 'active',
  `notes` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_departure_airport` (`departure_airport`),
  INDEX `idx_arrival_airport` (`arrival_airport`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `flight_bookings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `flight_id` BIGINT UNSIGNED NOT NULL,
  `booking_traveler_id` BIGINT UNSIGNED NOT NULL,
  `seat_number` VARCHAR(10) NOT NULL,
  `ticket_number` VARCHAR(50) UNIQUE,
  `status` VARCHAR(50) DEFAULT 'booked',
  `price_paid` DECIMAL(10, 2),
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`flight_id`) REFERENCES `flights`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_traveler_id`) REFERENCES `booking_travelers`(`id`) ON DELETE CASCADE,
  
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_flight_id` (`flight_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotels` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `country` VARCHAR(2) NOT NULL,
  `address` LONGTEXT NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `website` VARCHAR(255),
  `star_rating` INT NOT NULL,
  `description` LONGTEXT,
  `total_rooms` INT NOT NULL,
  `available_rooms` INT NOT NULL,
  `price_per_night` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `check_in_time` TIME DEFAULT '14:00:00',
  `check_out_time` TIME DEFAULT '11:00:00',
  `status` VARCHAR(50) DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hotel_bookings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `hotel_id` BIGINT UNSIGNED NOT NULL,
  `check_in_date` DATE NOT NULL,
  `check_out_date` DATE NOT NULL,
  `number_of_rooms` INT NOT NULL,
  `number_of_nights` INT NOT NULL,
  `room_type` VARCHAR(100) NOT NULL,
  `price_per_night` DECIMAL(10, 2) NOT NULL,
  `total_price` DECIMAL(12, 2) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'confirmed',
  `confirmation_number` VARCHAR(50) UNIQUE,
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`hotel_id`) REFERENCES `hotels`(`id`) ON DELETE CASCADE,
  
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_hotel_id` (`hotel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transports` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `booking_id` BIGINT UNSIGNED,
  `transport_type` VARCHAR(50) NOT NULL,
  `vehicle_name` VARCHAR(255) NOT NULL,
  `vehicle_number` VARCHAR(50) NOT NULL,
  `pickup_location` VARCHAR(255) NOT NULL,
  `dropoff_location` VARCHAR(255) NOT NULL,
  `pickup_date` DATE NOT NULL,
  `pickup_time` TIME NOT NULL,
  `dropoff_time` TIME,
  `capacity` INT NOT NULL,
  `price_per_seat` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `driver_name` VARCHAR(255) NOT NULL,
  `driver_phone` VARCHAR(20) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'active',
  `notes` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
  
  INDEX `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transport_bookings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `transport_id` BIGINT UNSIGNED NOT NULL,
  `booking_traveler_id` BIGINT UNSIGNED,
  `seat_number` VARCHAR(10),
  `status` VARCHAR(50) DEFAULT 'booked',
  
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transport_id`) REFERENCES `transports`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_traveler_id`) REFERENCES `booking_travelers`(`id`) ON DELETE SET NULL,
  
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_transport_id` (`transport_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `destinations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `country` VARCHAR(100) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `region` VARCHAR(100),
  `description` LONGTEXT,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `tourist_season` VARCHAR(255),
  `weather_info` LONGTEXT,
  `visa_required` BOOLEAN DEFAULT false,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `language` VARCHAR(100),
  `image_url` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visa_applications` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `booking_traveler_id` BIGINT UNSIGNED NOT NULL,
  `destination_country` VARCHAR(2) NOT NULL,
  `visa_type` VARCHAR(100) NOT NULL,
  `application_date` DATE NOT NULL,
  `submission_date` DATE,
  `approval_date` DATE,
  `visa_number` VARCHAR(100) UNIQUE,
  `issue_date` DATE,
  `expiry_date` DATE,
  `status` VARCHAR(50) DEFAULT 'pending',
  `documents` JSON,
  `notes` LONGTEXT,
  `agency_name` VARCHAR(255),
  `agency_reference` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_traveler_id`) REFERENCES `booking_travelers`(`id`) ON DELETE CASCADE,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
