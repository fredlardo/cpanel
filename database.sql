-- =====================================================
-- DOXERA+ - Base de données MySQL (conversion Prisma)
-- Généré pour phpMyAdmin - moteur InnoDB / utf8mb4
-- =====================================================
CREATE DATABASE IF NOT EXISTS `doxera_plus` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `doxera_plus`;

SET FOREIGN_KEY_CHECKS=0;

-- ---------- Hiérarchie ----------
CREATE TABLE IF NOT EXISTS `entities` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `organizations` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `entityId` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`entityId`) REFERENCES `entities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `divisions` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `organizationId` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`organizationId`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `services` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `divisionId` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`divisionId`) REFERENCES `divisions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Tables de paramétrage ----------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `genres` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dossier_types` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `priorities` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `level` INT DEFAULT 0,
  `color` VARCHAR(20) DEFAULT '#6b7280',
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `confidentiality_levels` (
  `id` VARCHAR(30) PRIMARY KEY,
  `level` INT UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `color` VARCHAR(20) DEFAULT '#6b7280',
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `visibility_levels` (
  `id` VARCHAR(30) PRIMARY KEY,
  `level` INT UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `person_roles` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `groups` (
  `id` VARCHAR(30) PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Utilisateurs ----------
CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(30) PRIMARY KEY,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `login` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `firstName` VARCHAR(100) NOT NULL,
  `lastName` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `role` ENUM('ADMIN','SUPERVISOR','USER') DEFAULT 'USER',
  `profile` VARCHAR(50) DEFAULT 'STANDARD',
  `confidentialityLevel` INT DEFAULT 1,
  `visibilityLevel` INT DEFAULT 5,
  `modules` JSON NULL,
  `serviceId` VARCHAR(30) NOT NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`serviceId`) REFERENCES `services`(`id`),
  INDEX idx_users_service (`serviceId`),
  INDEX idx_users_login (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_service_access` (
  `id` VARCHAR(30) PRIMARY KEY,
  `userId` VARCHAR(30) NOT NULL,
  `serviceId` VARCHAR(30) NOT NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_service (`userId`,`serviceId`),
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`serviceId`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `group_members` (
  `id` VARCHAR(30) PRIMARY KEY,
  `groupId` VARCHAR(30) NOT NULL,
  `userId` VARCHAR(30) NOT NULL,
  UNIQUE KEY uniq_group_user (`groupId`,`userId`),
  FOREIGN KEY (`groupId`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Templates ----------
CREATE TABLE IF NOT EXISTS `dossier_templates` (
  `id` VARCHAR(30) PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `categoryId` VARCHAR(30) NOT NULL,
  `genreId` VARCHAR(30) NOT NULL,
  `dossierTypeId` VARCHAR(30) NOT NULL,
  `priorityId` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`categoryId`) REFERENCES `categories`(`id`),
  FOREIGN KEY (`genreId`) REFERENCES `genres`(`id`),
  FOREIGN KEY (`dossierTypeId`) REFERENCES `dossier_types`(`id`),
  FOREIGN KEY (`priorityId`) REFERENCES `priorities`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dossier_template_steps` (
  `id` VARCHAR(30) PRIMARY KEY,
  `order` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `templateId` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`templateId`) REFERENCES `dossier_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Dossiers ----------
CREATE TABLE IF NOT EXISTS `dossiers` (
  `id` VARCHAR(30) PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `reference` VARCHAR(100) UNIQUE NOT NULL,
  `status` ENUM('NOUVEAU','EN_COURS','EN_ATTENTE','CLOS','ANNULE','REJETE') DEFAULT 'NOUVEAU',
  `deadline` DATETIME NULL,
  `closureComment` TEXT NULL,
  `deletedAt` DATETIME NULL,
  `archivedAt` DATETIME NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `serviceId` VARCHAR(30) NOT NULL,
  `categoryId` VARCHAR(30) NOT NULL,
  `genreId` VARCHAR(30) NOT NULL,
  `dossierTypeId` VARCHAR(30) NOT NULL,
  `priorityId` VARCHAR(30) NOT NULL,
  `confidentialityId` VARCHAR(30) NOT NULL,
  `visibilityId` VARCHAR(30) NULL,
  `creatorId` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`serviceId`) REFERENCES `services`(`id`),
  FOREIGN KEY (`categoryId`) REFERENCES `categories`(`id`),
  FOREIGN KEY (`genreId`) REFERENCES `genres`(`id`),
  FOREIGN KEY (`dossierTypeId`) REFERENCES `dossier_types`(`id`),
  FOREIGN KEY (`priorityId`) REFERENCES `priorities`(`id`),
  FOREIGN KEY (`confidentialityId`) REFERENCES `confidentiality_levels`(`id`),
  FOREIGN KEY (`visibilityId`) REFERENCES `visibility_levels`(`id`),
  FOREIGN KEY (`creatorId`) REFERENCES `users`(`id`),
  INDEX idx_dossiers_status (`status`),
  INDEX idx_dossiers_service (`serviceId`),
  INDEX idx_dossiers_creator (`creatorId`),
  INDEX idx_dossiers_deleted (`deletedAt`),
  INDEX idx_dossiers_archived (`archivedAt`),
  INDEX idx_dossiers_deadline (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dossier_persons` (
  `id` VARCHAR(30) PRIMARY KEY,
  `dossierId` VARCHAR(30) NOT NULL,
  `userId` VARCHAR(30) NOT NULL,
  `roleId` VARCHAR(30) NOT NULL,
  UNIQUE KEY uniq_dossier_user_role (`dossierId`,`userId`,`roleId`),
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`),
  FOREIGN KEY (`roleId`) REFERENCES `person_roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `documents` (
  `id` VARCHAR(30) PRIMARY KEY,
  `fileName` VARCHAR(255) NOT NULL,
  `originalName` VARCHAR(255) NOT NULL,
  `mimeType` VARCHAR(100) NOT NULL,
  `fileSize` INT NOT NULL,
  `filePath` VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `deletedAt` DATETIME NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `dossierId` VARCHAR(30) NOT NULL,
  `uploadedById` VARCHAR(30) NULL,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploadedById`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transmissions` (
  `id` VARCHAR(30) PRIMARY KEY,
  `recommendations` TEXT NULL,
  `priority` VARCHAR(50) NULL,
  `deadline` DATETIME NULL,
  `comment` TEXT NULL,
  `status` VARCHAR(30) DEFAULT 'ENVOYE',
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `dossierId` VARCHAR(30) NOT NULL,
  `senderId` VARCHAR(30) NOT NULL,
  `receiverId` VARCHAR(30) NULL,
  `targetServiceId` VARCHAR(30) NULL,
  `targetDivisionId` VARCHAR(30) NULL,
  `targetGroupId` VARCHAR(30) NULL,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`senderId`) REFERENCES `users`(`id`),
  FOREIGN KEY (`receiverId`) REFERENCES `users`(`id`),
  FOREIGN KEY (`targetServiceId`) REFERENCES `services`(`id`),
  FOREIGN KEY (`targetDivisionId`) REFERENCES `divisions`(`id`),
  FOREIGN KEY (`targetGroupId`) REFERENCES `groups`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `action_logs` (
  `id` VARCHAR(30) PRIMARY KEY,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dossierId` VARCHAR(30) NOT NULL,
  `userId` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`),
  INDEX idx_logs_user (`userId`),
  INDEX idx_logs_dossier (`dossierId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` VARCHAR(30) PRIMARY KEY,
  `type` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `isRead` TINYINT(1) DEFAULT 0,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `dossierId` VARCHAR(30) NOT NULL,
  `userId` VARCHAR(30) NOT NULL,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX idx_notif_user_read (`userId`,`isRead`),
  INDEX idx_notif_dossier (`dossierId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Workflow ----------
CREATE TABLE IF NOT EXISTS `dossier_workflows` (
  `id` VARCHAR(30) PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `status` VARCHAR(30) DEFAULT 'EN_COURS',
  `startedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completedAt` DATETIME NULL,
  `dossierId` VARCHAR(30) UNIQUE NOT NULL,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dossier_workflow_steps` (
  `id` VARCHAR(30) PRIMARY KEY,
  `order` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `status` ENUM('EN_ATTENTE','EN_COURS','VALIDE','REJETE') DEFAULT 'EN_ATTENTE',
  `comment` TEXT NULL,
  `deadlineDays` INT NULL,
  `validatedAt` DATETIME NULL,
  `ipAddress` VARCHAR(45) NULL,
  `workflowId` VARCHAR(30) NOT NULL,
  `validatorId` VARCHAR(30) NULL,
  FOREIGN KEY (`workflowId`) REFERENCES `dossier_workflows`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`validatorId`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;

-- =====================================================
-- DONNÉES DE DÉMONSTRATION (équivalent seed.ts)
-- =====================================================
-- Entité / Organisation / Divisions / Services
-- Users: admin/admin123, jdupont, mmartin, pdurand
-- Le mot de passe est hashé bcrypt - à générer via PHP password_hash()
-- =====================================================
