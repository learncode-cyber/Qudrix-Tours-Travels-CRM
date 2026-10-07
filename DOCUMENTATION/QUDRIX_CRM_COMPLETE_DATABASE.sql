-- ============================================================
-- QUDRIX TRAVEL CRM - COMPLETE DATABASE SCHEMA (All Phases 0-9)
-- ============================================================
-- Database: QUDRIX CRM
-- Version: Phase 0 through Phase 9 Complete
-- Target: MySQL 8.0+ / MariaDB 10.5+
-- Creation Date: 2024-01-01
-- Tables: 50+ (all phases)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- ============================================================
-- PHASE 0: CORE FOUNDATION
-- ============================================================

-- 1. TENANTS TABLE
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL UNIQUE,
  `slug` varchar(255) NOT NULL UNIQUE,
  `email` varchar(255) NULL,
  `description` longtext NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `currency` varchar(3) DEFAULT 'USD',
  `settings` json NULL,
  `is_active` boolean DEFAULT true,
  `trial_ends_at` timestamp NULL,
  `plan` varchar(50) DEFAULT 'free',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  UNIQUE KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `phone` varchar(20) NULL,
  `password` varchar(255) NOT NULL,
  `avatar_url` varchar(255) NULL,
  `is_active` boolean DEFAULT true,
  `status` varchar(50) DEFAULT 'active',
  `mfa_enabled` boolean DEFAULT false,
  `mfa_secret` varchar(255) NULL,
  `email_verified_at` timestamp NULL,
  `last_login_at` timestamp NULL,
  `remember_token` varchar(100) NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ROLES TABLE
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL UNIQUE,
  `permissions` json DEFAULT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  UNIQUE KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ROLE_USER PIVOT TABLE
CREATE TABLE IF NOT EXISTS `role_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_role_tenant` (`user_id`, `role_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BRANCHES TABLE
CREATE TABLE IF NOT EXISTS `branches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL UNIQUE,
  `address` text NULL,
  `city` varchar(100) NULL,
  `country` varchar(100) NULL,
  `phone` varchar(20) NULL,
  `email` varchar(255) NULL,
  `manager_id` bigint unsigned NULL,
  `is_active` boolean DEFAULT true,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. PACKAGES TABLE
CREATE TABLE IF NOT EXISTS `packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext NULL,
  `type` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT 'active',
  `price` decimal(12,2) DEFAULT 0,
  `currency` varchar(3) DEFAULT 'USD',
  `duration_days` int unsigned NULL,
  `inclusions` json NULL,
  `exclusions` json NULL,
  `is_active` boolean DEFAULT true,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_type` (`type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. AUDIT_LOGS TABLE
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `old_values` json NULL,
  `new_values` json NULL,
  `ip_address` varchar(45) NULL,
  `user_agent` text NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_entity_type` (`entity_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 1: CUSTOMER RELATIONSHIP MANAGEMENT (CRM)
-- ============================================================

-- 8. CUSTOMERS TABLE
CREATE TABLE IF NOT EXISTS `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NULL,
  `phone` varchar(20) NULL,
  `customer_type` varchar(50) DEFAULT 'individual',
  `national_id` varchar(100) NULL,
  `passport_number` varchar(100) NULL,
  `address` text NULL,
  `city` varchar(100) NULL,
  `country` varchar(100) NULL,
  `additional_info` json NULL,
  `is_active` boolean DEFAULT true,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. LEADS TABLE
CREATE TABLE IF NOT EXISTS `leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NULL,
  `phone` varchar(20) NULL,
  `source` varchar(50) NULL,
  `status` varchar(50) DEFAULT 'new',
  `priority` varchar(50) DEFAULT 'medium',
  `assigned_to` bigint unsigned NULL,
  `notes` text NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. LEAD_SCORES TABLE
CREATE TABLE IF NOT EXISTS `lead_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `lead_id` bigint unsigned NOT NULL,
  `engagement_score` int unsigned DEFAULT 0,
  `conversion_probability` decimal(5,2) DEFAULT 0.00,
  `scoring_criteria` json NULL,
  `last_scored_at` timestamp NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_lead_id` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. CUSTOMER_FAMILIES TABLE
CREATE TABLE IF NOT EXISTS `customer_families` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `relationship` varchar(50) NULL,
  `date_of_birth` date NULL,
  `passport_number` varchar(100) NULL,
  `nationality` varchar(100) NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. COMMUNICATIONS TABLE
CREATE TABLE IF NOT EXISTS `communications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `subject` varchar(255) NULL,
  `message` longtext NULL,
  `status` varchar(50) DEFAULT 'new',
  `priority` varchar(50) DEFAULT 'normal',
  `created_by` bigint unsigned NULL,
  `read_at` timestamp NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. TASKS TABLE
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NULL,
  `assigned_to` bigint unsigned NULL,
  `status` varchar(50) DEFAULT 'pending',
  `priority` varchar(50) DEFAULT 'medium',
  `due_date` timestamp NULL,
  `completed_at` timestamp NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 2: SALES & QUOTATIONS
-- ============================================================

-- 14. QUOTATIONS TABLE
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `lead_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NULL,
  `quotation_number` varchar(100) NOT NULL UNIQUE,
  `status` varchar(50) DEFAULT 'draft',
  `total_amount` decimal(12,2) DEFAULT 0,
  `currency` varchar(3) DEFAULT 'USD',
  `valid_until` date NULL,
  `notes` longtext NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_quotation_number` (`quotation_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. QUOTATION_ITEMS TABLE
CREATE TABLE IF NOT EXISTS `quotation_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `quotation_id` bigint unsigned NOT NULL,
  `package_id` bigint unsigned NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int unsigned DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  KEY `idx_quotation_id` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. PROPOSALS TABLE
CREATE TABLE IF NOT EXISTS `proposals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `quotation_id` bigint unsigned NOT NULL,
  `lead_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NULL,
  `proposal_number` varchar(100) NOT NULL UNIQUE,
  `status` varchar(50) DEFAULT 'draft',
  `total_value` decimal(12,2) NOT NULL,
  `valid_until` date NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. DEAL_STAGES TABLE
CREATE TABLE IF NOT EXISTS `deal_stages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NULL,
  `sequence` int unsigned DEFAULT 0,
  `is_active` boolean DEFAULT true,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_sequence` (`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. SALES_ACTIVITIES TABLE
CREATE TABLE IF NOT EXISTS `sales_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `lead_id` bigint unsigned NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NULL,
  `scheduled_for` timestamp NULL,
  `completed_at` timestamp NULL,
  `user_id` bigint unsigned NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_lead_id` (`lead_id`),
  KEY `idx_activity_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 3: BOOKING ENGINE
-- ============================================================

-- 19. BOOKINGS TABLE
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `reference_number` varchar(100) NOT NULL UNIQUE,
  `status` varchar(50) DEFAULT 'pending',
  `type` varchar(50) NOT NULL,
  `travel_date` date NULL,
  `return_date` date NULL,
  `number_of_travelers` int unsigned DEFAULT 1,
  `total_cost` decimal(12,2) DEFAULT 0,
  `currency` varchar(3) DEFAULT 'USD',
  `notes` longtext NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_reference_number` (`reference_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. BOOKING_TRAVELERS TABLE
CREATE TABLE IF NOT EXISTS `booking_travelers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `booking_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `passport_number` varchar(100) NULL,
  `date_of_birth` date NULL,
  `nationality` varchar(100) NULL,
  `gender` varchar(10) NULL,
  `contact_email` varchar(255) NULL,
  `contact_phone` varchar(20) NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_passport_number` (`passport_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. BOOKING_ITINERARIES TABLE
CREATE TABLE IF NOT EXISTS `booking_itineraries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `booking_id` bigint unsigned NOT NULL,
  `day_number` int unsigned NOT NULL,
  `activity` varchar(255) NOT NULL,
  `location` varchar(255) NULL,
  `description` longtext NULL,
  `start_time` timestamp NULL,
  `end_time` timestamp NULL,
  `notes` text NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_day_number` (`day_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. BOOKING_CONFIRMATIONS TABLE
CREATE TABLE IF NOT EXISTS `booking_confirmations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `booking_id` bigint unsigned NOT NULL,
  `confirmation_number` varchar(100) NOT NULL UNIQUE,
  `provider` varchar(100) NULL,
  `status` varchar(50) DEFAULT 'pending',
  `confirmation_date` timestamp NULL,
  `document_url` varchar(255) NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_confirmation_number` (`confirmation_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. GROUP_BOOKINGS TABLE
CREATE TABLE IF NOT EXISTS `group_bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `leader_id` bigint unsigned NOT NULL,
  `group_type` varchar(50) NOT NULL,
  `member_count` int unsigned DEFAULT 0,
  `status` varchar(50) DEFAULT 'active',
  `start_date` date NULL,
  `end_date` date NULL,
  `total_cost` decimal(12,2) DEFAULT 0,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leader_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_group_type` (`group_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 4: TRAVEL SERVICES
-- ============================================================

-- 24. FLIGHTS TABLE
CREATE TABLE IF NOT EXISTS `flights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `airline_code` varchar(10) NOT NULL,
  `flight_number` varchar(20) NOT NULL,
  `origin` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `departure_time` timestamp NULL,
  `arrival_time` timestamp NULL,
  `price_per_person` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `available_seats` int unsigned DEFAULT 0,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_flight_number` (`flight_number`),
  KEY `idx_origin_destination` (`origin`, `destination`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. FLIGHT_BOOKINGS TABLE
CREATE TABLE IF NOT EXISTS `flight_bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `booking_id` bigint unsigned NOT NULL,
  `flight_id` bigint unsigned NOT NULL,
  `booking_reference` varchar(100) NOT NULL UNIQUE,
  `seat_number` varchar(10) NULL,
  `status` varchar(50) DEFAULT 'confirmed',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`flight_id`) REFERENCES `flights` (`id`) ON DELETE CASCADE,
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_flight_id` (`flight_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. HOTELS TABLE
CREATE TABLE IF NOT EXISTS `hotels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `address` text NULL,
  `rating` decimal(3,1) NULL,
  `room_price_per_night` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `available_rooms` int unsigned DEFAULT 0,
  `amenities` json NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_city_country` (`city`, `country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 27. HOTEL_BOOKINGS TABLE
CREATE TABLE IF NOT EXISTS `hotel_bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `booking_id` bigint unsigned NOT NULL,
  `hotel_id` bigint unsigned NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `room_number` varchar(20) NULL,
  `room_type` varchar(50) NULL,
  `number_of_nights` int unsigned NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `status` varchar(50) DEFAULT 'confirmed',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE,
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_hotel_id` (`hotel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 28. TRANSPORTS TABLE
CREATE TABLE IF NOT EXISTS `transports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `transport_type` varchar(50) NOT NULL,
  `provider_name` varchar(255) NOT NULL,
  `origin` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `departure_time` timestamp NULL,
  `arrival_time` timestamp NULL,
  `price_per_person` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `capacity` int unsigned DEFAULT 50,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_transport_type` (`transport_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 29. TRANSPORT_BOOKINGS TABLE
CREATE TABLE IF NOT EXISTS `transport_bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `booking_id` bigint unsigned NOT NULL,
  `transport_id` bigint unsigned NOT NULL,
  `booking_reference` varchar(100) NOT NULL UNIQUE,
  `seat_number` varchar(10) NULL,
  `passenger_name` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT 'confirmed',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transport_id`) REFERENCES `transports` (`id`) ON DELETE CASCADE,
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_transport_id` (`transport_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 30. DESTINATIONS TABLE
CREATE TABLE IF NOT EXISTS `destinations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL UNIQUE,
  `country` varchar(100) NOT NULL,
  `description` longtext NULL,
  `attractions` json NULL,
  `best_time_to_visit` varchar(100) NULL,
  `visa_required` boolean DEFAULT false,
  `images` json NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 31. VISA_APPLICATIONS TABLE
CREATE TABLE IF NOT EXISTS `visa_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `destination_country` varchar(100) NOT NULL,
  `visa_type` varchar(50) NOT NULL,
  `application_date` date NULL,
  `approval_date` date NULL,
  `expiry_date` date NULL,
  `status` varchar(50) DEFAULT 'pending',
  `reference_number` varchar(100) NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 5: SPECIALIZED TRAVEL (HAJJ, UMRAH, TOURS) & EXPENSES
-- ============================================================

-- 32. HAJJ_PACKAGES TABLE
CREATE TABLE IF NOT EXISTS `hajj_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext NULL,
  `year` int unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `available_slots` int unsigned DEFAULT 0,
  `inclusions` json NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_year` (`year`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 33. UMRAH_PACKAGES TABLE
CREATE TABLE IF NOT EXISTS `umrah_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `available_slots` int unsigned DEFAULT 0,
  `inclusions` json NULL,
  `guide_name` varchar(255) NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 34. TOUR_PACKAGES TABLE
CREATE TABLE IF NOT EXISTS `tour_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `description` longtext NULL,
  `duration_days` int unsigned NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `max_participants` int unsigned DEFAULT 0,
  `itinerary` json NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_destination` (`destination`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 35. RITUAL_CHECKPOINTS TABLE
CREATE TABLE IF NOT EXISTS `ritual_checkpoints` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned NULL,
  `ritual_type` varchar(50) NOT NULL,
  `location` varchar(255) NOT NULL,
  `scheduled_date` date NULL,
  `completed_date` date NULL,
  `status` varchar(50) DEFAULT 'pending',
  `notes` text NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_ritual_type` (`ritual_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 36. EXPENSES TABLE
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned NULL,
  `category` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `date` date NOT NULL,
  `paid_by` bigint unsigned NULL,
  `approved_by` bigint unsigned NULL,
  `status` varchar(50) DEFAULT 'pending',
  `receipt_url` varchar(255) NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 37. SUPPLIERS TABLE
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `email` varchar(255) NULL,
  `phone` varchar(20) NULL,
  `address` text NULL,
  `city` varchar(100) NULL,
  `country` varchar(100) NULL,
  `contact_person` varchar(255) NULL,
  `rating` decimal(3,1) NULL,
  `is_active` boolean DEFAULT true,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_type` (`type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 38. COMPLAINTS TABLE
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `complaint_number` varchar(100) NOT NULL UNIQUE,
  `category` varchar(50) NOT NULL,
  `description` longtext NOT NULL,
  `severity` varchar(50) DEFAULT 'medium',
  `status` varchar(50) DEFAULT 'open',
  `resolution` longtext NULL,
  `resolved_date` timestamp NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 6: AUTOMATION ENGINE
-- ============================================================

-- 39. AUTOMATIONS TABLE
CREATE TABLE IF NOT EXISTS `automations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext NULL,
  `trigger_type` varchar(50) NOT NULL,
  `trigger_event` varchar(100) NOT NULL,
  `trigger_conditions` json NULL,
  `is_active` boolean DEFAULT true,
  `execution_count` int unsigned DEFAULT 0,
  `last_executed_at` timestamp NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_trigger_type` (`trigger_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 40. AUTOMATION_STEPS TABLE
CREATE TABLE IF NOT EXISTS `automation_steps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `automation_id` bigint unsigned NOT NULL,
  `sequence` int unsigned NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_data` json NOT NULL,
  `retry_count` int unsigned DEFAULT 0,
  `max_retries` int unsigned DEFAULT 3,
  `delay_seconds` int unsigned DEFAULT 0,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`automation_id`) REFERENCES `automations` (`id`) ON DELETE CASCADE,
  KEY `idx_automation_id` (`automation_id`),
  KEY `idx_sequence` (`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 41. AUTOMATION_TEMPLATES TABLE
CREATE TABLE IF NOT EXISTS `automation_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL UNIQUE,
  `description` longtext NULL,
  `template_data` json NOT NULL,
  `category` varchar(50) NOT NULL,
  `is_public` boolean DEFAULT false,
  `usage_count` int unsigned DEFAULT 0,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 42. AUTOMATION_LOGS TABLE
CREATE TABLE IF NOT EXISTS `automation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `automation_id` bigint unsigned NOT NULL,
  `execution_id` varchar(100) NOT NULL UNIQUE,
  `status` varchar(50) NOT NULL,
  `result_data` json NULL,
  `error_message` text NULL,
  `started_at` timestamp NULL,
  `completed_at` timestamp NULL,
  `duration_ms` int unsigned NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`automation_id`) REFERENCES `automations` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_automation_id` (`automation_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 43. AUTOMATION_DASHBOARDS TABLE
CREATE TABLE IF NOT EXISTS `automation_dashboards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `metrics` json NOT NULL,
  `widgets` json NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 44. WEBHOOK_EVENTS TABLE
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_data` json NOT NULL,
  `processed` boolean DEFAULT false,
  `processed_at` timestamp NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 7: ANALYTICS, REPORTS & BUSINESS INTELLIGENCE
-- ============================================================

-- 45. ANALYTICS TABLE
CREATE TABLE IF NOT EXISTS `analytics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `metric_type` varchar(100) NOT NULL,
  `metric_name` varchar(255) NOT NULL,
  `metric_value` decimal(15,2) NOT NULL,
  `dimension1` varchar(100) NULL,
  `dimension2` varchar(100) NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_metric_type` (`metric_type`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 46. REPORTS TABLE
CREATE TABLE IF NOT EXISTS `reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NULL,
  `report_data` json NULL,
  `generated_by` bigint unsigned NULL,
  `generated_at` timestamp NULL,
  `file_url` varchar(255) NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_report_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 47. REPORT_SCHEDULES TABLE
CREATE TABLE IF NOT EXISTS `report_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `report_id` bigint unsigned NULL,
  `name` varchar(255) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `frequency` varchar(50) NOT NULL,
  `scheduled_time` time NULL,
  `next_run_at` timestamp NULL,
  `is_active` boolean DEFAULT true,
  `recipients` json NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 48. DATA_INSIGHTS TABLE
CREATE TABLE IF NOT EXISTS `data_insights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `insight_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NULL,
  `insight_data` json NOT NULL,
  `confidence_score` decimal(3,2) NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_insight_type` (`insight_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 49. CUSTOMER_SEGMENTS TABLE
CREATE TABLE IF NOT EXISTS `customer_segments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` longtext NULL,
  `criteria` json NOT NULL,
  `member_count` int unsigned DEFAULT 0,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 50. PREDICTIONS TABLE
CREATE TABLE IF NOT EXISTS `predictions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NULL,
  `prediction_type` varchar(50) NOT NULL,
  `predicted_value` decimal(5,2) NOT NULL,
  `confidence_score` decimal(5,2) NOT NULL,
  `prediction_data` json NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_prediction_type` (`prediction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 51. DASHBOARDS TABLE
CREATE TABLE IF NOT EXISTS `dashboards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NULL,
  `layout` json NOT NULL,
  `widgets` json NULL,
  `is_default` boolean DEFAULT false,
  `created_by` bigint unsigned NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 8: OFFLINE & PWA SUPPORT
-- ============================================================

-- 52. OFFLINE_SYNCS TABLE
CREATE TABLE IF NOT EXISTS `offline_syncs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `sync_key` varchar(100) NOT NULL UNIQUE,
  `status` varchar(50) DEFAULT 'pending',
  `queued_changes` json NULL,
  `synced_at` timestamp NULL,
  `retry_count` int unsigned DEFAULT 0,
  `last_error` text NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 53. SYNC_QUEUES TABLE
CREATE TABLE IF NOT EXISTS `sync_queues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `offline_sync_id` bigint unsigned NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `operation` varchar(20) NOT NULL,
  `data` json NOT NULL,
  `status` varchar(50) DEFAULT 'queued',
  `processed_at` timestamp NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`offline_sync_id`) REFERENCES `offline_syncs` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 54. CACHE_POLICIES TABLE
CREATE TABLE IF NOT EXISTS `cache_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `cache_strategy` varchar(50) NOT NULL,
  `ttl_seconds` int unsigned NOT NULL,
  `max_age_seconds` int unsigned NULL,
  `is_active` boolean DEFAULT true,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_entity_type` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 55. PWA_SETTINGS TABLE
CREATE TABLE IF NOT EXISTS `pwa_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `app_name` varchar(255) NOT NULL,
  `app_description` text NULL,
  `app_icon_url` varchar(255) NULL,
  `theme_color` varchar(7) DEFAULT '#000000',
  `background_color` varchar(7) DEFAULT '#FFFFFF',
  `start_url` varchar(255) DEFAULT '/',
  `display_mode` varchar(50) DEFAULT 'standalone',
  `enable_offline` boolean DEFAULT true,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 56. SERVICE_WORKER_CACHES TABLE
CREATE TABLE IF NOT EXISTS `service_worker_caches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `cache_name` varchar(100) NOT NULL,
  `cache_version` int unsigned DEFAULT 1,
  `url_patterns` json NOT NULL,
  `is_active` boolean DEFAULT true,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_cache_name` (`cache_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 57. OFFLINE_DATA TABLE
CREATE TABLE IF NOT EXISTS `offline_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_data` json NOT NULL,
  `last_synced_at` timestamp NULL,
  `is_latest` boolean DEFAULT true,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_entity_type` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ADDITIONAL TABLES (PAYMENTS, NOTIFICATIONS, SETTINGS)
-- ============================================================

-- 58. PAYMENTS TABLE
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `payment_method` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `transaction_id` varchar(100) NULL UNIQUE,
  `paid_at` timestamp NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 59. NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `is_read` boolean DEFAULT false,
  `read_at` timestamp NULL,
  `created_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 60. SETTINGS TABLE
CREATE TABLE IF NOT EXISTS `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` bigint unsigned NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` longtext NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_tenant_key` (`tenant_id`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FINAL CONFIGURATION
-- ============================================================

SET FOREIGN_KEY_CHECKS=1;

-- ============================================================
-- SUMMARY
-- ============================================================
-- Total Tables: 60
-- Total Relations: 80+
-- Database: Production Ready
-- Last Updated: 2024-01-01
-- ============================================================
