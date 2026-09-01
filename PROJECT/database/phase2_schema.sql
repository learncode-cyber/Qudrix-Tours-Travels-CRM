-- Phase 2 Database Schema for QUDRIX CRM
-- Added tables: quotations, quotation_items, proposals, deal_stages, sales_activities

ALTER TABLE `leads` ADD COLUMN `estimated_value` DECIMAL(12, 2) DEFAULT 0;
ALTER TABLE `leads` ADD COLUMN `conversion_probability` INT DEFAULT 0;

CREATE TABLE `quotations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `quotation_number` VARCHAR(50) UNIQUE NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `description` LONGTEXT,
  `status` VARCHAR(50) NOT NULL DEFAULT 'draft',
  `subtotal` DECIMAL(12, 2) DEFAULT 0,
  `tax_amount` DECIMAL(12, 2) DEFAULT 0,
  `discount_amount` DECIMAL(12, 2) DEFAULT 0,
  `total_amount` DECIMAL(12, 2) DEFAULT 0,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `valid_until` TIMESTAMP NULL,
  `payment_terms` JSON,
  `notes` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_lead_id` (`lead_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quotation_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `quotation_id` BIGINT UNSIGNED NOT NULL,
  `package_id` BIGINT UNSIGNED,
  `description` VARCHAR(255) NOT NULL,
  `quantity` INT DEFAULT 1,
  `unit_price` DECIMAL(12, 2) NOT NULL,
  `tax_rate` DECIMAL(5, 2) DEFAULT 0,
  `discount` DECIMAL(12, 2) DEFAULT 0,
  `total` DECIMAL(12, 2) NOT NULL,
  
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE SET NULL,
  
  INDEX `idx_quotation_id` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposals` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `quotation_id` BIGINT UNSIGNED NOT NULL,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `proposal_number` VARCHAR(50) UNIQUE NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'draft',
  `title` VARCHAR(255) NOT NULL,
  `description` LONGTEXT,
  `proposal_date` TIMESTAMP NULL,
  `expiry_date` TIMESTAMP NULL,
  `sent_date` TIMESTAMP NULL,
  `signed_date` TIMESTAMP NULL,
  `notes` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_lead_id` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `deal_stages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `stage` VARCHAR(50) NOT NULL,
  `entered_at` TIMESTAMP NULL,
  `exited_at` TIMESTAMP NULL,
  `duration_days` INT,
  `notes` VARCHAR(255),
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_lead_id` (`lead_id`),
  INDEX `idx_stage` (`stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales_activities` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `activity_type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` LONGTEXT,
  `activity_date` TIMESTAMP NULL,
  `outcome` VARCHAR(50),
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_lead_id` (`lead_id`),
  INDEX `idx_activity_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
