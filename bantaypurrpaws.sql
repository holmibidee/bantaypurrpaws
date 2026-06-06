-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: sql103.infinityfree.com    Database: bantaypurrpaws
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `adoption_applications`
--

DROP TABLE IF EXISTS `adoption_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adoption_applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pet_id` int NOT NULL,
  `user_id` int NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `contact_number` varchar(512) NOT NULL,
  `email` varchar(512) NOT NULL,
  `address` text,
  `occupation` varchar(100) NOT NULL,
  `reason_for_adoption` text,
  `home_type` varchar(80) DEFAULT NULL,
  `existing_pets` enum('yes','no') NOT NULL,
  `agreement` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `schedule_date` date DEFAULT NULL,
  `schedule_time` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pet_id` (`pet_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `adoption_applications_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `adoption_applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `adoption_applications`
--

LOCK TABLES `adoption_applications` WRITE;
/*!40000 ALTER TABLE `adoption_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `adoption_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `notification_type` varchar(32) NOT NULL DEFAULT 'adoption',
  `message` varchar(255) NOT NULL,
  `link_url` varchar(512) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `adoption_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,NULL,NULL,'system','Welcome to BantayPurrPaws! Your email was verified.',NULL,0,'2026-06-05 15:40:09'),(2,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 15:50:17'),(3,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 17:09:34'),(4,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 17:54:05'),(5,NULL,NULL,'system','Welcome to BantayPurrPaws! Your email was verified.',NULL,0,'2026-06-05 18:26:02'),(6,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 19:09:35'),(7,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 19:18:22'),(8,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 19:32:16'),(9,NULL,NULL,'system','Welcome to BantayPurrPaws! Your email was verified.',NULL,0,'2026-06-05 19:45:57'),(11,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 20:24:10'),(12,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 23:39:07'),(13,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 23:55:45'),(14,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-05 23:56:56'),(15,NULL,NULL,'announcement','Sample Announcement','announcements.php',0,'2026-06-06 00:21:26'),(16,NULL,NULL,'system','Welcome to BantayPurrPaws! Your email was verified.',NULL,1,'2026-06-06 00:26:01'),(17,NULL,NULL,'report','Watashi Wa submitted rescue report BPP-FAD5DC4F','admin/reports.php?q=BPP-FAD5DC4F',1,'2026-06-06 00:33:19'),(18,NULL,1,'system','You logged in successfully.',NULL,0,'2026-06-06 01:15:19'),(19,NULL,1,'system','You logged in successfully.',NULL,0,'2026-06-06 01:23:08'),(20,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-06 01:24:15'),(21,NULL,NULL,'system','Your account permissions have been updated by an administrator. Please re-login to apply the changes.',NULL,0,'2026-06-06 01:25:31'),(22,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-06 01:26:23'),(23,NULL,11,'system','Welcome to BantayPurrPaws! Your email was verified.',NULL,0,'2026-06-06 01:30:28'),(26,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-06 01:55:32'),(27,NULL,NULL,'system','Your account permissions have been updated by an administrator. Please re-login to apply the changes.',NULL,0,'2026-06-06 01:56:11'),(28,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-06 01:57:51'),(29,NULL,NULL,'system','Your account permissions have been updated by an administrator. Please re-login to apply the changes.',NULL,0,'2026-06-06 01:58:38'),(30,NULL,NULL,'system','You logged in successfully.',NULL,0,'2026-06-06 02:00:18'),(31,NULL,12,'system','You logged in successfully.',NULL,0,'2026-06-06 02:27:56'),(32,NULL,12,'system','Your account permissions have been updated by an administrator. Please re-login to apply the changes.',NULL,0,'2026-06-06 02:28:22'),(33,NULL,12,'system','Your account permissions have been updated by an administrator. Please re-login to apply the changes.',NULL,0,'2026-06-06 02:28:47'),(34,NULL,12,'system','Your account permissions have been updated by an administrator. Please re-login to apply the changes.',NULL,0,'2026-06-06 02:28:58'),(35,NULL,11,'announcement','Test Announcement role - Wally B on the house','announcements.php',1,'2026-06-06 02:29:22'),(36,NULL,12,'announcement','Test Announcement role - Wally B on the house','announcements.php',0,'2026-06-06 02:29:23'),(37,NULL,11,'system','You logged in successfully.',NULL,0,'2026-06-06 02:31:08'),(38,NULL,NULL,'report','Daniel Caesar submitted rescue report BPP-61B4656E','admin/reports.php?q=BPP-61B4656E',0,'2026-06-06 03:10:52'),(39,NULL,1,'system','You logged in successfully.',NULL,0,'2026-06-06 03:13:18'),(40,NULL,NULL,'report','Daniel Caesar submitted rescue report BPP-F298788A','admin/reports.php?q=BPP-F298788A',0,'2026-06-06 03:20:35');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_tokens`
--

DROP TABLE IF EXISTS `otp_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `otp_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `otp_code` char(6) NOT NULL,
  `purpose` enum('registration','password_reset','google_link','profile_update','email_change_current','email_change_new','staff_invite') NOT NULL DEFAULT 'registration',
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_email_purpose` (`email`,`purpose`),
  KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_tokens`
--

LOCK TABLES `otp_tokens` WRITE;
/*!40000 ALTER TABLE `otp_tokens` DISABLE KEYS */;
INSERT INTO `otp_tokens` VALUES (1,'test+otp@example.com','973924','registration','2026-06-05 15:51:44',1,'2026-06-05 15:36:44'),(2,'test+otp@example.com','910920','registration','2026-06-05 15:51:58',1,'2026-06-05 15:36:58'),(3,'anthony.domasig@evsu.edu.ph','572603','registration','2026-06-05 15:54:54',1,'2026-06-05 15:39:54'),(4,'anthony.domasig@evsu.edu.ph','071578','registration','2026-06-05 16:04:34',1,'2026-06-05 15:49:34'),(5,'anthony.domasig@evsu.edu.ph','123618','registration','2026-06-05 16:08:16',1,'2026-06-05 15:53:16'),(6,'anthony.domasig@evsu.edu.ph','222324','registration','2026-06-05 16:11:35',1,'2026-06-05 15:56:35'),(7,'anthony.domasig@evsu.edu.ph','278251','registration','2026-06-05 16:17:17',1,'2026-06-05 16:02:17'),(8,'anthony.domasig@evsu.edu.ph','198172','registration','2026-06-05 16:25:31',1,'2026-06-05 16:10:31'),(9,'anthony.domasig@evsu.edu.ph','851636','registration','2026-06-05 16:25:51',1,'2026-06-05 16:10:51'),(10,'anthony.domasig@evsu.edu.ph','240594','registration','2026-06-05 16:29:16',1,'2026-06-05 16:14:16'),(11,'anthony.domasig@evsu.edu.ph','390566','registration','2026-06-05 16:31:46',1,'2026-06-05 16:16:46'),(12,'anthony.domasig@evsu.edu.ph','452817','registration','2026-06-05 16:32:28',1,'2026-06-05 16:17:28'),(13,'anthony.domasig@evsu.edu.ph','964250','registration','2026-06-05 16:34:01',1,'2026-06-05 16:19:01'),(14,'anthony.domasig@evsu.edu.ph','373600','registration','2026-06-05 16:44:09',1,'2026-06-05 16:29:09'),(15,'anthony.domasig@evsu.edu.ph','869074','registration','2026-06-05 16:45:51',1,'2026-06-05 16:30:51'),(16,'anthony.domasig@evsu.edu.ph','689507','registration','2026-06-05 16:47:19',1,'2026-06-05 16:32:19'),(17,'anthony.domasig@evsu.edu.ph','961265','registration','2026-06-05 16:48:58',1,'2026-06-05 16:33:58'),(18,'anthony.domasig@evsu.edu.ph','914570','registration','2026-06-05 16:57:11',1,'2026-06-05 16:42:11'),(19,'anthony.domasig@evsu.edu.ph','025379','registration','2026-06-05 17:11:53',1,'2026-06-05 16:56:53'),(20,'anthony.domasig@evsu.edu.ph','172502','registration','2026-06-05 17:22:54',1,'2026-06-05 17:07:54'),(21,'anthony.domasig@evsu.edu.ph','757074','registration','2026-06-05 17:25:58',1,'2026-06-05 17:10:58'),(22,'anthony.domasig@evsu.edu.ph','038487','registration','2026-06-05 18:08:17',1,'2026-06-05 17:53:17'),(23,'domasiganthony139@gmail.com','835515','registration','2026-06-05 18:40:39',1,'2026-06-05 18:25:39'),(24,'anthony.domasig@evsu.edu.ph','602231','registration','2026-06-05 19:24:03',1,'2026-06-05 19:09:03'),(25,'anthony.domasig@evsu.edu.ph','539145','registration','2026-06-05 19:32:51',1,'2026-06-05 19:17:51'),(26,'domasiganthony139@gmail.com','883029','registration','2026-06-05 19:43:18',1,'2026-06-05 19:28:18'),(27,'domasiganthony139@gmail.com','180597','registration','2026-06-05 19:44:13',1,'2026-06-05 19:29:13'),(28,'anthony.domasig@evsu.edu.ph','100248','registration','2026-06-05 19:46:47',1,'2026-06-05 19:31:47'),(29,'domasiganthony139@gmail.com','460700','registration','2026-06-05 19:59:59',1,'2026-06-05 19:44:59'),(30,'domasiganthony139@gmail.com','982789','profile_update','2026-06-05 20:36:06',1,'2026-06-05 20:21:06'),(31,'anthony.domasig@evsu.edu.ph','500145','registration','2026-06-05 20:38:53',1,'2026-06-05 20:23:53'),(32,'anthony.domasig@evsu.edu.ph','951434','registration','2026-06-05 23:53:26',1,'2026-06-05 23:38:26'),(33,'riccselling05@gmail.com','363594','registration','2026-06-06 00:10:22',1,'2026-06-05 23:55:22'),(34,'riccselling05@gmail.com','367540','registration','2026-06-06 00:11:33',1,'2026-06-05 23:56:33'),(35,'domasiganthony139@gmail.com','654011','registration','2026-06-06 00:37:31',1,'2026-06-06 00:22:31'),(36,'domasiganthony139@gmail.com','916620','registration','2026-06-06 00:40:03',1,'2026-06-06 00:25:03'),(37,'domasiganthony139@gmail.com','584894','profile_update','2026-06-06 00:45:44',1,'2026-06-06 00:30:44'),(38,'anthony.domasig@evsu.edu.ph','885269','registration','2026-06-06 01:28:15',1,'2026-06-06 01:13:15'),(39,'anthony.domasig@evsu.edu.ph','044924','registration','2026-06-06 01:37:48',1,'2026-06-06 01:22:48'),(40,'riccselling05@gmail.com','195350','registration','2026-06-06 01:38:55',1,'2026-06-06 01:23:55'),(41,'riccselling05@gmail.com','242387','registration','2026-06-06 01:40:49',1,'2026-06-06 01:25:49'),(42,'domasiganthony139@gmail.com','902517','registration','2026-06-06 01:44:09',1,'2026-06-06 01:29:09'),(43,'domasiganthony139@gmail.com','401307','profile_update','2026-06-06 01:45:59',1,'2026-06-06 01:30:59'),(44,'riccselling05@gmail.com','198310','registration','2026-06-06 02:07:20',1,'2026-06-06 01:52:20'),(45,'riccselling05@gmail.com','902291','password_reset','2026-06-06 02:07:54',1,'2026-06-06 01:52:54'),(46,'riccselling05@gmail.com','521359','registration','2026-06-06 02:09:28',1,'2026-06-06 01:54:28'),(47,'riccselling05@gmail.com','040493','registration','2026-06-06 02:11:45',1,'2026-06-06 01:56:45'),(48,'riccselling05@gmail.com','874296','registration','2026-06-06 02:12:18',1,'2026-06-06 01:57:18'),(49,'riccselling05@gmail.com','207387','registration','2026-06-06 02:14:56',1,'2026-06-06 01:59:56'),(50,'riccselling05@gmail.com','997437','registration','2026-06-06 02:25:33',1,'2026-06-06 02:10:33'),(51,'riccselling05@gmail.com','265308','password_reset','2026-06-06 02:25:57',1,'2026-06-06 02:10:57'),(52,'riccselling05@gmail.com','387091','registration','2026-06-06 02:26:27',1,'2026-06-06 02:11:27'),(53,'riccselling05@gmail.com','450151','password_reset','2026-06-06 02:30:29',1,'2026-06-06 02:15:29'),(54,'riccselling05@gmail.com','871225','registration','2026-06-06 02:40:30',1,'2026-06-06 02:25:30'),(55,'riccselling05@gmail.com','179512','password_reset','2026-06-06 02:41:25',1,'2026-06-06 02:26:25'),(56,'riccselling05@gmail.com','709045','registration','2026-06-06 02:42:33',1,'2026-06-06 02:27:33'),(57,'domasiganthony139@gmail.com','400654','registration','2026-06-06 02:45:09',1,'2026-06-06 02:30:09'),(58,'domasiganthony139@gmail.com','823425','registration','2026-06-06 02:45:46',1,'2026-06-06 02:30:46'),(59,'anthony.domasig@evsu.edu.ph','620933','registration','2026-06-06 03:26:20',1,'2026-06-06 03:11:20'),(60,'anthony.domasig@evsu.edu.ph','550062','password_reset','2026-06-06 03:26:46',1,'2026-06-06 03:11:46'),(61,'anthony.domasig@evsu.edu.ph','226189','registration','2026-06-06 03:27:53',1,'2026-06-06 03:12:53');
/*!40000 ALTER TABLE `otp_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pet_images`
--

DROP TABLE IF EXISTS `pet_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pet_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pet_id` int NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pet_id` (`pet_id`),
  CONSTRAINT `pet_images_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pet_images`
--

LOCK TABLES `pet_images` WRITE;
/*!40000 ALTER TABLE `pet_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `pet_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pets`
--

DROP TABLE IF EXISTS `pets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `breed` varchar(100) NOT NULL,
  `age` varchar(50) NOT NULL,
  `gender` enum('Male','Female','Unknown') NOT NULL DEFAULT 'Unknown',
  `vaccination_status` varchar(150) DEFAULT NULL,
  `health_condition` text,
  `description` text,
  `adoption_requirements` text,
  `rescue_date` date DEFAULT NULL,
  `status` enum('available','pending_adoption','adopted') NOT NULL DEFAULT 'available',
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pets`
--

LOCK TABLES `pets` WRITE;
/*!40000 ALTER TABLE `pets` DISABLE KEYS */;
INSERT INTO `pets` VALUES (4,'Pete','Raged Barbarian','5','Male','Raged, Antivenom','Super healthy','Always easily ragebaited','','2026-04-06','available','uploads/pets/pet_6a2392d1655b70.89075610.webp','2026-06-06 03:24:01','2026-06-06 03:24:01');
/*!40000 ALTER TABLE `pets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_logs`
--

DROP TABLE IF EXISTS `report_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `updated_by` int NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `report_logs_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `rescue_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_logs_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_logs`
--

LOCK TABLES `report_logs` WRITE;
/*!40000 ALTER TABLE `report_logs` DISABLE KEYS */;
INSERT INTO `report_logs` VALUES (3,4,11,NULL,'pending','Report submitted by user.','2026-06-06 03:10:51'),(4,5,11,NULL,'pending','Report submitted by user.','2026-06-06 03:20:35'),(5,5,1,'pending','in_progress',NULL,'2026-06-06 03:20:45'),(6,5,1,'in_progress','rescued',NULL,'2026-06-06 03:20:52'),(7,4,1,'pending','failed',NULL,'2026-06-06 03:21:02'),(8,3,1,'pending','in_progress',NULL,'2026-06-06 03:21:12'),(9,3,1,'in_progress','rescued',NULL,'2026-06-06 03:21:16'),(10,2,1,'pending','in_progress',NULL,'2026-06-06 03:21:25'),(11,2,1,'in_progress','rescued',NULL,'2026-06-06 03:21:31');
/*!40000 ALTER TABLE `report_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rescue_reports`
--

DROP TABLE IF EXISTS `rescue_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rescue_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_code` varchar(20) NOT NULL,
  `reporter_id` int NOT NULL,
  `reporter_name` varchar(150) NOT NULL,
  `contact_number` varchar(512) NOT NULL,
  `animal_type` varchar(100) DEFAULT NULL,
  `location` text NOT NULL,
  `description` text,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','in_progress','rescued','failed') NOT NULL DEFAULT 'pending',
  `assigned_to` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_code` (`report_code`),
  KEY `reporter_id` (`reporter_id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `rescue_reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rescue_reports_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rescue_reports`
--

LOCK TABLES `rescue_reports` WRITE;
/*!40000 ALTER TABLE `rescue_reports` DISABLE KEYS */;
INSERT INTO `rescue_reports` VALUES (2,'BPP-8B282FA1',11,'Daniel Caesar','enc:v1:c6FUDITzAVZ/UuECuqi3VerF48OkOiGOMGnhgFzQnK4/t7hNAyn4XxgonBHu/jyo74w+',NULL,'California, USA','Pet need help','uploads/reports/report_6a2386d92e0a16.55595438.jpg','rescued',NULL,'2026-06-06 02:32:57','2026-06-06 03:21:31'),(3,'BPP-F2FCF1F9',11,'Daniel Caesar','enc:v1:BsvUFSvWK7/1EO4IgVCumUNePS2DVWqoxZ4zMOS1AUjgjP1Yg0sqgdyDpkX2uedJn0lo',NULL,'California, USA','Test Reporting','uploads/reports/report_6a238b5f9a6959.72346277.jpg','rescued',NULL,'2026-06-06 02:52:15','2026-06-06 03:21:16'),(4,'BPP-61B4656E',11,'Daniel Caesar','enc:v1:+p911TImAOjBS7i/C9yk9MBWUjGrY+g8fK2P4r00YuVC5Lgy2hlAII+a2xGjGpg6sHkD',NULL,'California, USA','Need help','uploads/reports/report_6a238fbbf2a6b9.38846882.webp','failed',NULL,'2026-06-06 03:10:51','2026-06-06 03:21:02'),(5,'BPP-F298788A',11,'Daniel Caesar','enc:v1:rva47LT4U6LgQOihQp2Ji5ZyJ/v2Xo5aUoAf1mbtcUWPS3KNIDg6Mo7j4q9pYFUOwM3t','Cat','California, USA','Mwehehehe','uploads/reports/report_6a239203e7bf09.85140913.jpg','rescued',NULL,'2026-06-06 03:20:35','2026-06-06 03:20:52');
/*!40000 ALTER TABLE `rescue_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_invites`
--

DROP TABLE IF EXISTS `staff_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_invites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `permissions` json DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_invite_token` (`token`),
  KEY `idx_invite_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_invites`
--

LOCK TABLES `staff_invites` WRITE;
/*!40000 ALTER TABLE `staff_invites` DISABLE KEYS */;
INSERT INTO `staff_invites` VALUES (1,'riccselling05@gmail.com','4b9f4929e85738214a1c7a06bc180e939986c2cac43e7072bfab38ddf2b8c46e','[\"manage_pets\", \"view_adoptions\"]','2026-06-06 23:53:01',1,4,'2026-06-05 23:53:01'),(2,'riccselling05@gmail.com','b80f7810891aaa4c6a3e9f7dd805eb7fbe2a25bb7c6cffe85c4eee91253e0ca8','[\"manage_pets\", \"post_announcements\"]','2026-06-07 01:19:02',1,1,'2026-06-06 01:19:02'),(3,'riccselling05@gmail.com','c54d35daefcf0ab98ec094110c8ee47be8516e6b794c6a3a9a6174fb444be317','[\"manage_reports\", \"review_adoptions\"]','2026-06-07 01:23:17',1,1,'2026-06-06 01:23:17'),(4,'riccselling05@gmail.com','dd2c08bcf1da83765d2921c087332a597739acef38e4a47b8baa3cc52cf0da02','[]','2026-06-07 02:09:42',1,1,'2026-06-06 02:09:42');
/*!40000 ALTER TABLE `staff_invites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(512) NOT NULL,
  `email_hash` varchar(64) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('user','staff','admin') NOT NULL DEFAULT 'user',
  `google_id` varchar(128) DEFAULT NULL,
  `avatar_url` varchar(512) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `auth_provider` enum('local','google') NOT NULL DEFAULT 'local',
  `username` varchar(50) DEFAULT NULL,
  `phone_number` varchar(512) DEFAULT NULL,
  `profile_picture` varchar(512) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `staff_permissions` longtext,
  `permissions_changed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email_hash` (`email_hash`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Administrator','enc:v1:F/JBu4ljDqrZiAEz8+ce0fFPofsNH+gBu5s+UYonG6nVVN7I74RSkr+iFnrafPjO0JHfmkvLPAiQjuew/T2d2GOBXw==','c3ebf11075da34a687252b6fdaaa71be7a79afe4e37442a4a8e9ed2894fd4166','$2y$10$WRzdb5lztzmoJuE8glPmhedy3lLbUNaq775gjfyL95GHfeku8s9Da','admin',NULL,NULL,1,'local',NULL,NULL,NULL,'2026-06-06 01:12:47','2026-06-06 03:12:39',NULL,NULL),(11,'Daniel Caesar','enc:v1:TnrJySjBDOzHbBW5pNc3eslyheJqkVMttBCOmFNA9NCC+B66mScOJZWnbLATAObV1Ez9ejotZ3d9eXDbCp2mx0r8qw==','9fac7bca2eb1983976a9a7473833ea90951537925a0259e8529efcc0a76024c2','$2y$10$Bfy4kbuJtGndWIPZ7yRq7eXDElNkm7kLEiJMf3g0YcOFtBApkdtc2','user',NULL,NULL,1,'local',NULL,'enc:v1:f+J56Yj5mRUwtgLtBCX9N8yOQ92pkcAo7Z9cS3AA61Ly8k9/HU6lQV55SHLyM9+5vyes',NULL,'2026-06-06 01:30:28','2026-06-06 01:31:49',NULL,NULL),(12,'Wally Bayola','enc:v1:6FFRmna/Lc23FbtmEUSp+9THSnfHzsDIHVqi8lcmcs6IOC2iBoQIJYU/JCz4b16dwLpZli6RU39dLBKfv8he','303a551d6dd0fb4411bf3724b9292779b57297f362493fc87bf94079212f8735','$2y$10$0JXqYQUML36o0Y7rSicP8e1TLsL.fRgnRd54futjXXbpiz7c0L0sy','staff',NULL,NULL,1,'local','wallyb',NULL,NULL,'2026-06-06 02:10:26','2026-06-06 02:28:58','[\"manage_reports\",\"post_announcements\"]','2026-06-06 02:28:58');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-06 11:27:41
