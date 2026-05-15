-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: toys4us
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.24.04.1

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
-- Table structure for table `address`
--

DROP TABLE IF EXISTS `address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `address` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `address`
--

LOCK TABLES `address` WRITE;
/*!40000 ALTER TABLE `address` DISABLE KEYS */;
/*!40000 ALTER TABLE `address` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_item`
--

DROP TABLE IF EXISTS `cart_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`user_id`,`product_id`),
  KEY `fk_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_item`
--



--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` VALUES (1,'Plush Toys','plush-toys',NULL,'2026-05-11 07:11:38'),(2,'Action Figures','action-figures',NULL,'2026-05-11 07:11:38'),(3,'Board Games','board-games','','2026-05-11 07:11:38'),(4,'Outdoor','outdoor',NULL,'2026-05-11 07:11:38'),(5,'Educational','educational','','2026-05-11 07:11:38');
/*!40000 ALTER TABLE `category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customplush`
--

DROP TABLE IF EXISTS `customplush`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customplush` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `base_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'User-given name for the plush',
  `voice_message_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Path to stored TTS audio file',
  `total_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cplush_user` (`user_id`),
  KEY `fk_cplush_base` (`base_id`),
  CONSTRAINT `fk_cplush_base` FOREIGN KEY (`base_id`) REFERENCES `plushbase` (`id`),
  CONSTRAINT `fk_cplush_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;



--
-- Table structure for table `customplushaccesory`
--

DROP TABLE IF EXISTS `customplushaccesory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customplushaccesory` (
  `custom_plush_id` int NOT NULL,
  `accessory_id` int NOT NULL,
  PRIMARY KEY (`custom_plush_id`,`accessory_id`),
  KEY `fk_cpa_accessory` (`accessory_id`),
  CONSTRAINT `fk_cpa_accessory` FOREIGN KEY (`accessory_id`) REFERENCES `plushaccessory` (`id`),
  CONSTRAINT `fk_cpa_plush` FOREIGN KEY (`custom_plush_id`) REFERENCES `customplush` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customplushaccesory`
--

LOCK TABLES `customplushaccesory` WRITE;
/*!40000 ALTER TABLE `customplushaccesory` DISABLE KEYS */;
/*!40000 ALTER TABLE `customplushaccesory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customplushaccessory`
--

DROP TABLE IF EXISTS `customplushaccessory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customplushaccessory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customplush_id` int NOT NULL,
  `accessory_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customplushaccessory`
--

LOCK TABLES `customplushaccessory` WRITE;
/*!40000 ALTER TABLE `customplushaccessory` DISABLE KEYS */;
INSERT INTO `customplushaccessory` VALUES (1,2,5),(2,2,4),(6,16,2),(7,16,1),(8,16,4),(24,17,3),(25,17,1),(26,17,4);
/*!40000 ALTER TABLE `customplushaccessory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order`
--

DROP TABLE IF EXISTS `order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `address` text COLLATE utf8mb4_general_ci,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `province` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stripe_payment_id` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ord_user` (`user_id`),
  CONSTRAINT `fk_ord_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `order_item`
--

DROP TABLE IF EXISTS `order_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `custom_plush_id` int DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_oi_order` (`order_id`),
  KEY `fk_oi_product` (`product_id`),
  KEY `fk_oi_plush` (`custom_plush_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_plush` FOREIGN KEY (`custom_plush_id`) REFERENCES `customplush` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `plushaccessory`
--

DROP TABLE IF EXISTS `plushaccessory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plushaccessory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `category` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'bodywear, shoes or facewear',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plushaccessory`
--

LOCK TABLES `plushaccessory` WRITE;
/*!40000 ALTER TABLE `plushaccessory` DISABLE KEYS */;
INSERT INTO `plushaccessory` VALUES (1,'Bow','assets/accessories/bow.png',2.99,'bodywear',1),(2,'Glasses','assets/accessories/glasses.png',2.99,'facewear',1),(3,'Hat','assets/accessories/hat.png',2.99,'facewear',1),(4,'Pants','assets/accessories/pants.png',2.99,'shoes',1),(5,'Pink Bow','assets/accessories/pinkHeadBow.png',2.99,'facewear',1);
/*!40000 ALTER TABLE `plushaccessory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plushbase`
--

DROP TABLE IF EXISTS `plushbase`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plushbase` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `species` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'bear',
  `color` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'brown',
  `image_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plushbase`
--

LOCK TABLES `plushbase` WRITE;
/*!40000 ALTER TABLE `plushbase` DISABLE KEYS */;
INSERT INTO `plushbase` VALUES (1,'Brown Bear','bear','brown','assets/bases/bear.png',14.99,1),(2,'Black Bear','bear','black','assets/bases/bear_black.png',14.99,1),(3,'Blue Bear','bear','blue','assets/bases/bear_blue.png',14.99,1),(4,'Green Bear','bear','green','assets/bases/bear_green.png',14.99,1),(5,'Orange Bear','bear','orange','assets/bases/bear_orange.png',14.99,1),(6,'Pink Bear','bear','pink','assets/bases/bear_pink.png',14.99,1),(7,'Purple Bear','bear','purple','assets/bases/bear_purple.png',14.99,1),(8,'White Bear','bear','white','assets/bases/bear_white.png',14.99,1),(9,'Yellow Bear','bear','yellow','assets/bases/bear_yellow.png',14.99,1),(10,'Black Bunny','bunny','black','assets/bases/bunny_black.png',14.99,1),(11,'Brown Bunny','bunny','brown','assets/bases/bunny_brown.png',14.99,1),(12,'Green Bunny','bunny','green','assets/bases/bunny_green.png',14.99,1),(13,'Orange Bunny','bunny','orange','assets/bases/bunny_orange.png',14.99,1),(14,'Pink Bunny','bunny','pink','assets/bases/bunny_pink.png',14.99,1),(15,'Purple Bunny','bunny','purple','assets/bases/bunny_purple.png',14.99,1),(16,'White Bunny','bunny','white','assets/bases/bunny_white.png',14.99,1);
/*!40000 ALTER TABLE `plushbase` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product`
--

DROP TABLE IF EXISTS `product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `price` decimal(10,2) DEFAULT NULL,
  `rating` float DEFAULT '0',
  `category_id` int DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_prod_category` (`category_id`),
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product`
--

LOCK TABLES `product` WRITE;
/*!40000 ALTER TABLE `product` DISABLE KEYS */;
INSERT INTO `product` VALUES (1,'LEGO Star Wars Set','Build your own starship with 800+ pieces',59.99,5,5,0,1,'https://m.media-amazon.com/images/I/81U5AGCxTEL._AC_UF894,1000_QL80_.jpg','2026-05-11 07:14:40'),(2,'Remote Control Car','Fast RC buggy with rechargeable battery',34.99,2,4,0,1,'https://m.media-amazon.com/images/I/710wxOs32yL._AC_UF894,1000_QL80_.jpg','2026-05-11 07:14:40'),(3,'Teddy Bear','Soft plush bear, 18 inches tall',24.99,4,1,0,1,'https://i.ebayimg.com/images/g/-hkAAOSw4ohi4OJT/s-l1200.jpg','2026-05-11 07:14:40'),(4,'10 Game Set','Classic family board game collection',29.99,3.6,3,0,1,'https://www.funcarnival.com/cdn/shop/products/N1445.jpg?v=1654888729','2026-05-11 07:14:40'),(5,'Art & Craft Kit','200-piece art set for creative kids',19.99,3,5,0,1,'https://m.media-amazon.com/images/I/81TVx2oKnTL._AC_US1400_.jpg','2026-05-11 07:14:40'),(6,'Puzzle 1000pc','Beautiful landscape jigsaw puzzle',14.99,3,3,0,1,'https://www.urbannaturestore.ca/cdn/shop/files/OhCanada_1000PieceJigsawPuzzle_1.jpg?v=1708622689&width=1214','2026-05-11 07:14:40'),(7,'Gaming laptop','to play your favorite steam games on',499.00,4,5,5,1,'https://m.media-amazon.com/images/I/71sgAr9atBS._AC_UF894,1000_QL80_.jpg','2026-05-12 12:20:07'),(8,'Spider-Man Toy','a really good action figure for the best person ever',49.99,5,2,90,1,'https://m.media-amazon.com/images/I/61YWZBsxBVL.jpg','2026-05-14 05:53:38'),(9,'meow','meow',12.00,0,1,2,1,'','2026-05-14 14:26:21');
/*!40000 ALTER TABLE `product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review`
--

DROP TABLE IF EXISTS `review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `title` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `comment` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_rev_product` (`product_id`),
  KEY `fk_rev_user` (`user_id`),
  CONSTRAINT `fk_rev_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review`
--

LOCK TABLES `review` WRITE;
/*!40000 ALTER TABLE `review` DISABLE KEYS */;
/*!40000 ALTER TABLE `review` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'user',
  `totp_secret` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-15  2:58:50
