-- DOXERA+ v2 — Extensions GED complètes
USE `doxera_plus`;
SET FOREIGN_KEY_CHECKS=0;

-- Organisation : tags + sous-dossiers (parentId)
CREATE TABLE IF NOT EXISTS `tags` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20) DEFAULT '#3b82f6',
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dossier_tags` (
  `id` VARCHAR(30) PRIMARY KEY,
  `dossierId` VARCHAR(30) NOT NULL,
  `tagId` VARCHAR(30) NOT NULL,
  UNIQUE KEY uniq_dossier_tag (`dossierId`,`tagId`),
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tagId`) REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- sous-dossiers hiérarchie
ALTER TABLE `dossiers` ADD COLUMN IF NOT EXISTS `parentId` VARCHAR(30) NULL AFTER `id`,
  ADD INDEX IF NOT EXISTS idx_dossiers_parent (`parentId`),
  ADD CONSTRAINT IF NOT EXISTS fk_dossiers_parent FOREIGN KEY (`parentId`) REFERENCES `dossiers`(`id`) ON DELETE SET NULL;

-- métadonnées personnalisées
CREATE TABLE IF NOT EXISTS `metadata_fields` (
  `id` VARCHAR(30) PRIMARY KEY,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('text','number','date','select') DEFAULT 'text',
  `options` JSON NULL,
  `isRequired` TINYINT(1) DEFAULT 0,
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dossier_metadata` (
  `id` VARCHAR(30) PRIMARY KEY,
  `dossierId` VARCHAR(30) NOT NULL,
  `fieldId` VARCHAR(30) NOT NULL,
  `value` TEXT NULL,
  UNIQUE KEY uniq_dossier_field (`dossierId`,`fieldId`),
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`fieldId`) REFERENCES `metadata_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Numérisation : ocr texte
ALTER TABLE `documents` ADD COLUMN IF NOT EXISTS `ocrText` MEDIUMTEXT NULL AFTER `filePath`,
  ADD COLUMN IF NOT EXISTS `version` INT DEFAULT 1 AFTER `fileSize`,
  ADD COLUMN IF NOT EXISTS `isLocked` TINYINT(1) DEFAULT 0 AFTER `version`,
  ADD COLUMN IF NOT EXISTS `lockedById` VARCHAR(30) NULL AFTER `isLocked`;

-- Gestion des versions
CREATE TABLE IF NOT EXISTS `document_versions` (
  `id` VARCHAR(30) PRIMARY KEY,
  `documentId` VARCHAR(30) NOT NULL,
  `version` INT NOT NULL,
  `fileName` VARCHAR(255) NOT NULL,
  `filePath` VARCHAR(500) NOT NULL,
  `fileSize` INT NOT NULL,
  `ocrText` MEDIUMTEXT NULL,
  `uploadedById` VARCHAR(30) NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`documentId`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploadedById`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Collaboration : commentaires + tâches
CREATE TABLE IF NOT EXISTS `dossier_comments` (
  `id` VARCHAR(30) PRIMARY KEY,
  `dossierId` VARCHAR(30) NOT NULL,
  `userId` VARCHAR(30) NOT NULL,
  `parentId` VARCHAR(30) NULL,
  `content` TEXT NOT NULL,
  `mentions` JSON NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`),
  FOREIGN KEY (`parentId`) REFERENCES `dossier_comments`(`id`) ON DELETE CASCADE,
  INDEX idx_comments_dossier (`dossierId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dossier_tasks` (
  `id` VARCHAR(30) PRIMARY KEY,
  `dossierId` VARCHAR(30) NOT NULL,
  `creatorId` VARCHAR(30) NOT NULL,
  `assigneeId` VARCHAR(30) NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('A_FAIRE','EN_COURS','TERMINE','ANNULE') DEFAULT 'A_FAIRE',
  `dueDate` DATETIME NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`creatorId`) REFERENCES `users`(`id`),
  FOREIGN KEY (`assigneeId`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Partage : liens sécurisés
CREATE TABLE IF NOT EXISTS `share_links` (
  `id` VARCHAR(30) PRIMARY KEY,
  `token` VARCHAR(64) UNIQUE NOT NULL,
  `dossierId` VARCHAR(30) NULL,
  `documentId` VARCHAR(30) NULL,
  `password` VARCHAR(255) NULL,
  `expiresAt` DATETIME NULL,
  `allowDownload` TINYINT(1) DEFAULT 1,
  `maxViews` INT NULL,
  `views` INT DEFAULT 0,
  `createdById` VARCHAR(30) NOT NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`documentId`) REFERENCES `documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recherche : historique + enregistrées
CREATE TABLE IF NOT EXISTS `search_history` (
  `id` VARCHAR(30) PRIMARY KEY,
  `userId` VARCHAR(30) NOT NULL,
  `query` VARCHAR(500) NOT NULL,
  `filters` JSON NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `saved_searches` (
  `id` VARCHAR(30) PRIMARY KEY,
  `userId` VARCHAR(30) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `query` VARCHAR(500) NOT NULL,
  `filters` JSON NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Archivage : politiques + gel juridique
CREATE TABLE IF NOT EXISTS `retention_policies` (
  `id` VARCHAR(30) PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `durationDays` INT NOT NULL,
  `action` ENUM('ARCHIVER','DETRUIRE','TRANSFERER') DEFAULT 'ARCHIVER',
  `isActive` TINYINT(1) DEFAULT 1,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `dossiers` ADD COLUMN IF NOT EXISTS `retentionPolicyId` VARCHAR(30) NULL AFTER `archivedAt`,
  ADD COLUMN IF NOT EXISTS `legalHold` TINYINT(1) DEFAULT 0 AFTER `retentionPolicyId`,
  ADD COLUMN IF NOT EXISTS `retentionUntil` DATETIME NULL AFTER `legalHold`;

-- Sécurité : MFA
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `mfaEnabled` TINYINT(1) DEFAULT 0 AFTER `modules`,
  ADD COLUMN IF NOT EXISTS `mfaSecret` VARCHAR(64) NULL AFTER `mfaEnabled`;

-- Acquisition : imports
CREATE TABLE IF NOT EXISTS `acquisition_imports` (
  `id` VARCHAR(30) PRIMARY KEY,
  `source` ENUM('dragdrop','scanner','email','mobile','watched','bulk') DEFAULT 'dragdrop',
  `fileName` VARCHAR(255) NOT NULL,
  `status` ENUM('EN_ATTENTE','TRAITE','ERREUR') DEFAULT 'EN_ATTENTE',
  `dossierId` VARCHAR(30) NULL,
  `userId` VARCHAR(30) NOT NULL,
  `createdAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dossierId`) REFERENCES `dossiers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `tags` (`id`,`code`,`name`,`color`) VALUES
 ('tag001','URGENT','Urgent','#ef4444'),('tag002','CONFIDENTIEL','Confidentiel','#7c3aed'),('tag003','A_VALIDER','À valider','#f59e0b'),('tag004','ARCHIVE','Archive','#64748b');
INSERT IGNORE INTO `retention_policies` (`id`,`code`,`name`,`durationDays`,`action`) VALUES
 ('ret001','5ANS','Conservation 5 ans',1825,'ARCHIVER'),('ret002','10ANS','Conservation 10 ans',3650,'ARCHIVER'),('ret003','DESTR_3','Destruction 3 ans',1095,'DETRUIRE');
INSERT IGNORE INTO `metadata_fields` (`id`,`code`,`name`,`type`,`isRequired`) VALUES
 ('mf001','REF_EXT','Référence externe','text',0),('mf002','MONTANT','Montant','number',0),('mf003','DATE_SIGN','Date signature','date',0);

SET FOREIGN_KEY_CHECKS=1;
