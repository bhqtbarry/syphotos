/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.15-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: www_syphotos_cn
-- ------------------------------------------------------
-- Server version	10.11.15-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `airplane`
--

DROP TABLE IF EXISTS `airplane`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `airplane` (
  `icao24` varchar(10) NOT NULL,
  `timestamp` datetime DEFAULT NULL,
  `acars` tinyint(4) DEFAULT NULL,
  `adsb` tinyint(4) DEFAULT NULL,
  `built` varchar(20) DEFAULT NULL,
  `categoryDescription` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `engines` varchar(50) DEFAULT NULL,
  `firstFlightDate` varchar(20) DEFAULT NULL,
  `firstSeen` varchar(20) DEFAULT NULL,
  `icaoAircraftClass` varchar(20) DEFAULT NULL,
  `lineNumber` varchar(50) DEFAULT NULL,
  `manufacturerIcao` varchar(50) DEFAULT NULL,
  `manufacturerName` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `modes` tinyint(4) DEFAULT NULL,
  `nextReg` varchar(50) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `operator` varchar(100) DEFAULT NULL,
  `operatorCallsign` varchar(50) DEFAULT NULL,
  `operatorIata` varchar(10) DEFAULT NULL,
  `operatorIcao` varchar(10) DEFAULT NULL,
  `owner` varchar(100) DEFAULT NULL,
  `prevReg` varchar(50) DEFAULT NULL,
  `regUntil` varchar(20) DEFAULT NULL,
  `registered` varchar(20) DEFAULT NULL,
  `registration` varchar(50) DEFAULT NULL,
  `selCal` varchar(20) DEFAULT NULL,
  `serialNumber` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `typecode` varchar(20) DEFAULT NULL,
  `vdl` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`icao24`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `airport`
--

DROP TABLE IF EXISTS `airport`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `airport` (
  `id` bigint(20) NOT NULL,
  `ident` varchar(16) NOT NULL,
  `type` varchar(32) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `latitude_deg` double DEFAULT NULL,
  `longitude_deg` double DEFAULT NULL,
  `elevation_ft` int(11) DEFAULT NULL,
  `continent` char(2) DEFAULT NULL,
  `iso_country` char(2) DEFAULT NULL,
  `iso_region` varchar(10) DEFAULT NULL,
  `municipality` varchar(255) DEFAULT NULL,
  `scheduled_service` enum('yes','no') DEFAULT 'no',
  `icao_code` varchar(8) DEFAULT NULL,
  `iata_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gps_code` varchar(8) DEFAULT NULL,
  `local_code` varchar(8) DEFAULT NULL,
  `home_link` varchar(512) DEFAULT NULL,
  `wikipedia_link` varchar(512) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_airport_iata` (`iata_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '公告标题',
  `content` text NOT NULL COMMENT '公告内容',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '是否启用（1=启用，0=禁用）',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '创建时间',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='系统公告表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appeals`
--

DROP TABLE IF EXISTS `appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appeals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `photo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `response` text DEFAULT NULL COMMENT '管理员回复（拒绝理由/通过说明）',
  `admin_comment` text DEFAULT NULL COMMENT '管理员处理申诉时给用户的留言',
  `status` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL COMMENT '申诉处理时间',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `photo_id` (`photo_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `appeals_ibfk_1` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appeals_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=414 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_clients`
--

DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(64) NOT NULL COMMENT '客户端唯一标识（随机生成）',
  `client_secret` varchar(128) NOT NULL COMMENT '客户端密钥（加密存储）',
  `client_name` varchar(100) NOT NULL COMMENT '第三方网站名称（如：XX航空社区）',
  `redirect_uri` varchar(255) NOT NULL COMMENT '授权成功后的回调地址（必须是客户端备案的地址）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1-启用，0-禁用',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_client_id` (`client_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='第三方客户端注册表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_codes`
--

DROP TABLE IF EXISTS `oauth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL COMMENT '临时授权码（随机生成）',
  `client_id` varchar(64) NOT NULL COMMENT '关联的客户端ID',
  `user_id` int(11) NOT NULL COMMENT '授权的用户ID（关联syphotos的users表）',
  `expired_at` datetime NOT NULL COMMENT '过期时间',
  `used` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已使用：1-已用，0-未用',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_client_user` (`client_id`,`user_id`),
  KEY `idx_expired_used` (`expired_at`,`used`)
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='授权码表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `photo_likes`
--

DROP TABLE IF EXISTS `photo_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photo_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `photo_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_photo` (`user_id`,`photo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=459 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `photos`
--

DROP TABLE IF EXISTS `photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `aircraft_model` varchar(50) NOT NULL,
  `registration_number` varchar(50) NOT NULL,
  `拍摄时间` datetime DEFAULT current_timestamp(),
  `拍摄地点` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `score` int(11) DEFAULT 0,
  `rejection_reason` text DEFAULT NULL,
  `admin_comment` text DEFAULT NULL COMMENT '管理员审核时给用户的留言',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `views` int(11) NOT NULL DEFAULT 0 COMMENT '图片浏览量',
  `likes` int(11) NOT NULL DEFAULT 0 COMMENT '图片点赞量',
  `allow_use` tinyint(1) NOT NULL DEFAULT 0,
  `watermark_size` int(11) NOT NULL DEFAULT 24,
  `watermark_opacity` int(11) NOT NULL DEFAULT 50,
  `watermark_position` varchar(50) NOT NULL DEFAULT 'bottom-right',
  `actual_watermark_size` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为精选图片：0=否，1=是',
  `original_width` int(11) NOT NULL DEFAULT 0 COMMENT '图片原始宽度（像素）',
  `original_height` int(11) NOT NULL DEFAULT 0 COMMENT '图片原始高度（像素）',
  `final_width` int(11) NOT NULL DEFAULT 0 COMMENT '图片处理后最终宽度（像素）',
  `final_height` int(11) NOT NULL DEFAULT 0 COMMENT '图片处理后最终高度（像素）',
  `shooting_time` datetime NOT NULL DEFAULT current_timestamp(),
  `shooting_location` varchar(255) NOT NULL DEFAULT '',
  `Cam` varchar(100) DEFAULT NULL,
  `Lens` varchar(255) DEFAULT NULL,
  `FocalLength` int(11) DEFAULT NULL,
  `ISO` int(11) DEFAULT NULL,
  `F` float DEFAULT NULL,
  `Shutter` varchar(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `fk_photos_reviewer` (`reviewer_id`),
  KEY `idx_photos_location` (`拍摄地点`),
  CONSTRAINT `fk_photos_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5575 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `runway`
--

DROP TABLE IF EXISTS `runway`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `runway` (
  `id` bigint(20) NOT NULL,
  `airport_ref` bigint(20) NOT NULL,
  `airport_ident` varchar(16) DEFAULT NULL,
  `length_ft` int(11) DEFAULT NULL,
  `width_ft` int(11) DEFAULT NULL,
  `surface` varchar(32) DEFAULT NULL,
  `lighted` tinyint(1) DEFAULT NULL,
  `closed` tinyint(1) DEFAULT NULL,
  `le_ident` varchar(8) DEFAULT NULL,
  `le_latitude_deg` double DEFAULT NULL,
  `le_longitude_deg` double DEFAULT NULL,
  `le_elevation_ft` int(11) DEFAULT NULL,
  `le_heading_degT` double DEFAULT NULL,
  `le_displaced_threshold_ft` int(11) DEFAULT NULL,
  `he_ident` varchar(8) DEFAULT NULL,
  `he_latitude_deg` double DEFAULT NULL,
  `he_longitude_deg` double DEFAULT NULL,
  `he_elevation_ft` int(11) DEFAULT NULL,
  `he_heading_degT` double DEFAULT NULL,
  `he_displaced_threshold_ft` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_active` timestamp NULL DEFAULT NULL,
  `is_banned` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否封禁：0=正常，1=已封禁',
  `qq_openid` varchar(64) DEFAULT NULL COMMENT 'QQ聚合登录openid',
  `remember_token` varchar(255) DEFAULT NULL COMMENT '记住我功能的验证令牌',
  `social_uid` varchar(64) DEFAULT NULL COMMENT '第三方登录唯一ID',
  `social_type` varchar(20) DEFAULT NULL COMMENT '第三方登录方式',
  `updated_at` datetime DEFAULT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_token_created_at` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `sys_admin` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `qq_openid` (`qq_openid`)
) ENGINE=InnoDB AUTO_INCREMENT=134183 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_all`
--

DROP TABLE IF EXISTS `v_all`;
/*!50001 DROP VIEW IF EXISTS `v_all`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_all` AS SELECT
 1 AS `id`,
  1 AS `user_id`,
  1 AS `reviewer_id`,
  1 AS `title`,
  1 AS `category`,
  1 AS `aircraft_model`,
  1 AS `registration_number`,
  1 AS `shottime`,
  1 AS `location`,
  1 AS `filename`,
  1 AS `approved`,
  1 AS `score`,
  1 AS `rejection_reason`,
  1 AS `admin_comment`,
  1 AS `created_at`,
  1 AS `views`,
  1 AS `likes`,
  1 AS `allow_use`,
  1 AS `watermark_size`,
  1 AS `watermark_opacity`,
  1 AS `watermark_position`,
  1 AS `actual_watermark_size`,
  1 AS `is_featured`,
  1 AS `original_width`,
  1 AS `original_height`,
  1 AS `final_width`,
  1 AS `final_height`,
  1 AS `shooting_time`,
  1 AS `shooting_location`,
  1 AS `Cam`,
  1 AS `Lens`,
  1 AS `FocalLength`,
  1 AS `ISO`,
  1 AS `F`,
  1 AS `Shutter`,
  1 AS `username`,
  1 AS `adminName` */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `v_all`
--

/*!50001 DROP VIEW IF EXISTS `v_all`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`sa`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_all` AS select `photos`.`id` AS `id`,`photos`.`user_id` AS `user_id`,`photos`.`reviewer_id` AS `reviewer_id`,`photos`.`title` AS `title`,`photos`.`category` AS `category`,`photos`.`aircraft_model` AS `aircraft_model`,`photos`.`registration_number` AS `registration_number`,`photos`.`拍摄时间` AS `shottime`,`photos`.`拍摄地点` AS `location`,`photos`.`filename` AS `filename`,`photos`.`approved` AS `approved`,`photos`.`score` AS `score`,`photos`.`rejection_reason` AS `rejection_reason`,`photos`.`admin_comment` AS `admin_comment`,`photos`.`created_at` AS `created_at`,`photos`.`views` AS `views`,`photos`.`likes` AS `likes`,`photos`.`allow_use` AS `allow_use`,`photos`.`watermark_size` AS `watermark_size`,`photos`.`watermark_opacity` AS `watermark_opacity`,`photos`.`watermark_position` AS `watermark_position`,`photos`.`actual_watermark_size` AS `actual_watermark_size`,`photos`.`is_featured` AS `is_featured`,`photos`.`original_width` AS `original_width`,`photos`.`original_height` AS `original_height`,`photos`.`final_width` AS `final_width`,`photos`.`final_height` AS `final_height`,`photos`.`shooting_time` AS `shooting_time`,`photos`.`shooting_location` AS `shooting_location`,`photos`.`Cam` AS `Cam`,`photos`.`Lens` AS `Lens`,`photos`.`FocalLength` AS `FocalLength`,`photos`.`ISO` AS `ISO`,`photos`.`F` AS `F`,`photos`.`Shutter` AS `Shutter`,`users`.`username` AS `username`,`usera`.`username` AS `adminName` from ((`photos` join `users` on(`photos`.`user_id` = `users`.`id`)) join `users` `usera` on(`photos`.`reviewer_id` = `usera`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-12  9:17:18
