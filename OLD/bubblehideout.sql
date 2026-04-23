-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2025 at 01:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bubblehideout`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CheckIngredientAvailability` (IN `p_menu_item_id` VARCHAR(50), IN `p_size_id` VARCHAR(50), IN `p_quantity` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_ingredient_id INT;
    DECLARE v_required_qty DECIMAL(10,3);
    DECLARE v_total_needed DECIMAL(10,3);
    DECLARE v_current_stock DECIMAL(10,3);
    DECLARE v_ingredient_name VARCHAR(255);
    DECLARE v_unit VARCHAR(50);
    DECLARE v_availability_status VARCHAR(20);
    
    DECLARE ingredient_cursor CURSOR FOR
        SELECT ums.ingredient_id, ums.quantity_required, ui.item_name, ui.current_quantity, ui.unit
        FROM unified_menu_system ums
        JOIN unified_inventory ui ON ums.ingredient_id = ui.item_id
        WHERE ums.menu_item_id = p_menu_item_id 
        AND ums.size_id = p_size_id
        AND ums.ingredient_id IS NOT NULL;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Create temporary table for results
    DROP TEMPORARY TABLE IF EXISTS temp_availability;
    CREATE TEMPORARY TABLE temp_availability (
        ingredient_name VARCHAR(255),
        required_quantity DECIMAL(10,3),
        current_stock DECIMAL(10,3),
        unit VARCHAR(50),
        status VARCHAR(20)
    );
    
    OPEN ingredient_cursor;
    
    availability_loop:LOOP
        FETCH ingredient_cursor INTO v_ingredient_id, v_required_qty, v_ingredient_name, v_current_stock, v_unit;
        IF done THEN
            LEAVE availability_loop;
        END IF;
        
        SET v_total_needed = v_required_qty * p_quantity;
        
        IF v_current_stock >= v_total_needed THEN
            SET v_availability_status = 'AVAILABLE';
        ELSE
            SET v_availability_status = 'INSUFFICIENT';
        END IF;
        
        INSERT INTO temp_availability VALUES (
            v_ingredient_name,
            v_total_needed,
            v_current_stock,
            v_unit,
            v_availability_status
        );
        
    END LOOP;
    
    CLOSE ingredient_cursor;
    
    -- Return results
    SELECT * FROM temp_availability;
    
    DROP TEMPORARY TABLE temp_availability;
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ProcessOrderWithDeduction` (IN `p_menu_item_id` VARCHAR(50), IN `p_size_id` VARCHAR(50), IN `p_quantity` INT, IN `p_order_id` VARCHAR(50))   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_ingredient_id INT;
    DECLARE v_required_qty DECIMAL(10,3);
    DECLARE v_total_deduction DECIMAL(10,3);
    DECLARE v_current_stock DECIMAL(10,3);
    DECLARE v_ingredient_name VARCHAR(255);
    DECLARE v_is_liquid TINYINT(1);
    DECLARE v_unit VARCHAR(50);
    DECLARE v_error_message TEXT;
    
    DECLARE ingredient_cursor CURSOR FOR
        SELECT ums.ingredient_id, ums.quantity_required, ui.item_name, ui.is_liquid, ui.unit
        FROM unified_menu_system ums
        JOIN unified_inventory ui ON ums.ingredient_id = ui.item_id
        WHERE ums.menu_item_id = p_menu_item_id 
        AND ums.size_id = p_size_id
        AND ums.ingredient_id IS NOT NULL;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Check if all ingredients are available first
    OPEN ingredient_cursor;
    
    check_loop: LOOP
        FETCH ingredient_cursor INTO v_ingredient_id, v_required_qty, v_ingredient_name, v_is_liquid, v_unit;
        IF done THEN
            LEAVE check_loop;
        END IF;
        
        SET v_total_deduction = v_required_qty * p_quantity;
        
        SELECT current_quantity INTO v_current_stock 
        FROM unified_inventory 
        WHERE item_id = v_ingredient_id;
        
        IF v_current_stock < v_total_deduction THEN
            SET v_error_message = CONCAT('Insufficient stock for: ', v_ingredient_name, '. Need ', v_total_deduction, ' ', v_unit, ' but only have ', v_current_stock, ' ', v_unit, '.');
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = v_error_message;
        END IF;
        
    END LOOP;
    
    CLOSE ingredient_cursor;
    
    -- Reset cursor for actual deduction
    SET done = FALSE;
    OPEN ingredient_cursor;
    
    deduct_loop: LOOP
        FETCH ingredient_cursor INTO v_ingredient_id, v_required_qty, v_ingredient_name, v_is_liquid, v_unit;
        IF done THEN
            LEAVE deduct_loop;
        END IF;
        
        SET v_total_deduction = v_required_qty * p_quantity;
        
        -- Deduct from inventory
        UPDATE unified_inventory 
        SET current_quantity = current_quantity - v_total_deduction
        WHERE item_id = v_ingredient_id;
        
    END LOOP;
    
    CLOSE ingredient_cursor;
    
    -- Return success message
    SELECT CONCAT('Order ', p_order_id, ' processed successfully for ', p_quantity, ' x ', p_menu_item_id, ' (', p_size_id, ')') AS success_message;
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RestockInventory` (IN `p_item_name` VARCHAR(255), IN `p_quantity` DECIMAL(10,3))   BEGIN
    DECLARE v_item_exists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO v_item_exists
    FROM unified_inventory
    WHERE item_name = p_item_name;
    
    IF v_item_exists > 0 THEN
        UPDATE unified_inventory
        SET current_quantity = current_quantity + p_quantity,
            last_updated = CURRENT_TIMESTAMP
        WHERE item_name = p_item_name;
        
        SELECT CONCAT('Successfully restocked ', p_quantity, ' units of ', p_item_name) AS message;
    ELSE
        SELECT CONCAT('Item not found: ', p_item_name) AS error;
    END IF;
    
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `GetMenuPrice` (`p_menu_item_id` VARCHAR(50), `p_size_id` VARCHAR(50)) RETURNS DECIMAL(10,2) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_price DECIMAL(10,2) DEFAULT 0.00;
    
    SELECT DISTINCT price INTO v_price
    FROM unified_menu_system
    WHERE menu_item_id = p_menu_item_id 
    AND size_id = p_size_id
    LIMIT 1;
    
    RETURN IFNULL(v_price, 0.00);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `ACC_ID` int(11) NOT NULL,
  `FNAME` varchar(50) DEFAULT NULL,
  `LNAME` varchar(50) DEFAULT NULL,
  `ROLE` varchar(10) NOT NULL,
  `STATUS` varchar(20) NOT NULL,
  `EMAIL` varchar(50) DEFAULT NULL,
  `ORIG_PASS` varchar(60) NOT NULL,
  `PASS` varchar(255) DEFAULT NULL,
  `CNTC_NUM` varchar(15) DEFAULT NULL,
  `BDAY` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`ACC_ID`, `FNAME`, `LNAME`, `ROLE`, `STATUS`, `EMAIL`, `ORIG_PASS`, `PASS`, `CNTC_NUM`, `BDAY`) VALUES
(101, 'Leon', 'Emano', 'Admin', 'Online', 'admin@bh', 'bubble', '$2y$10$OGjG35JWzaioMgOyGf5WZeYEpnV1Y5pLya.2nYpI/DNFnjGDDfi8K', '09995205719', '2004-04-30'),
(102, 'Mecha', 'Escasinas', 'Employee', 'Online', 'mecha@bhemployee', 'bubblehideout', '$2y$10$s33GMPCMgkboxRI4guGi9uVfDGdQWP8nmqY6QCtOFpfACaBsNX8am', '09995205717', '2003-11-14'),
(103, 'Matt', 'Maniego', 'Employee', 'Offline', 'matt@bhemployee', 'bubblehideout', '$2y$10$U4vZoveEwafSWTcK69BTaenW7U7Mts2g/dE4p.sC/BA87AhCzq0fy', '09995205714', '2004-04-30'),
(125, 'John', 'Plat', 'Admin', 'Offline', 'plat@email.com', 'bubble', '$2y$10$bJekwOpp7VmNzISuq4smdesSOlESk0lo.aeGreibpUpSTIoX238uS', '123456789', '2003-04-03'),
(133, 'Senku', 'Ishigami', 'Admin', 'Offline', 'senku@bh', 'bubble', '$2y$10$zWGW/l7LxJ3xIypAt4psSedP6TdlsLKX.7MIZgyFMxvi2r.rboQ5u', '1234567789', '2000-05-03');

-- --------------------------------------------------------

--
-- Table structure for table `acc_archive`
--

CREATE TABLE `acc_archive` (
  `ID` int(11) NOT NULL,
  `ACC_ID` int(11) DEFAULT NULL,
  `FNAME` varchar(50) DEFAULT NULL,
  `LNAME` varchar(50) DEFAULT NULL,
  `ROLE` varchar(10) NOT NULL,
  `EMAIL` varchar(50) DEFAULT NULL,
  `ORIG_PASS` varchar(60) NOT NULL,
  `PASS` varchar(255) DEFAULT NULL,
  `CNTC_NUM` varchar(15) DEFAULT NULL,
  `BDAY` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `acc_archive`
--

INSERT INTO `acc_archive` (`ID`, `ACC_ID`, `FNAME`, `LNAME`, `ROLE`, `EMAIL`, `ORIG_PASS`, `PASS`, `CNTC_NUM`, `BDAY`) VALUES
(23, 130, 'Alexandra', 'Ilagan', 'Admin', 'ilagan@bh', 'alexa', '$2y$10$3phfvceeyZUB/fXwpII1NeTTiHnwSPsbYOnvB7lmNIFw2lo4nuswy', '09995874444', '0000-00-00'),
(24, 128, 'John', 'Nicolas', 'Employee', 'nicolas.john@bhemployee', 'bubblehideout', '$2y$10$SghxmdX4uCkNBk93u08rzeYJoHL52P19o.BcTHt94bInJVL86GcsW', '09995205712', '2004-04-30'),
(26, 131, 'Sample', 'Emano', 'Admin', 'emano@bh', 'password', '$2y$10$bXdpfKoN6P90h2oiPx/ks.Ctj14xSEr4TeyoTh4LrbnHELpmWuat2', '1234567898', '2004-03-04'),
(27, 127, 'Ria', 'Augusto', 'Admin', 'ria@bh', 'bubble', '$2y$10$qgenoPRQ6nT10flb8Ql25.1hmf6JCZHuia3F.doiqbAVEfCTWdcVm', '123456789', '2004-07-09');

-- --------------------------------------------------------

--
-- Table structure for table `admin_key`
--

CREATE TABLE `admin_key` (
  `ID` int(11) NOT NULL,
  `ADMIN_KEY` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_key`
--

INSERT INTO `admin_key` (`ID`, `ADMIN_KEY`) VALUES
(1, '7263admin##key200430'),
(2, '5680admin##key200311');

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

CREATE TABLE `customer_orders` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `order_type` enum('dine_in','takeout','delivery') NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL,
  `discount_type` varchar(50) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `status` enum('Pending','Confirmed','Preparing','Order Ready','Completed','cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_orders`
--

INSERT INTO `customer_orders` (`id`, `order_id`, `order_type`, `total`, `discount`, `discount_type`, `amount_paid`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ORD6663', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 13:37:53', '2025-10-17 13:59:31'),
(2, 'ORD8386', 'dine_in', 207.00, 0.00, NULL, 0.00, '', '2025-08-12 13:56:08', '2025-10-17 13:59:31'),
(3, 'ORD0593', 'dine_in', 238.00, 0.00, NULL, 0.00, '', '2025-08-12 14:00:23', '2025-10-17 13:59:31'),
(4, 'ORD8073', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:01:02', '2025-10-17 13:59:31'),
(5, 'ORD9945', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:13:04', '2025-10-17 13:59:31'),
(6, 'ORD6995', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:16:39', '2025-10-17 13:59:31'),
(7, 'ORD0269', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:20:09', '2025-10-17 13:59:31'),
(8, 'ORD0792', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:25:39', '2025-10-17 13:59:31'),
(9, 'ORD4100', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:26:11', '2025-10-17 13:59:31'),
(10, 'ORD0563', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:27:00', '2025-10-17 13:59:31'),
(11, 'ORD5731', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:27:39', '2025-10-17 13:59:31'),
(12, 'ORD7795', 'dine_in', 118.00, 0.00, NULL, 0.00, '', '2025-08-12 14:32:32', '2025-10-17 13:59:31'),
(13, 'ORD9527', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:34:12', '2025-10-17 13:59:31'),
(14, 'ORD7146', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:38:08', '2025-10-17 13:59:31'),
(15, 'ORD0540', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:41:56', '2025-10-17 13:59:31'),
(16, 'ORD9463', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:43:14', '2025-10-17 13:59:31'),
(17, 'ORD3621', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:43:36', '2025-10-17 13:59:31'),
(18, 'ORD5047', 'dine_in', 99.00, 0.00, NULL, 0.00, '', '2025-08-12 14:44:16', '2025-10-17 13:59:31'),
(19, 'ORD7910', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:45:06', '2025-10-17 13:59:31'),
(20, 'ORD7477', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:54:02', '2025-10-17 13:59:31'),
(21, 'ORD2336', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:57:04', '2025-10-17 13:59:31'),
(22, 'ORD2553', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:59:02', '2025-10-17 13:59:31'),
(23, 'ORD5633', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:02:17', '2025-10-17 13:59:31'),
(24, 'ORD0447', 'dine_in', 138.00, 0.00, NULL, 0.00, '', '2025-08-12 15:08:27', '2025-10-17 13:59:31'),
(25, 'ORD7595', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:12:10', '2025-10-17 13:59:31'),
(26, 'ORD1636', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:28:28', '2025-10-17 13:59:31'),
(27, 'ORD8580', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:31:28', '2025-10-17 13:59:31'),
(28, 'ORD4489', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:32:54', '2025-10-17 13:59:31'),
(29, 'ORD7927', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:33:29', '2025-10-17 13:59:31'),
(30, 'ORD4852', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:46:54', '2025-10-17 13:59:31'),
(31, 'ORD5511', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:47:53', '2025-10-17 13:59:31'),
(32, 'ORD2355', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:07:27', '2025-10-17 13:59:31'),
(33, 'ORD6820', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:22:40', '2025-10-17 13:59:31'),
(34, 'ORD6233', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:24:57', '2025-10-17 13:59:31'),
(35, 'ORD0521', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:26:11', '2025-10-17 13:59:31'),
(36, 'ORD5704', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-21 08:06:28', '2025-10-17 13:59:31'),
(37, 'ORD9753', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-29 04:53:19', '2025-10-17 13:59:31'),
(38, 'ORD8347', 'dine_in', 219.00, 0.00, NULL, 0.00, '', '2025-08-29 05:16:57', '2025-10-17 13:59:31'),
(39, 'ORD6991', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-09-10 09:01:08', '2025-10-17 13:59:31'),
(40, 'ORD6751', 'dine_in', 148.00, 0.00, NULL, 0.00, '', '2025-09-17 17:55:04', '2025-10-17 13:59:31'),
(41, 'ORD6743', 'dine_in', 238.00, 0.00, NULL, 0.00, '', '2025-10-01 22:06:35', '2025-10-17 13:59:31'),
(42, 'ORD5262', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-10 17:52:38', '2025-10-17 13:59:31'),
(43, 'ORD1774', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-10 17:53:27', '2025-10-17 13:59:31'),
(44, 'ORD5117', 'takeout', 59.00, 0.00, NULL, 0.00, '', '2025-10-10 17:54:55', '2025-10-17 13:59:31'),
(45, 'ORD2131', 'takeout', 59.00, 0.00, NULL, 0.00, '', '2025-10-10 17:55:19', '2025-10-17 13:59:31'),
(46, 'ORD1580', 'takeout', 107.00, 0.00, NULL, 0.00, '', '2025-10-10 18:29:20', '2025-10-17 13:59:31'),
(47, 'ORD7336', 'dine_in', 258.00, 0.00, NULL, 0.00, '', '2025-10-10 18:35:24', '2025-10-17 13:59:31'),
(48, 'ORD8440', 'dine_in', 89.00, 0.00, NULL, 0.00, '', '2025-10-11 05:55:18', '2025-10-17 13:59:31'),
(49, 'ORD1506', 'dine_in', 398.00, 0.00, NULL, 0.00, '', '2025-10-11 06:25:18', '2025-10-17 13:59:31'),
(50, 'ORD5178', 'takeout', 99.00, 0.00, NULL, 0.00, '', '2025-10-11 06:26:36', '2025-10-17 13:59:31'),
(51, 'ORD1488', 'takeout', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:27:25', '2025-10-17 13:59:31'),
(52, 'ORD7568', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:33:21', '2025-10-17 13:59:31'),
(53, 'ORD8485', 'dine_in', 59.00, 0.00, NULL, 0.00, '', '2025-10-11 06:34:10', '2025-10-17 13:59:31'),
(54, 'ORD3389', 'dine_in', 500.00, 0.00, NULL, 0.00, '', '2025-10-11 06:35:50', '2025-10-17 13:59:31'),
(55, 'ORD3081', 'dine_in', 237.00, 0.00, NULL, 0.00, '', '2025-10-11 06:37:21', '2025-10-17 13:59:31'),
(56, 'ORD2809', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:39:13', '2025-10-17 13:59:31'),
(57, 'ORD4542', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-11 06:41:04', '2025-10-17 13:59:31'),
(58, 'ORD5677', 'takeout', 590.00, 0.00, NULL, 0.00, '', '2025-10-11 06:45:00', '2025-10-17 13:59:31'),
(59, 'ORD8990', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-11 06:55:26', '2025-10-17 13:59:31'),
(60, 'ORD5267', 'dine_in', 239.00, 0.00, NULL, 0.00, '', '2025-10-11 06:58:06', '2025-10-17 13:59:31'),
(61, 'ORD1060', 'takeout', 378.00, 0.00, NULL, 0.00, '', '2025-10-11 06:59:36', '2025-10-17 13:59:31'),
(62, 'ORD5333', 'dine_in', 306.00, 0.00, NULL, 0.00, '', '2025-10-11 07:00:30', '2025-10-17 13:59:31'),
(63, 'ORD3338', 'dine_in', 219.00, 0.00, NULL, 0.00, '', '2025-10-11 07:13:40', '2025-10-17 13:59:31'),
(64, 'ORD0420', 'dine_in', 328.00, 0.00, NULL, 1000.00, '', '2025-10-17 05:17:38', '2025-10-17 13:59:31'),
(66, 'ORD0526', 'dine_in', 69.00, 0.00, NULL, 200.00, '', '2025-10-17 06:02:54', '2025-10-17 13:59:31'),
(67, 'ORD1861', 'dine_in', 357.00, 0.00, NULL, 2000.00, '', '2025-10-17 06:04:51', '2025-10-17 13:59:31'),
(68, 'ORD0466', 'dine_in', 69.00, 0.00, NULL, 100.00, '', '2025-10-17 06:06:01', '2025-10-17 13:59:31'),
(69, 'ORD5089', 'dine_in', 307.00, 0.00, NULL, 500.00, '', '2025-10-17 06:08:44', '2025-10-17 13:59:31'),
(70, 'ORD3502', 'dine_in', 179.00, 0.00, NULL, 200.00, '', '2025-10-17 06:09:15', '2025-10-17 13:59:31'),
(71, 'ORD1520', 'dine_in', 189.00, 0.00, NULL, 0.00, '', '2025-10-17 06:39:02', '2025-10-17 13:59:31'),
(72, 'TBL1008', 'takeout', 945.00, 0.00, NULL, 0.00, '', '2025-10-17 06:40:47', '2025-10-17 13:59:31'),
(73, 'TBL1962', 'dine_in', 100.00, 0.00, NULL, 200.00, '', '2025-10-17 06:51:17', '2025-10-17 13:59:31'),
(74, 'ORD0088', 'dine_in', 339.00, 0.00, NULL, 500.00, '', '2025-10-17 06:55:29', '2025-10-17 13:59:31'),
(75, 'ORD2296', 'dine_in', 129.00, 0.00, NULL, 200.00, '', '2025-10-17 07:08:57', '2025-10-17 13:59:31'),
(76, 'ORD5067', 'dine_in', 148.00, 0.00, NULL, 1000.00, '', '2025-10-17 08:07:59', '2025-10-17 13:59:31'),
(77, 'ORD6918', 'dine_in', 129.00, 0.00, NULL, 600.00, '', '2025-10-17 08:34:47', '2025-10-17 13:59:31'),
(78, 'ORD7238', 'dine_in', 100.00, 0.00, NULL, 200.00, '', '2025-10-17 08:39:35', '2025-10-17 13:59:31'),
(79, 'TBL8381', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 08:48:37', '2025-10-17 13:59:31'),
(80, 'TBL8501', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:18:04', '2025-10-17 13:59:31'),
(81, 'TBL7189', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:20:47', '2025-10-17 13:59:31'),
(82, 'TBL2337', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:24:28', '2025-10-17 13:59:31'),
(83, 'ORD2762', 'dine_in', 100.00, 0.00, NULL, 100.00, '', '2025-10-17 09:25:37', '2025-10-17 13:59:31'),
(84, 'ORD6493', 'dine_in', 189.00, 0.00, NULL, 200.00, '', '2025-10-17 09:30:23', '2025-10-17 13:59:31'),
(86, 'TBL4078', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:33:55', '2025-10-17 13:59:31'),
(87, 'TBL8721', 'dine_in', 118.00, 0.00, NULL, 1000.00, '', '2025-10-17 09:38:14', '2025-10-17 13:59:31'),
(88, 'ORD8090', 'dine_in', 100.00, 0.00, NULL, 500.00, '', '2025-10-17 10:14:16', '2025-10-17 13:59:31'),
(89, 'ORD5774', 'dine_in', 278.00, 0.00, NULL, 500.00, '', '2025-10-17 13:35:09', '2025-10-18 09:01:58'),
(90, 'ORD5070', 'dine_in', 100.00, 0.00, NULL, 500.00, '', '2025-10-17 13:36:30', '2025-10-18 09:01:58'),
(92, 'ORD1587', 'dine_in', 87.20, 21.80, 'PWD', 100.00, '', '2025-10-17 14:45:44', '2025-10-18 09:01:58'),
(93, 'TBL3236', 'dine_in', 200.00, 0.00, NULL, 500.00, '', '2025-10-17 16:00:34', '2025-10-18 09:01:58'),
(0, 'TBL9956', 'dine_in', 300.00, 0.00, NULL, 1000.00, '', '2025-10-18 08:48:11', '2025-10-18 09:01:58'),
(0, 'ORD2002', 'dine_in', 308.80, 77.20, 'Senior', 500.00, 'Pending', '2025-10-18 09:11:28', '2025-10-18 09:11:28'),
(0, 'ORD1485', 'dine_in', 100.00, 0.00, NULL, 500.00, 'Order Ready', '2025-10-18 09:12:03', '2025-10-18 11:04:14');

-- --------------------------------------------------------

--
-- Table structure for table `customer_order_items`
--

CREATE TABLE `customer_order_items` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `menu_item_id` varchar(50) NOT NULL,
  `size_id` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `flavor` varchar(100) DEFAULT NULL,
  `sinkers` text DEFAULT NULL,
  `base` varchar(100) DEFAULT NULL,
  `refills` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_order_items`
--

INSERT INTO `customer_order_items` (`id`, `order_id`, `menu_item_id`, `size_id`, `quantity`, `price`, `flavor`, `sinkers`, `base`, `refills`, `created_at`) VALUES
(1, 'ORD6663', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 13:37:54'),
(2, 'ORD8386', 'MNGO', 'REG', 3, 69.00, '', NULL, NULL, 0, '2025-08-12 13:56:08'),
(3, 'ORD0593', 'BBQMUSHROOMBURGER', 'REG', 2, 119.00, '', NULL, NULL, 0, '2025-08-12 14:00:23'),
(4, 'ORD8073', 'CHEESEBURGER', 'REG', 1, 109.00, '', NULL, NULL, 0, '2025-08-12 14:01:02'),
(5, 'ORD9945', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-08-12 14:13:05'),
(6, 'ORD6995', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-08-12 14:16:39'),
(7, 'ORD0269', 'CHEESEBURGER', 'REG', 1, 109.00, '', NULL, NULL, 0, '2025-08-12 14:20:09'),
(8, 'ORD0792', 'CHEESEBURGER', 'REG', 1, 109.00, '', NULL, NULL, 0, '2025-08-12 14:25:39'),
(9, 'ORD4100', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:26:11'),
(10, 'ORD0563', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:27:00'),
(11, 'ORD5731', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:27:39'),
(12, 'ORD7795', 'BLUEBERRY_COOLRS', 'GRANDE', 2, 59.00, '', NULL, NULL, 0, '2025-08-12 14:32:33'),
(13, 'ORD9527', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:34:13'),
(14, 'ORD7146', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:38:09'),
(15, 'ORD0540', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-08-12 14:41:56'),
(16, 'ORD9463', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:43:14'),
(17, 'ORD3621', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:43:36'),
(18, 'ORD5047', 'HOKKAIDO', 'REG', 1, 99.00, '', NULL, NULL, 0, '2025-08-12 14:44:16'),
(19, 'ORD7910', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:45:06'),
(20, 'ORD7477', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:54:02'),
(21, 'ORD2336', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:57:04'),
(22, 'ORD2553', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 14:59:02'),
(23, 'ORD5633', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:02:17'),
(24, 'ORD0447', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:08:27'),
(25, 'ORD0447', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:08:27'),
(26, 'ORD7595', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:12:10'),
(27, 'ORD1636', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:28:28'),
(28, 'ORD8580', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:31:29'),
(29, 'ORD4489', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:32:54'),
(30, 'ORD7927', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:33:29'),
(31, 'ORD4852', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:46:54'),
(32, 'ORD5511', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 15:47:54'),
(33, 'ORD2355', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 16:07:27'),
(34, 'ORD6820', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 16:22:40'),
(35, 'ORD6233', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 16:24:57'),
(36, 'ORD0521', 'BLUEBERRY_COOLRS', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-12 16:26:11'),
(37, 'ORD5704', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-08-21 08:06:28'),
(38, 'ORD9753', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-08-29 04:53:19'),
(39, 'ORD8347', 'BLUEBERRY_COOLRS', 'REG', 1, 100.00, '', NULL, NULL, 0, '2025-08-29 05:16:57'),
(40, 'ORD8347', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-08-29 05:16:57'),
(41, 'ORD6991', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-09-10 09:01:08'),
(42, 'ORD6751', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-09-17 17:55:04'),
(43, 'ORD6751', 'LYCHEE_FRUITTEA', 'REG', 1, 79.00, '', NULL, NULL, 0, '2025-09-17 17:55:04'),
(44, 'ORD6743', 'BBQMUSHROOMBURGER', 'REG', 2, 119.00, '', NULL, NULL, 0, '2025-10-01 22:06:35'),
(45, 'ORD5262', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-10-10 17:52:38'),
(46, 'ORD1774', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-10-10 17:53:27'),
(47, 'ORD5117', 'MNGO', 'GRANDE', 1, 59.00, '', NULL, NULL, 0, '2025-10-10 17:54:55'),
(48, 'ORD2131', 'MNGO', 'GRANDE', 1, 59.00, '', NULL, NULL, 0, '2025-10-10 17:55:19'),
(49, 'ORD1580', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-10-10 18:29:20'),
(50, 'ORD1580', 'BLACKPEARL', 'REG', 1, 19.00, '', NULL, NULL, 0, '2025-10-10 18:29:21'),
(51, 'ORD1580', 'CRYSTAL', 'REG', 1, 19.00, '', NULL, NULL, 0, '2025-10-10 18:29:21'),
(52, 'ORD7336', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-10 18:35:24'),
(53, 'ORD7336', 'MONSTERBURGER', 'REG', 1, 139.00, '', NULL, NULL, 0, '2025-10-10 18:35:24'),
(54, 'ORD8440', 'BLBERRY', 'REG', 1, 89.00, '', NULL, NULL, 0, '2025-10-11 05:55:18'),
(55, 'ORD1506', 'BLUEBERRY_COOLRS', 'REG', 1, 100.00, '', NULL, NULL, 0, '2025-10-11 06:25:18'),
(56, 'ORD1506', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-11 06:25:19'),
(57, 'ORD1506', 'BEEFBULGOGI', 'REG', 1, 179.00, '', NULL, NULL, 0, '2025-10-11 06:25:19'),
(58, 'ORD5178', 'HOKKAIDO', 'REG', 1, 99.00, '', NULL, NULL, 0, '2025-10-11 06:26:36'),
(59, 'ORD1488', 'CARBONARA', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-11 06:27:25'),
(60, 'ORD7568', 'CARBONARA', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-11 06:33:22'),
(61, 'ORD8485', 'MNGO', 'GRANDE', 1, 59.00, '', NULL, NULL, 0, '2025-10-11 06:34:10'),
(62, 'ORD3389', 'BLUEBERRY_COOLRS', 'REG', 5, 100.00, '', NULL, NULL, 0, '2025-10-11 06:35:50'),
(63, 'ORD3081', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-11 06:37:21'),
(64, 'ORD3081', 'BLUEBERRY_COOLRS', 'GRANDE', 2, 59.00, '', NULL, NULL, 0, '2025-10-11 06:37:21'),
(65, 'ORD2809', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-11 06:39:13'),
(66, 'ORD4542', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-10-11 06:41:04'),
(67, 'ORD5677', 'BLUEBERRY_COOLRS', 'GRANDE', 10, 59.00, '', NULL, NULL, 0, '2025-10-11 06:45:00'),
(68, 'ORD8990', 'BLUEBERRY_COOLRS', 'REG', 1, 100.00, '', NULL, NULL, 0, '2025-10-11 06:55:26'),
(69, 'ORD5267', 'WINGS10PCS', 'REG', 1, 239.00, 'Honey Garlic', NULL, NULL, 0, '2025-10-11 06:58:06'),
(70, 'ORD1060', 'BEEFWAGYU', 'REG', 2, 189.00, '', NULL, NULL, 0, '2025-10-11 06:59:36'),
(71, 'ORD5333', 'BLACKPEARL', 'REG', 1, 19.00, '', NULL, NULL, 0, '2025-10-11 07:00:30'),
(72, 'ORD5333', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-11 07:00:30'),
(73, 'ORD5333', 'CAPPUCCINO', 'GRANDE', 1, 109.00, '', NULL, NULL, 0, '2025-10-11 07:00:30'),
(74, 'ORD5333', 'BLUEBERRY_COOLRS', 'GRANDE', 1, 59.00, '', NULL, NULL, 0, '2025-10-11 07:00:30'),
(75, 'ORD3338', 'BBQMUSHROOMBURGER', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-11 07:13:40'),
(76, 'ORD3338', 'BLUEBERRY_COOLRS', 'REG', 1, 100.00, '', NULL, NULL, 0, '2025-10-11 07:13:40'),
(77, 'ORD0420', 'BLUEBERRY_COOLRS', 'REG', 2, 100.00, '', NULL, NULL, 0, '2025-10-17 05:17:38'),
(78, 'ORD0420', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-10-17 05:17:38'),
(79, 'ORD0420', 'MNGO', 'GRANDE', 1, 59.00, '', NULL, NULL, 0, '2025-10-17 05:17:38'),
(82, 'ORD0526', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-10-17 06:02:54'),
(83, 'ORD1861', 'CARBONARA', 'REG', 1, 119.00, '', NULL, NULL, 0, '2025-10-17 06:04:52'),
(84, 'ORD1861', 'BBQMUSHROOMBURGER', 'REG', 2, 119.00, '', NULL, NULL, 0, '2025-10-17 06:04:52'),
(85, 'ORD0466', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-10-17 06:06:01'),
(86, 'ORD5089', 'TUNAPASTA', 'REG', 1, 89.00, '', NULL, NULL, 0, '2025-10-17 06:08:44'),
(87, 'ORD5089', 'CHEESEBURGER', 'REG', 2, 109.00, '', NULL, NULL, 0, '2025-10-17 06:08:44'),
(88, 'ORD3502', 'MANGO', 'REG', 1, 79.00, '', NULL, NULL, 0, '2025-10-17 06:09:15'),
(89, 'ORD3502', 'BLUEBERRY_COOLRS', 'REG', 1, 100.00, '', NULL, NULL, 0, '2025-10-17 06:09:15'),
(90, 'ORD1520', 'CHICKENINASAL', 'REG', 1, 189.00, '', NULL, NULL, 0, '2025-10-17 06:39:02'),
(91, 'TBL1008', 'BEEFWAGYU', 'REG', 5, 189.00, '', NULL, NULL, 0, '2025-10-17 06:40:48'),
(92, 'TBL1962', 'BLUEBERRY_COOLRS', 'REG', 1, 100.00, '', NULL, NULL, 0, '2025-10-17 06:51:17'),
(93, 'ORD0088', 'WINGS15PCS', 'REG', 1, 339.00, 'Buffalo', NULL, NULL, 0, '2025-10-17 06:55:29'),
(94, 'ORD2296', 'WINGS5PCS', 'REG', 1, 129.00, 'Buffalo', NULL, NULL, 0, '2025-10-17 07:08:57'),
(95, 'ORD2296', 'CHICKEN', 'REG', 3, 0.00, 'Buffalo', NULL, NULL, 0, '2025-10-17 07:34:12'),
(96, 'ORD2296', 'CHICKEN', 'REG', 4, 0.00, 'Garlic Parmesan', NULL, NULL, 0, '2025-10-17 07:39:04'),
(97, 'ORD0088', 'CHICKEN', 'REG', 5, 0.00, 'Honey BBQ', NULL, NULL, 0, '2025-10-17 07:47:44'),
(98, 'ORD5067', 'MANGO', 'REG', 1, 79.00, '', NULL, NULL, 0, '2025-10-17 08:07:59'),
(99, 'ORD5067', 'MNGO', 'REG', 1, 69.00, '', NULL, NULL, 0, '2025-10-17 08:08:00'),
(100, 'ORD6918', 'WINGS5PCS', 'REG', 1, 129.00, 'Spicy', NULL, NULL, 0, '2025-10-17 08:34:47'),
(101, 'ORD6918', 'CHICKEN', 'REG', 3, 0.00, 'Garlic Parmesan', NULL, NULL, 0, '2025-10-17 08:36:14'),
(102, 'ORD7238', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'Buffalo', NULL, NULL, 0, '2025-10-17 08:39:36'),
(103, 'TBL8381', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'Honey Garlic', NULL, NULL, 0, '2025-10-17 08:48:37'),
(104, 'TBL8501', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'BBQ', NULL, NULL, 0, '2025-10-17 09:18:04'),
(105, 'TBL7189', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'Buffalo', NULL, NULL, 0, '2025-10-17 09:20:47'),
(106, 'TBL2337', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'Buffalo', NULL, NULL, 0, '2025-10-17 09:24:29'),
(107, 'ORD2762', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'Original', NULL, NULL, 0, '2025-10-17 09:25:37'),
(108, 'ORD6493', 'BEEFWAGYU', 'REG', 1, 189.00, '', NULL, NULL, 0, '2025-10-17 09:30:23'),
(110, 'TBL4078', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'Teriyaki', NULL, NULL, 0, '2025-10-17 09:33:55'),
(111, 'TBL8721', 'FRIES', 'REG', 2, 59.00, '', NULL, NULL, 0, '2025-10-17 09:38:14'),
(112, 'ORD8090', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'BBQ', NULL, NULL, 0, '2025-10-17 10:14:16'),
(113, 'ORD5774', 'TUNAPASTA', 'REG', 1, 89.00, '', NULL, NULL, 0, '2025-10-17 13:35:09'),
(114, 'ORD5774', 'BEEFWAGYU', 'REG', 1, 189.00, '', NULL, NULL, 0, '2025-10-17 13:35:09'),
(115, 'ORD5070', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'BBQ', NULL, NULL, 0, '2025-10-17 13:36:30'),
(116, 'ORD1587', 'CHEESEBURGER', 'REG', 1, 109.00, '', '', '', 0, '2025-10-17 14:45:44'),
(117, 'TBL3236', 'UNLICHICKENWINGS', 'REG', 2, 100.00, 'Honey Garlic', NULL, NULL, 0, '2025-10-17 16:00:34'),
(0, 'ORD5070', 'CHICKEN', 'REG', 4, 0.00, 'Garlic Parmesan', NULL, NULL, 0, '2025-10-18 06:27:37'),
(0, 'TBL3236', 'CHICKEN', 'REG', 5, 0.00, 'Garlic Parmesan', NULL, NULL, 0, '2025-10-18 08:46:10'),
(0, 'TBL9956', 'UNLICHICKENWINGS', 'REG', 3, 100.00, 'Honey Garlic', NULL, NULL, 0, '2025-10-18 08:48:11'),
(0, 'ORD2002', 'BLUEBERRY_COOLRS', 'GRANDE', 1, 59.00, '', 'Pearl', '', 0, '2025-10-18 09:11:28'),
(0, 'ORD2002', 'CHEESEBURGER', 'REG', 3, 109.00, '', '', '', 0, '2025-10-18 09:11:28'),
(0, 'ORD1485', 'UNLICHICKENWINGS', 'REG', 1, 100.00, 'Buffalo', '', '', 0, '2025-10-18 09:12:03');

-- --------------------------------------------------------

--
-- Table structure for table `discount_logs`
--

CREATE TABLE `discount_logs` (
  `id` int(11) NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL,
  `discount_type` varchar(50) DEFAULT NULL,
  `pwd_id` varchar(100) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `applied_by` varchar(50) NOT NULL,
  `pin_used` varchar(255) NOT NULL,
  `applied_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discount_logs`
--

INSERT INTO `discount_logs` (`id`, `discount_percentage`, `discount_type`, `pwd_id`, `item_name`, `subtotal`, `discount_amount`, `applied_by`, `pin_used`, `applied_time`) VALUES
(1, 10.00, NULL, NULL, NULL, 99.00, 9.90, 'Manager', '1234', '2025-06-04 09:49:00'),
(2, 50.00, NULL, NULL, NULL, 69.00, 34.50, 'Manager', '1234', '2025-06-04 09:50:48'),
(3, 10.00, NULL, NULL, NULL, 545.00, 54.50, 'Manager', '0000', '2025-08-29 05:14:55'),
(4, 20.00, 'PWD', '123412341234', NULL, 525.00, 105.00, 'Manager', '0000', '2025-10-17 14:39:08'),
(5, 20.00, 'PWD', '1111111111111111', NULL, 109.00, 21.80, 'Manager', '0000', '2025-10-17 14:41:23'),
(0, 20.00, 'Senior', '2525444478987777', NULL, 386.00, 77.20, 'Manager', '0000', '2025-10-18 09:06:57');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `name`, `comment`, `created_at`) VALUES
(1, 'Leon Emano', 'I like the place, and the staff is very accomodating!', '2025-07-07 09:21:19'),
(2, 'Mecha Escasinas', 'I like their convenient ordering system', '2025-07-07 09:26:26'),
(3, 'Matthew Philip Maniego', 'I love their foods so much! Will come back again!', '2025-07-08 11:41:56'),
(4, 'Anonymous', 'secret no clue', '2025-07-08 11:43:19'),
(5, 'Big Brother', 'Masarap. at Malinamnam. Maaari bang makuha ito para sa aking housemates?', '2025-07-08 16:12:31'),
(6, 'Anonymous', 'its nice!', '2025-08-29 05:18:57'),
(7, 'Anonymous', 'so yummy good', '2025-10-11 06:19:36'),
(8, 'Anonymous', 'Sarap naman!!', '2025-10-11 06:25:53'),
(9, 'Leeyon', 'so very freshing!', '2025-10-11 06:37:52'),
(10, 'Anonymous', 'So Yummy Good!!', '2025-10-11 06:45:35'),
(11, 'Park Jiwon', 'K lang...', '2025-10-11 07:01:01'),
(0, 'Hannielyn', 'sige lang, masarap naman, edible.', '2025-10-18 08:49:00');

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `ID` int(11) NOT NULL,
  `ACC_ID` int(11) DEFAULT NULL,
  `EMAIL` varchar(50) DEFAULT NULL,
  `PASS` varchar(50) DEFAULT NULL,
  `DATE_OF_LOGIN` date DEFAULT NULL,
  `TIME_OF_LOGIN` varchar(50) DEFAULT NULL,
  `TIME_OF_LOGOUT` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`ID`, `ACC_ID`, `EMAIL`, `PASS`, `DATE_OF_LOGIN`, `TIME_OF_LOGIN`, `TIME_OF_LOGOUT`) VALUES
(80, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-10', '04:50 PM', '04:50 PM'),
(81, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-10', '04:51 PM', '04:52 PM'),
(82, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-10', '04:52 PM', '04:53 PM'),
(83, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-10', '04:53 PM', '04:53 PM'),
(84, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-10', '05:33 PM', '09:19 PM'),
(85, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-10', '05:34 PM', '06:35 PM'),
(86, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-10', '08:28 PM', '09:19 PM'),
(87, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-10', '08:59 PM', '09:19 PM'),
(88, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-10', '09:19 PM', '09:43 PM'),
(89, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-10', '09:34 PM', '09:42 PM'),
(90, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-10', '09:43 PM', '09:43 PM'),
(91, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-11', '08:51 PM', '10:11 PM'),
(92, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-11', '10:11 PM', '12:53 AM'),
(93, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-13', '02:05 AM', '12:53 AM'),
(94, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-13', '02:08 AM', '02:14 AM'),
(95, 101, 'admin@bh', '$2y$10$jKejhpXC7Qg0f13uASwRiOUL8hL5rD0i5Z/Me3DpJBv', '2025-09-18', '12:32 AM', '12:53 AM'),
(96, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-18', '12:53 AM', '01:49 AM'),
(97, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-18', '02:41 AM', '02:42 AM'),
(98, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-18', '02:43 AM', '07:41 PM'),
(99, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-18', '01:56 PM', '07:41 PM'),
(100, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-18', '11:02 PM', '11:28 PM'),
(101, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-19', '09:52 PM', '11:28 PM'),
(102, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-19', '10:21 PM', '11:28 PM'),
(103, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-19', '11:45 PM', '07:40 PM'),
(104, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-22', '07:01 PM', '07:40 PM'),
(105, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-22', '07:40 PM', '07:41 PM'),
(106, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-22', '07:41 PM', '07:43 PM'),
(107, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-09-22', '07:43 PM', '07:45 PM'),
(108, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-22', '07:45 PM', '07:50 PM'),
(109, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-27', '07:44 PM', '07:50 PM'),
(110, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-09-30', '11:47 PM', '12:21 AM'),
(111, 101, 'admin@bh', '$2y$10$RH5.li/oCdrZRnVUrAKsou1eJm2e2DWJGJ/VUMpyqqs', '2025-10-01', '12:22 AM', '12:25 AM'),
(112, 101, 'admin@bh', '$2y$10$6.pKGDEBvFSqcZLaYbP5jOaR0OgIOiDC.HuugY4Kb.B', '2025-10-01', '12:25 AM', '12:31 AM'),
(113, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-10-01', '12:31 AM', '12:32 AM'),
(114, 101, 'admin@bh', '$2y$10$6.pKGDEBvFSqcZLaYbP5jOaR0OgIOiDC.HuugY4Kb.B', '2025-10-02', '06:00 AM', '06:04 AM'),
(115, 102, 'mecha@bhemployee', '$2y$10$6upeETFLwmcBMUMRAEYozuygVD/dcTGb4XFCzA4Q.AQ', '2025-10-02', '06:05 AM', '06:06 AM'),
(116, 101, 'admin@bh', '$2y$10$6.pKGDEBvFSqcZLaYbP5jOaR0OgIOiDC.HuugY4Kb.B', '2025-10-05', '06:58 PM', '07:22 PM'),
(117, 101, 'admin@bh', '$2y$10$YdvcM7ZEIehDEzMR1QhzR.ofij3wdVPhhLIKAhjfNja', '2025-10-05', '07:22 PM', '09:36 PM'),
(118, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-05', '09:40 PM', '01:44 PM'),
(119, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-05', '09:55 PM', '01:45 PM'),
(120, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-11', '12:06 AM', '01:44 PM'),
(121, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-11', '01:27 PM', '01:44 PM'),
(122, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-11', '01:44 PM', '01:46 PM'),
(123, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-11', '01:45 PM', '01:45 PM'),
(124, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-11', '01:46 PM', '03:28 PM'),
(125, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-11', '03:01 PM', '03:28 PM'),
(126, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-11', '03:28 PM', '03:35 PM'),
(127, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-11', '03:35 PM', '05:13 PM'),
(128, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-11', '05:13 PM', '05:25 PM'),
(129, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-11', '05:25 PM', '08:01 PM'),
(130, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-11', '05:46 PM', '12:47 PM'),
(131, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-12', '07:11 PM', '12:47 PM'),
(132, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-14', '05:28 PM', '08:01 PM'),
(133, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-14', '07:38 PM', '08:01 PM'),
(134, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-14', '08:01 PM', '12:47 PM'),
(135, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-16', '12:42 PM', '12:47 PM'),
(136, 101, 'admin@bh', '$2y$10$UBiUka/9gbANI5A.OEVVSeVarT0Joig3bKZf31dKsSy', '2025-10-16', '11:39 PM', '12:33 PM'),
(137, 101, 'admin@bh', '$2y$10$urT7pCzfrU/JiyFIYXn0V.ryLzfTRCX9TAmQUXXtPEx', '2025-10-17', '12:33 PM', '12:39 PM'),
(138, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-17', '12:40 PM', '12:47 PM'),
(139, 101, 'admin@bh', '$2y$10$urT7pCzfrU/JiyFIYXn0V.ryLzfTRCX9TAmQUXXtPEx', '2025-10-17', '12:47 PM', '12:58 PM'),
(140, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-17', '12:58 PM', '01:01 PM'),
(141, 101, 'admin@bh', '$2y$10$urT7pCzfrU/JiyFIYXn0V.ryLzfTRCX9TAmQUXXtPEx', '2025-10-17', '01:01 PM', '01:13 PM'),
(142, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-17', '01:13 PM', '01:15 PM'),
(143, 101, 'admin@bh', '$2y$10$urT7pCzfrU/JiyFIYXn0V.ryLzfTRCX9TAmQUXXtPEx', '2025-10-17', '01:15 PM', '01:17 PM'),
(144, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-17', '01:17 PM', '02:43 PM'),
(145, 101, 'admin@bh', '$2y$10$urT7pCzfrU/JiyFIYXn0V.ryLzfTRCX9TAmQUXXtPEx', '2025-10-17', '02:43 PM', '02:50 PM'),
(146, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-17', '02:50 PM', '05:23 PM'),
(147, 101, 'admin@bh', '$2y$10$urT7pCzfrU/JiyFIYXn0V.ryLzfTRCX9TAmQUXXtPEx', '2025-10-17', '03:43 PM', '12:48 AM'),
(148, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-17', '05:24 PM', '12:17 AM'),
(149, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-17', '05:28 PM', '12:17 AM'),
(150, 101, 'admin@bh', '$2y$10$OGjG35JWzaioMgOyGf5WZeYEpnV1Y5pLya.2nYpI/DN', '2025-10-17', '10:52 PM', '12:48 AM'),
(151, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-18', '12:19 AM', '12:19 AM'),
(152, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-18', '12:19 AM', '12:20 AM'),
(153, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-18', '12:21 AM', '02:39 PM'),
(154, 101, 'admin@bh', '$2y$10$OGjG35JWzaioMgOyGf5WZeYEpnV1Y5pLya.2nYpI/DN', '2025-10-18', '12:49 AM', '12:50 AM'),
(0, 101, 'admin@bh', '$2y$10$OGjG35JWzaioMgOyGf5WZeYEpnV1Y5pLya.2nYpI/DN', '2025-10-18', '11:28 AM', '11:28 AM'),
(0, 103, 'maniego.matt@bhemployee', '$2y$10$EaKBShLyxnfHwgc7dmlbA.3DLRuK9r9yBz7tXeieQXH', '2025-10-18', '11:29 AM', '11:34 AM'),
(0, 101, 'admin@bh', '$2y$10$OGjG35JWzaioMgOyGf5WZeYEpnV1Y5pLya.2nYpI/DN', '2025-10-18', '11:34 AM', '01:19 PM'),
(0, 103, 'maniego.matt@bhemployee', '$2y$10$EaKBShLyxnfHwgc7dmlbA.3DLRuK9r9yBz7tXeieQXH', '2025-10-18', '01:19 PM', '02:04 PM'),
(0, 101, 'admin@bh', '$2y$10$OGjG35JWzaioMgOyGf5WZeYEpnV1Y5pLya.2nYpI/DN', '2025-10-18', '02:04 PM', '02:12 PM'),
(0, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-18', '02:05 PM', '02:39 PM'),
(0, 101, 'admin@bh', '$2y$10$OGjG35JWzaioMgOyGf5WZeYEpnV1Y5pLya.2nYpI/DN', '2025-10-18', '02:12 PM', NULL),
(0, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-18', '02:40 PM', '04:55 PM'),
(0, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-18', '04:56 PM', NULL),
(0, 101, 'admin@bh', '$2y$10$OGjG35JWzaioMgOyGf5WZeYEpnV1Y5pLya.2nYpI/DN', '2025-10-18', '06:54 PM', NULL),
(0, 102, 'mecha@bhemployee', '$2y$10$7tVb.yMUY5DkmTnAnK1dH.7GFKbAuFMDydPIZYJelwL', '2025-10-18', '06:54 PM', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` varchar(50) NOT NULL,
  `category_id` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `category_id`, `name`, `description`) VALUES
('BBQMUSHROOMBURGER', 'BURGERS', 'BBQ Mushroom Burger', NULL),
('BEEFBULGOGI', 'RICEDISH', 'Beef Bulgogi', NULL),
('BEEFGARLICTENDERLOIN', 'RICEDISH', 'Beef Garlic Tenderloin', NULL),
('BEEFWAGYU', 'RICEDISH', 'Beef Wagyu', NULL),
('BFF', 'COMBOMEAL', 'Burger, Fries, Frappe\r\n(BFF)', 'burger,fries,frappe'),
('BFM', 'COMBOMEAL', 'Burger, Fries, Milktea\r\n(BFM)', 'burger,fries,milktea'),
('BLACKPEARL', 'ADDONS', 'Black Pearl', NULL),
('BLBERRY', 'MLKTEA', 'Blueberry', NULL),
('BLBERRY - REG', 'MLKTEA', 'Blueberry', NULL),
('BLKPRL', 'MLKTEA', 'Black Pearl', NULL),
('BLUBRRY', 'FRUITTEA', 'Blueberry', NULL),
('BLUEBERRY', 'COOLRS', 'Blueberry', NULL),
('BLUEBERRY_HIDEOUT', 'HIDEOUT', 'Blueberry', NULL),
('BLUEBRRY_FRUITTEA', 'FRUITTEA', 'Blueberry', NULL),
('BURGERSTEAK', 'SIZZLERS', 'Burger Steak', NULL),
('CAPPUCCINO', 'FRPPE', 'Cappuccino', NULL),
('CARAMEL', 'FRPPE', 'Caramel', NULL),
('CARAMELMACCHIATO', 'ICEDRINKS', 'Caramel Macchiato', NULL),
('CARAMELMACHIATO_ICEDRINKS', 'ICEDRINKS', 'Caramel Macchiato', NULL),
('CARBONARA', 'PASTA', 'Carbonara', NULL),
('CCF', 'COMBOMEAL', 'cheese burger,Fries,Frappe\r\n(CCF)', 'cheese burger,fries,frappe'),
('CFM', 'COMBOMEAL', 'Cheese burger,Fries,Milktea\r\n(CFM)', 'cheese burger,fries,milktea'),
('CF_ICEDRINKS', 'ICEDRINKS', 'Coffee Jelly', NULL),
('CF_ICEDRNKS', 'ICEDRNKS', 'Coffee Jelly', NULL),
('CHEESEBURGER', 'BURGERS', 'Cheeseburger', NULL),
('CHICKENINASAL', 'SIZZLERS', 'Chicken Inasal', NULL),
('CHICKENPENNE', 'PASTA', 'Chicken Penne', NULL),
('CHOCOMOUSSE', 'FRPPE', 'Chocolate Mousse', NULL),
('COFFEEJELLY', 'ADDONS', 'Coffee Jelly', NULL),
('COFFEEJEL_ICEDRINKS', 'ICEDRINKS', 'Coffee Jelly', NULL),
('COFFEE_JELLY', 'ICEDRINKS', 'Coffee Jelly', NULL),
('COKE', 'SOFTDRINKS', 'Coke', NULL),
('COOKIESNCREAM', 'FRPPE', 'Cookies and Cream', NULL),
('CRMCHZ', 'PRMMILKTEA', 'Cream Cheese', NULL),
('CRMLFUDGE', 'MLKTEA', 'Caramel Fudge', NULL),
('CRYSTAL', 'ADDONS', 'Crystal', NULL),
('DARKCHOCO', 'FRPPE', 'Dark Chocolate', NULL),
('DARKCHOCO_CHSCAKE', 'CHSCAKE', 'Dark Chocolate', NULL),
('DOUBLEPORKCHOP', 'SIZZLERS', 'Double Porkchop', NULL),
('DRKCHOC', 'MLKTEA', 'Dark Chocolate', NULL),
('FRIES', 'SNACKS', 'Fries', NULL),
('GARLICRICE', 'ADDONS', 'Garlic Rice', NULL),
('GOURMETSPAGHETTI', 'PASTA', 'Gourmet Spaghetti', NULL),
('GREENAPPLE', 'COOLRS', 'Green Apple', NULL),
('HIDEOUTCHICKEN', 'RICEDISH', 'Hideout Chicken', NULL),
('HOKKAIDO', 'PRMMILKTEA', 'Hokkaido', NULL),
('HONEYGARLICWINGS', 'RICEDISH', 'Honey Garlic Wings', NULL),
('HUNGARIANSAUSAGE', 'SIZZLERS', 'Hungarian Sausage', NULL),
('HZLNT', 'MLKTEA', 'Hazelnut', NULL),
('JAVACHIP', 'FRPPE', 'Java Chip', NULL),
('KIWI', 'COOLRS', 'Kiwi', NULL),
('LEMON', 'COOLRS', 'Lemon', NULL),
('LIEMPO', 'SIZZLERS', 'Liempo', NULL),
('LYCH', 'COOLRS', 'Lychee', NULL),
('LYCHEE', 'FRUITTEA', 'Lychee', NULL),
('LYCHEE_FRUITTEA', 'FRUITTEA', 'Lychee', NULL),
('MANGO', 'FRUITTEA', 'Mango', NULL),
('MANGOCHEESECAKE', 'HIDEOUT', 'Mango Cheesecake', NULL),
('MATCHA', 'HIDEOUT', 'Matcha', NULL),
('MATCHAOREO', 'FRPPE', 'Matcha Oreo', NULL),
('MATCHA_CHSCAKE', 'CHSCAKE', 'Matcha', NULL),
('MATCHA_PRMMILKTEA', 'PRMMILKTEA', 'Matcha', NULL),
('MNGO', 'COOLRS', 'Mango', NULL),
('MOCHA', 'ICEDRINKS', 'Mocha', NULL),
('MOCHA_FRPPE', 'FRPPE', 'Mocha', NULL),
('MOCHA_ICEDRINKS', 'ICEDRINKS', 'Mocha', NULL),
('MONSTERBURGER', 'BURGERS', 'Monster Burger', NULL),
('MTCH_HIDEOUT', 'HIDEOUT', 'Matcha\r\n', NULL),
('NACHOS', 'SNACKS', 'Nachos', NULL),
('NUTELLA', 'PRMMILKTEA', 'Nutella', NULL),
('OKINAWA', 'PRMMILKTEA', 'Okinawa', NULL),
('ONIONRINGS', 'SNACKS', 'Onion Rings', NULL),
('OREO', 'MLKTEA', 'Oreo', NULL),
('OREO_CHSCAKE', 'CHSCAKE', 'Oreo', NULL),
('PASSIONFRUIT', 'COOLRS', 'Passion Fruit', NULL),
('PEPPERBEEF', 'SIZZLERS', 'Pepper Beef', NULL),
('PLAINRICE', 'ADDONS', 'Plain Rice', NULL),
('PORKASADO', 'RICEDISH', 'Pork Asado', NULL),
('PORTERHOUSE', 'SIZZLERS', 'Porterhouse', NULL),
('QUARTERPOUNDER', 'BURGERS', 'Quarter Pounder', NULL),
('REDVELVET', 'HIDEOUT', 'Red Velvet', NULL),
('REDVLVT_CHSCAKE', 'CHSCAKE', 'Red Velvet', NULL),
('ROCKYROAD', 'FRPPE', 'Rocky Road', NULL),
('ROYAL', 'SOFTDRINKS', 'Royal', NULL),
('SALTCRML', 'MLKTEA', 'Salted Caramel', NULL),
('SARSI', 'SOFTDRINKS', 'Sarsi', NULL),
('SPRITE', 'SOFTDRINKS', 'Sprite', NULL),
('STRAWBERRY', 'FRUITTEA', 'Strawberry', NULL),
('STRAWBERRYOREO', 'FRPPE', 'Strawberry Oreo', NULL),
('STRAWBERRY_FRUITTEA', 'FRUITTEA', 'Strawberry\r\n', NULL),
('STRAWBERRY_HIDEOUT', 'HIDEOUT', 'Strawberry', NULL),
('STRWBERRY', 'COOLRS', 'Strawberry', NULL),
('TARO', 'HIDEOUT', 'Taro', NULL),
('TARO_CHSCAKE', 'CHSCAKE', 'Taro', NULL),
('TGRBRWN', 'PRMMILKTEA', 'Tiger Brown', NULL),
('TRO', 'MLKTEA', 'Taro', NULL),
('TUNAPASTA', 'PASTA', 'Tuna Pasta', NULL),
('UNLICHICKENRICE', 'UNLICHKN', 'Unlimited Chicken with Rice', NULL),
('UNLICHICKENWINGSRICE', 'UNLICHKN', 'Unlimited Chicken Wings with Rice', NULL),
('UNLICHICKENWINGSRICEFRIES', 'UNLICHKN', 'Unlimited Chicken Wings with Rice and Fries', NULL),
('VANILLA', 'FRPPE', 'Vanilla', NULL),
('VANILLALATTE', 'ICEDRINKS', 'Vanilla Latte', NULL),
('VANILLALATTE_ICECRINKS', 'ICEDRINKS', 'Vanilla Latte', NULL),
('WATERMELON', 'FRUITTEA', 'Watermelon', NULL),
('WATERMELON_FRUITTEA', 'FRUITTEA', 'Watermelon', NULL),
('WATERMELON_FT', 'FRUITTEA', 'Watermelon', NULL),
('WATRMELON', 'COOLRS', 'Watermelon', NULL),
('WINGS10PCS', 'WINGS', '10 pcs', NULL),
('WINGS15PCS', 'WINGS', '15 pcs', NULL),
('WINGS20PCS', 'WINGS', '20 pcs', NULL),
('WINGS5PCS', 'WINGS', '5 pcs', NULL),
('WINGS60PCS', 'WINGS', '60 pcs', NULL),
('WNTRMLM', 'MLKTEA', 'Wintermelon', NULL),
('WTRMLN', 'HIDEOUT', 'Watermelon', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_archive`
--

CREATE TABLE `order_archive` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `order_type` enum('dine_in','takeout') NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL,
  `discount_type` varchar(50) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','ready') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_archive`
--

INSERT INTO `order_archive` (`id`, `order_id`, `order_type`, `total`, `discount`, `discount_type`, `amount_paid`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ORD6663', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 13:37:53', '2025-10-16 04:44:25'),
(2, 'ORD8386', 'dine_in', 207.00, 0.00, NULL, 0.00, '', '2025-08-12 13:56:08', '2025-10-16 04:44:26'),
(3, 'ORD0593', 'dine_in', 238.00, 0.00, NULL, 0.00, '', '2025-08-12 14:00:23', '2025-10-16 04:44:28'),
(4, 'ORD8073', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:01:02', '2025-10-16 04:44:29'),
(5, 'ORD9945', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:13:04', '2025-10-16 04:44:31'),
(6, 'ORD6995', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:16:39', '2025-10-16 04:44:33'),
(7, 'ORD0269', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:20:09', '2025-10-16 04:44:35'),
(8, 'ORD0792', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:25:39', '2025-10-16 04:44:36'),
(9, 'ORD4100', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:26:11', '2025-10-16 05:01:34'),
(10, 'ORD0563', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:27:00', '2025-10-16 05:01:34'),
(11, 'ORD5731', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:27:39', '2025-10-16 05:01:34'),
(12, 'ORD7795', 'dine_in', 118.00, 0.00, NULL, 0.00, '', '2025-08-12 14:32:32', '2025-10-16 05:01:34'),
(13, 'ORD9527', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:34:12', '2025-10-16 05:01:34'),
(14, 'ORD7146', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:38:08', '2025-10-16 05:01:34'),
(15, 'ORD0540', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:41:56', '2025-10-16 05:01:34'),
(16, 'ORD9463', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:43:14', '2025-10-16 05:01:34'),
(17, 'ORD3621', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:43:36', '2025-10-16 05:01:34'),
(18, 'ORD5047', 'dine_in', 99.00, 0.00, NULL, 0.00, '', '2025-08-12 14:44:16', '2025-10-16 05:01:34'),
(19, 'ORD7910', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:45:06', '2025-10-16 05:01:34'),
(20, 'ORD7477', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:54:02', '2025-10-16 05:01:34'),
(21, 'ORD2336', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:57:04', '2025-10-16 05:01:34'),
(22, 'ORD2553', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:59:02', '2025-10-16 05:01:34'),
(23, 'ORD5633', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:02:17', '2025-10-16 05:01:34'),
(24, 'ORD0447', 'dine_in', 138.00, 0.00, NULL, 0.00, '', '2025-08-12 15:08:27', '2025-10-16 05:01:34'),
(25, 'ORD7595', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:12:10', '2025-10-16 05:01:34'),
(26, 'ORD1636', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:28:28', '2025-10-16 05:01:34'),
(27, 'ORD8580', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:31:28', '2025-10-16 05:01:34'),
(28, 'ORD4489', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:32:54', '2025-10-16 05:01:34'),
(29, 'ORD7927', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:33:29', '2025-10-16 05:01:34'),
(30, 'ORD4852', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:46:54', '2025-10-16 05:01:34'),
(31, 'ORD5511', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:47:53', '2025-10-16 05:01:34'),
(32, 'ORD2355', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:07:27', '2025-10-16 05:01:34'),
(33, 'ORD6820', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:22:40', '2025-10-16 05:01:34'),
(34, 'ORD6233', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:24:57', '2025-10-16 05:01:34'),
(35, 'ORD0521', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:26:11', '2025-10-16 05:01:34'),
(36, 'ORD5704', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-21 08:06:28', '2025-10-16 05:01:34'),
(37, 'ORD9753', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-29 04:53:19', '2025-10-16 05:01:34'),
(38, 'ORD8347', 'dine_in', 219.00, 0.00, NULL, 0.00, '', '2025-08-29 05:16:57', '2025-10-16 05:01:34'),
(39, 'ORD6991', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-09-10 09:01:08', '2025-10-16 05:01:34'),
(40, 'ORD6751', 'dine_in', 148.00, 0.00, NULL, 0.00, '', '2025-09-17 17:55:04', '2025-10-16 05:01:34'),
(41, 'ORD6743', 'dine_in', 238.00, 0.00, NULL, 0.00, '', '2025-10-01 22:06:35', '2025-10-16 04:44:24'),
(42, 'ORD5262', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-10 17:52:38', '2025-10-16 04:44:22'),
(43, 'ORD1774', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-10 17:53:27', '2025-10-16 04:44:21'),
(44, 'ORD5117', 'takeout', 59.00, 0.00, NULL, 0.00, '', '2025-10-10 17:54:55', '2025-10-16 04:44:19'),
(45, 'ORD2131', 'takeout', 59.00, 0.00, NULL, 0.00, '', '2025-10-10 17:55:19', '2025-10-16 04:44:18'),
(46, 'ORD1580', 'takeout', 107.00, 0.00, NULL, 0.00, '', '2025-10-10 18:29:20', '2025-10-16 04:44:16'),
(47, 'ORD7336', 'dine_in', 258.00, 0.00, NULL, 0.00, '', '2025-10-10 18:35:24', '2025-10-16 04:44:15'),
(48, 'ORD8440', 'dine_in', 89.00, 0.00, NULL, 0.00, '', '2025-10-11 05:55:18', '2025-10-16 04:44:13'),
(49, 'ORD1506', 'dine_in', 398.00, 0.00, NULL, 0.00, '', '2025-10-11 06:25:18', '2025-10-16 04:44:12'),
(50, 'ORD5178', 'takeout', 99.00, 0.00, NULL, 0.00, '', '2025-10-11 06:26:36', '2025-10-16 04:44:11'),
(51, 'ORD1488', 'takeout', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:27:25', '2025-10-16 04:44:09'),
(52, 'ORD7568', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:33:21', '2025-10-16 04:44:07'),
(53, 'ORD8485', 'dine_in', 59.00, 0.00, NULL, 0.00, '', '2025-10-11 06:34:10', '2025-10-11 10:15:45'),
(54, 'ORD3389', 'dine_in', 500.00, 0.00, NULL, 0.00, '', '2025-10-11 06:35:50', '2025-10-11 10:15:44'),
(55, 'ORD3081', 'dine_in', 237.00, 0.00, NULL, 0.00, '', '2025-10-11 06:37:21', '2025-10-11 10:15:42'),
(56, 'ORD2809', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:39:13', '2025-10-11 10:15:40'),
(57, 'ORD4542', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-11 06:41:04', '2025-10-11 10:15:39'),
(58, 'ORD5677', 'takeout', 590.00, 0.00, NULL, 0.00, '', '2025-10-11 06:45:00', '2025-10-11 10:15:37'),
(59, 'ORD8990', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-11 06:55:26', '2025-10-11 10:15:35'),
(60, 'ORD5267', 'dine_in', 239.00, 0.00, NULL, 0.00, '', '2025-10-11 06:58:06', '2025-10-11 10:15:33'),
(61, 'ORD1060', 'takeout', 378.00, 0.00, NULL, 0.00, '', '2025-10-11 06:59:36', '2025-10-11 10:15:32'),
(62, 'ORD5333', 'dine_in', 306.00, 0.00, NULL, 0.00, '', '2025-10-11 07:00:30', '2025-10-11 10:15:30'),
(63, 'ORD3338', 'dine_in', 219.00, 0.00, NULL, 0.00, '', '2025-10-11 07:13:40', '2025-10-11 09:21:45'),
(64, 'ORD6663', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 13:37:53', '2025-10-16 04:44:25'),
(65, 'ORD8386', 'dine_in', 207.00, 0.00, NULL, 0.00, '', '2025-08-12 13:56:08', '2025-10-16 04:44:26'),
(66, 'ORD0593', 'dine_in', 238.00, 0.00, NULL, 0.00, '', '2025-08-12 14:00:23', '2025-10-16 04:44:28'),
(67, 'ORD8073', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:01:02', '2025-10-16 04:44:29'),
(68, 'ORD9945', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:13:04', '2025-10-16 04:44:31'),
(69, 'ORD6995', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:16:39', '2025-10-16 04:44:33'),
(70, 'ORD0269', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:20:09', '2025-10-16 04:44:35'),
(71, 'ORD0792', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:25:39', '2025-10-16 04:44:36'),
(72, 'ORD4100', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:26:11', '2025-10-16 05:01:34'),
(73, 'ORD0563', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:27:00', '2025-10-16 05:01:34'),
(74, 'ORD5731', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:27:39', '2025-10-16 05:01:34'),
(75, 'ORD7795', 'dine_in', 118.00, 0.00, NULL, 0.00, '', '2025-08-12 14:32:32', '2025-10-16 05:01:34'),
(76, 'ORD9527', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:34:12', '2025-10-16 05:01:34'),
(77, 'ORD7146', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:38:08', '2025-10-16 05:01:34'),
(78, 'ORD0540', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:41:56', '2025-10-16 05:01:34'),
(79, 'ORD9463', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:43:14', '2025-10-16 05:01:34'),
(80, 'ORD3621', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:43:36', '2025-10-16 05:01:34'),
(81, 'ORD5047', 'dine_in', 99.00, 0.00, NULL, 0.00, '', '2025-08-12 14:44:16', '2025-10-16 05:01:34'),
(82, 'ORD7910', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:45:06', '2025-10-16 05:01:34'),
(83, 'ORD7477', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:54:02', '2025-10-16 05:01:34'),
(84, 'ORD2336', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:57:04', '2025-10-16 05:01:34'),
(85, 'ORD2553', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:59:02', '2025-10-16 05:01:34'),
(86, 'ORD5633', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:02:17', '2025-10-16 05:01:34'),
(87, 'ORD0447', 'dine_in', 138.00, 0.00, NULL, 0.00, '', '2025-08-12 15:08:27', '2025-10-16 05:01:34'),
(88, 'ORD7595', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:12:10', '2025-10-16 05:01:34'),
(89, 'ORD1636', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:28:28', '2025-10-16 05:01:34'),
(90, 'ORD8580', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:31:28', '2025-10-16 05:01:34'),
(91, 'ORD4489', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:32:54', '2025-10-16 05:01:34'),
(92, 'ORD7927', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:33:29', '2025-10-16 05:01:34'),
(93, 'ORD4852', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:46:54', '2025-10-16 05:01:34'),
(94, 'ORD5511', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:47:53', '2025-10-16 05:01:34'),
(95, 'ORD2355', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:07:27', '2025-10-16 05:01:34'),
(96, 'ORD6820', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:22:40', '2025-10-16 05:01:34'),
(97, 'ORD6233', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:24:57', '2025-10-16 05:01:34'),
(98, 'ORD0521', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:26:11', '2025-10-16 05:01:34'),
(99, 'ORD5704', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-21 08:06:28', '2025-10-16 05:01:34'),
(100, 'ORD9753', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-29 04:53:19', '2025-10-16 05:01:34'),
(101, 'ORD8347', 'dine_in', 219.00, 0.00, NULL, 0.00, '', '2025-08-29 05:16:57', '2025-10-16 05:01:34'),
(102, 'ORD6991', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-09-10 09:01:08', '2025-10-16 05:01:34'),
(103, 'ORD6751', 'dine_in', 148.00, 0.00, NULL, 0.00, '', '2025-09-17 17:55:04', '2025-10-16 05:01:34'),
(104, 'ORD6743', 'dine_in', 238.00, 0.00, NULL, 0.00, '', '2025-10-01 22:06:35', '2025-10-16 04:44:24'),
(105, 'ORD5262', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-10 17:52:38', '2025-10-16 04:44:22'),
(106, 'ORD1774', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-10 17:53:27', '2025-10-16 04:44:21'),
(107, 'ORD5117', 'takeout', 59.00, 0.00, NULL, 0.00, '', '2025-10-10 17:54:55', '2025-10-16 04:44:19'),
(108, 'ORD2131', 'takeout', 59.00, 0.00, NULL, 0.00, '', '2025-10-10 17:55:19', '2025-10-16 04:44:18'),
(109, 'ORD1580', 'takeout', 107.00, 0.00, NULL, 0.00, '', '2025-10-10 18:29:20', '2025-10-16 04:44:16'),
(110, 'ORD7336', 'dine_in', 258.00, 0.00, NULL, 0.00, '', '2025-10-10 18:35:24', '2025-10-16 04:44:15'),
(111, 'ORD8440', 'dine_in', 89.00, 0.00, NULL, 0.00, '', '2025-10-11 05:55:18', '2025-10-16 04:44:13'),
(112, 'ORD1506', 'dine_in', 398.00, 0.00, NULL, 0.00, '', '2025-10-11 06:25:18', '2025-10-16 04:44:12'),
(113, 'ORD5178', 'takeout', 99.00, 0.00, NULL, 0.00, '', '2025-10-11 06:26:36', '2025-10-16 04:44:11'),
(114, 'ORD1488', 'takeout', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:27:25', '2025-10-16 04:44:09'),
(115, 'ORD7568', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:33:21', '2025-10-16 04:44:07'),
(116, 'ORD8485', 'dine_in', 59.00, 0.00, NULL, 0.00, '', '2025-10-11 06:34:10', '2025-10-11 10:15:45'),
(117, 'ORD3389', 'dine_in', 500.00, 0.00, NULL, 0.00, '', '2025-10-11 06:35:50', '2025-10-11 10:15:44'),
(118, 'ORD3081', 'dine_in', 237.00, 0.00, NULL, 0.00, '', '2025-10-11 06:37:21', '2025-10-11 10:15:42'),
(119, 'ORD2809', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:39:13', '2025-10-11 10:15:40'),
(120, 'ORD4542', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-11 06:41:04', '2025-10-11 10:15:39'),
(121, 'ORD5677', 'takeout', 590.00, 0.00, NULL, 0.00, '', '2025-10-11 06:45:00', '2025-10-11 10:15:37'),
(122, 'ORD8990', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-11 06:55:26', '2025-10-11 10:15:35'),
(123, 'ORD5267', 'dine_in', 239.00, 0.00, NULL, 0.00, '', '2025-10-11 06:58:06', '2025-10-11 10:15:33'),
(124, 'ORD1060', 'takeout', 378.00, 0.00, NULL, 0.00, '', '2025-10-11 06:59:36', '2025-10-11 10:15:32'),
(125, 'ORD5333', 'dine_in', 306.00, 0.00, NULL, 0.00, '', '2025-10-11 07:00:30', '2025-10-11 10:15:30'),
(126, 'ORD3338', 'dine_in', 219.00, 0.00, NULL, 0.00, '', '2025-10-11 07:13:40', '2025-10-11 09:21:45'),
(127, 'ORD6663', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 13:37:53', '2025-10-17 06:48:00'),
(128, 'ORD8386', 'dine_in', 207.00, 0.00, NULL, 0.00, '', '2025-08-12 13:56:08', '2025-10-17 06:48:00'),
(129, 'ORD0593', 'dine_in', 238.00, 0.00, NULL, 0.00, '', '2025-08-12 14:00:23', '2025-10-17 06:48:00'),
(130, 'ORD8073', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:01:02', '2025-10-17 06:48:00'),
(131, 'ORD9945', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:13:04', '2025-10-17 06:48:00'),
(132, 'ORD6995', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:16:39', '2025-10-17 06:48:00'),
(133, 'ORD0269', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:20:09', '2025-10-17 06:48:00'),
(134, 'ORD0792', 'dine_in', 109.00, 0.00, NULL, 0.00, '', '2025-08-12 14:25:39', '2025-10-17 06:48:00'),
(135, 'ORD4100', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:26:11', '2025-10-17 06:48:00'),
(136, 'ORD0563', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:27:00', '2025-10-17 06:48:00'),
(137, 'ORD5731', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:27:39', '2025-10-17 06:48:00'),
(138, 'ORD7795', 'dine_in', 118.00, 0.00, NULL, 0.00, '', '2025-08-12 14:32:32', '2025-10-17 06:48:00'),
(139, 'ORD9527', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:34:12', '2025-10-17 06:48:00'),
(140, 'ORD7146', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:38:08', '2025-10-17 06:48:00'),
(141, 'ORD0540', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-12 14:41:56', '2025-10-17 06:48:00'),
(142, 'ORD9463', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:43:14', '2025-10-17 06:48:00'),
(143, 'ORD3621', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:43:36', '2025-10-17 06:48:00'),
(144, 'ORD5047', 'dine_in', 99.00, 0.00, NULL, 0.00, '', '2025-08-12 14:44:16', '2025-10-17 06:48:00'),
(145, 'ORD7910', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:45:06', '2025-10-17 06:48:00'),
(146, 'ORD7477', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:54:02', '2025-10-17 06:48:00'),
(147, 'ORD2336', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:57:04', '2025-10-17 06:48:00'),
(148, 'ORD2553', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 14:59:02', '2025-10-17 06:48:00'),
(149, 'ORD5633', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:02:17', '2025-10-17 06:48:00'),
(150, 'ORD0447', 'dine_in', 138.00, 0.00, NULL, 0.00, '', '2025-08-12 15:08:27', '2025-10-17 06:48:00'),
(151, 'ORD7595', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:12:10', '2025-10-17 06:48:00'),
(152, 'ORD1636', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:28:28', '2025-10-17 06:48:00'),
(153, 'ORD8580', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:31:28', '2025-10-17 06:48:00'),
(154, 'ORD4489', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:32:54', '2025-10-17 06:48:00'),
(155, 'ORD7927', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:33:29', '2025-10-17 06:48:00'),
(156, 'ORD4852', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:46:54', '2025-10-17 06:48:00'),
(157, 'ORD5511', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 15:47:53', '2025-10-17 06:48:00'),
(158, 'ORD2355', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:07:27', '2025-10-17 06:48:00'),
(159, 'ORD6820', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:22:40', '2025-10-17 06:48:00'),
(160, 'ORD6233', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:24:57', '2025-10-17 06:48:00'),
(161, 'ORD0521', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-12 16:26:11', '2025-10-17 06:48:00'),
(162, 'ORD5704', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-08-21 08:06:28', '2025-10-17 06:48:00'),
(163, 'ORD9753', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-08-29 04:53:19', '2025-10-17 06:48:00'),
(164, 'ORD8347', 'dine_in', 219.00, 0.00, NULL, 0.00, '', '2025-08-29 05:16:57', '2025-10-17 06:48:00'),
(165, 'ORD6991', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-09-10 09:01:08', '2025-10-17 06:48:00'),
(166, 'ORD6751', 'dine_in', 148.00, 0.00, NULL, 0.00, '', '2025-09-17 17:55:04', '2025-10-17 06:48:00'),
(167, 'ORD6743', 'dine_in', 238.00, 0.00, NULL, 0.00, '', '2025-10-01 22:06:35', '2025-10-17 06:48:00'),
(168, 'ORD5262', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-10 17:52:38', '2025-10-17 06:48:00'),
(169, 'ORD1774', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-10 17:53:27', '2025-10-17 06:48:00'),
(170, 'ORD5117', 'takeout', 59.00, 0.00, NULL, 0.00, '', '2025-10-10 17:54:55', '2025-10-17 06:48:00'),
(171, 'ORD2131', 'takeout', 59.00, 0.00, NULL, 0.00, '', '2025-10-10 17:55:19', '2025-10-17 06:48:00'),
(172, 'ORD1580', 'takeout', 107.00, 0.00, NULL, 0.00, '', '2025-10-10 18:29:20', '2025-10-17 06:48:00'),
(173, 'ORD7336', 'dine_in', 258.00, 0.00, NULL, 0.00, '', '2025-10-10 18:35:24', '2025-10-17 06:48:00'),
(174, 'ORD8440', 'dine_in', 89.00, 0.00, NULL, 0.00, '', '2025-10-11 05:55:18', '2025-10-17 06:48:00'),
(175, 'ORD1506', 'dine_in', 398.00, 0.00, NULL, 0.00, '', '2025-10-11 06:25:18', '2025-10-17 06:48:00'),
(176, 'ORD5178', 'takeout', 99.00, 0.00, NULL, 0.00, '', '2025-10-11 06:26:36', '2025-10-17 06:48:00'),
(177, 'ORD1488', 'takeout', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:27:25', '2025-10-17 06:48:00'),
(178, 'ORD7568', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:33:21', '2025-10-17 06:48:00'),
(179, 'ORD8485', 'dine_in', 59.00, 0.00, NULL, 0.00, '', '2025-10-11 06:34:10', '2025-10-17 06:48:00'),
(180, 'ORD3389', 'dine_in', 500.00, 0.00, NULL, 0.00, '', '2025-10-11 06:35:50', '2025-10-17 06:48:00'),
(181, 'ORD3081', 'dine_in', 237.00, 0.00, NULL, 0.00, '', '2025-10-11 06:37:21', '2025-10-17 06:48:00'),
(182, 'ORD2809', 'dine_in', 119.00, 0.00, NULL, 0.00, '', '2025-10-11 06:39:13', '2025-10-17 06:48:00'),
(183, 'ORD4542', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-11 06:41:04', '2025-10-17 06:48:00'),
(184, 'ORD5677', 'takeout', 590.00, 0.00, NULL, 0.00, '', '2025-10-11 06:45:00', '2025-10-17 06:48:00'),
(185, 'ORD8990', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-11 06:55:26', '2025-10-17 06:48:00'),
(186, 'ORD5267', 'dine_in', 239.00, 0.00, NULL, 0.00, '', '2025-10-11 06:58:06', '2025-10-17 06:48:00'),
(187, 'ORD1060', 'takeout', 378.00, 0.00, NULL, 0.00, '', '2025-10-11 06:59:36', '2025-10-17 06:48:00'),
(188, 'ORD5333', 'dine_in', 306.00, 0.00, NULL, 0.00, '', '2025-10-11 07:00:30', '2025-10-17 06:48:00'),
(189, 'ORD3338', 'dine_in', 219.00, 0.00, NULL, 0.00, '', '2025-10-11 07:13:40', '2025-10-17 06:48:00'),
(190, 'ORD0420', 'dine_in', 328.00, 0.00, NULL, 0.00, '', '2025-10-17 05:17:38', '2025-10-17 06:48:00'),
(191, 'ORD0526', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-17 06:02:54', '2025-10-17 06:48:00'),
(192, 'ORD1861', 'dine_in', 357.00, 0.00, NULL, 0.00, '', '2025-10-17 06:04:51', '2025-10-17 06:08:26'),
(193, 'ORD0466', 'dine_in', 69.00, 0.00, NULL, 0.00, '', '2025-10-17 06:06:01', '2025-10-17 06:06:31'),
(194, 'ORD5089', 'dine_in', 307.00, 0.00, NULL, 0.00, '', '2025-10-17 06:08:44', '2025-10-17 06:09:04'),
(195, 'ORD3502', 'dine_in', 179.00, 0.00, NULL, 0.00, '', '2025-10-17 06:09:15', '2025-10-17 06:48:00'),
(196, 'ORD1520', 'dine_in', 189.00, 0.00, NULL, 0.00, '', '2025-10-17 06:39:02', '2025-10-17 06:48:00'),
(197, 'TBL1008', 'takeout', 945.00, 0.00, NULL, 0.00, '', '2025-10-17 06:40:47', '2025-10-17 06:48:00'),
(198, 'TBL1962', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 06:51:17', '2025-10-17 09:17:34'),
(199, 'ORD0088', 'dine_in', 339.00, 0.00, NULL, 0.00, '', '2025-10-17 06:55:29', '2025-10-17 09:17:36'),
(200, 'ORD2296', 'dine_in', 129.00, 0.00, NULL, 0.00, '', '2025-10-17 07:08:57', '2025-10-17 07:46:52'),
(201, 'ORD5067', 'dine_in', 148.00, 0.00, NULL, 0.00, '', '2025-10-17 08:07:59', '2025-10-17 08:08:20'),
(202, 'ORD6918', 'dine_in', 129.00, 0.00, NULL, 0.00, '', '2025-10-17 08:34:47', '2025-10-17 09:17:38'),
(203, 'ORD7238', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 08:39:35', '2025-10-17 09:17:40'),
(204, 'TBL8381', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 08:48:37', '2025-10-17 09:17:42'),
(205, 'TBL8501', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:18:04', '2025-10-17 09:20:11'),
(206, 'TBL7189', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:20:47', '2025-10-17 09:22:28'),
(207, 'TBL2337', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:24:28', '2025-10-17 10:11:53'),
(208, 'ORD2762', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:25:37', '2025-10-17 10:12:10'),
(209, 'ORD6493', 'dine_in', 189.00, 0.00, NULL, 0.00, '', '2025-10-17 09:30:23', '2025-10-17 10:13:09'),
(210, 'TBL4078', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 09:33:55', '2025-10-17 10:13:11'),
(211, 'TBL8721', 'dine_in', 118.00, 0.00, NULL, 0.00, '', '2025-10-17 09:38:14', '2025-10-17 10:13:14'),
(212, 'ORD8090', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 10:14:16', '2025-10-17 13:34:55'),
(0, 'ORD5774', 'dine_in', 278.00, 0.00, NULL, 0.00, '', '2025-10-17 13:35:09', '2025-10-18 09:01:57'),
(0, 'ORD5070', 'dine_in', 100.00, 0.00, NULL, 0.00, '', '2025-10-17 13:36:30', '2025-10-18 09:01:50'),
(0, 'ORD1587', 'dine_in', 87.20, 0.00, NULL, 0.00, '', '2025-10-17 14:45:44', '2025-10-18 09:01:52'),
(0, 'TBL3236', 'dine_in', 200.00, 0.00, NULL, 0.00, '', '2025-10-17 16:00:34', '2025-10-18 08:46:24'),
(0, 'TBL9956', 'dine_in', 300.00, 0.00, NULL, 0.00, '', '2025-10-18 08:48:11', '2025-10-18 09:01:54');

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `stars` int(11) NOT NULL CHECK (`stars` between 1 and 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `stars`, `created_at`) VALUES
(1, 5, '2025-07-08 11:15:13'),
(2, 4, '2025-07-08 11:42:48'),
(3, 5, '2025-07-08 16:11:37'),
(4, 5, '2025-07-08 16:11:41'),
(5, 5, '2025-08-29 05:18:43'),
(6, 3, '2025-10-11 06:12:33'),
(7, 1, '2025-10-11 06:14:52'),
(8, 2, '2025-10-11 06:17:46'),
(9, 2, '2025-10-11 06:21:05'),
(10, 5, '2025-10-11 06:25:44'),
(11, 3, '2025-10-11 06:27:34'),
(12, 4, '2025-10-11 06:33:26'),
(13, 3, '2025-10-11 06:37:28'),
(14, 2, '2025-10-11 06:45:04'),
(15, 5, '2025-10-11 06:46:03'),
(16, 5, '2025-10-11 06:50:57'),
(17, 4, '2025-10-11 06:51:11'),
(18, 2, '2025-10-11 06:52:03'),
(19, 1, '2025-10-11 06:53:48'),
(20, 2, '2025-10-11 06:55:10'),
(21, 3, '2025-10-11 06:58:10'),
(22, 2, '2025-10-11 06:59:40'),
(23, 4, '2025-10-11 07:00:36'),
(24, 3, '2025-10-11 07:14:01'),
(0, 5, '2025-10-18 08:48:28');

-- --------------------------------------------------------

--
-- Table structure for table `sale_records`
--

CREATE TABLE `sale_records` (
  `ID` int(11) NOT NULL,
  `DATE_OF_SALE` date DEFAULT NULL,
  `TOTAL_SALE` decimal(10,2) DEFAULT NULL,
  `LAST_TRANSACT` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_records`
--

INSERT INTO `sale_records` (`ID`, `DATE_OF_SALE`, `TOTAL_SALE`, `LAST_TRANSACT`) VALUES
(11, '2025-06-29', 606.85, '102'),
(12, '2025-06-30', 5062.00, '102'),
(13, '2025-07-01', 3000.00, '102'),
(14, '2025-07-02', 2000.00, '102'),
(15, '2025-08-29', 7597.00, '103'),
(16, '2025-09-22', 490.50, '102'),
(17, '2024-01-03', 5956.73, '101'),
(18, '2024-01-05', 1139.19, '102'),
(19, '2024-01-09', 6491.91, '103'),
(20, '2024-01-12', 7043.82, '125'),
(21, '2024-01-14', 1990.73, '127'),
(22, '2024-01-16', 790.91, '101'),
(23, '2024-01-18', 4401.78, '102'),
(24, '2024-01-22', 4720.37, '103'),
(25, '2024-01-25', 1097.28, '125'),
(26, '2024-01-28', 568.72, '127'),
(27, '2024-02-02', 1436.71, '101'),
(28, '2024-02-04', 6150.28, '102'),
(29, '2024-02-08', 944.04, '103'),
(30, '2024-02-10', 7197.73, '125'),
(31, '2024-02-13', 2087.32, '127'),
(32, '2024-02-17', 3691.10, '101'),
(33, '2024-02-20', 2143.95, '102'),
(34, '2024-02-22', 2275.74, '103'),
(35, '2024-02-23', 7408.70, '125'),
(36, '2024-02-28', 5964.29, '127'),
(37, '2024-03-05', 2326.32, '101'),
(38, '2024-03-14', 6264.35, '102'),
(39, '2024-03-17', 6425.58, '103'),
(40, '2024-03-19', 5122.50, '125'),
(41, '2024-03-20', 1664.79, '127'),
(42, '2024-03-22', 1446.16, '101'),
(43, '2024-03-25', 4946.74, '102'),
(44, '2024-03-26', 7858.08, '103'),
(45, '2024-03-28', 5383.18, '125'),
(46, '2024-03-31', 3551.00, '127'),
(47, '2024-04-05', 1945.96, '101'),
(48, '2024-04-09', 7289.20, '102'),
(49, '2024-04-13', 5765.56, '103'),
(50, '2024-04-15', 5860.39, '125'),
(51, '2024-04-16', 5359.10, '127'),
(52, '2024-04-18', 5248.84, '101'),
(53, '2024-04-19', 3329.36, '102'),
(54, '2024-04-23', 3747.15, '103'),
(55, '2024-04-24', 1377.28, '125'),
(56, '2024-04-27', 4862.49, '127'),
(57, '2024-05-02', 4265.14, '101'),
(58, '2024-05-05', 4368.86, '102'),
(59, '2024-05-06', 1320.06, '103'),
(60, '2024-05-08', 3242.40, '125'),
(61, '2024-05-10', 3273.29, '127'),
(62, '2024-05-21', 7451.20, '101'),
(63, '2024-05-26', 6698.71, '102'),
(64, '2024-05-28', 1171.40, '103'),
(65, '2024-05-30', 6389.15, '125'),
(66, '2024-05-31', 7718.81, '127'),
(67, '2024-06-02', 7716.01, '101'),
(68, '2024-06-04', 1200.68, '102'),
(69, '2024-06-05', 4451.80, '103'),
(70, '2024-06-14', 6181.80, '125'),
(71, '2024-06-15', 6616.81, '127'),
(72, '2024-06-21', 5640.81, '101'),
(73, '2024-06-23', 2088.49, '102'),
(74, '2024-06-24', 2164.33, '103'),
(75, '2024-06-29', 3518.56, '125'),
(76, '2024-06-30', 7222.04, '127'),
(77, '2024-07-05', 1226.72, '101'),
(78, '2024-07-06', 6475.73, '102'),
(79, '2024-07-08', 7552.62, '103'),
(80, '2024-07-12', 6815.61, '125'),
(81, '2024-07-13', 3744.66, '127'),
(82, '2024-07-14', 2028.47, '101'),
(83, '2024-07-17', 971.92, '102'),
(84, '2024-07-23', 5360.67, '103'),
(85, '2024-07-26', 5167.74, '125'),
(86, '2024-07-30', 4283.86, '127'),
(87, '2024-08-01', 5254.26, '101'),
(88, '2024-08-03', 4577.03, '102'),
(89, '2024-08-04', 6568.59, '103'),
(90, '2024-08-11', 3731.08, '125'),
(91, '2024-08-16', 7091.74, '127'),
(92, '2024-08-22', 1534.03, '101'),
(93, '2024-08-23', 785.99, '102'),
(94, '2024-08-24', 6572.36, '103'),
(95, '2024-08-27', 7816.86, '125'),
(96, '2024-08-30', 1405.49, '127'),
(97, '2024-09-02', 4236.20, '101'),
(98, '2024-09-04', 4051.02, '102'),
(99, '2024-09-05', 1100.36, '103'),
(100, '2024-09-07', 1314.41, '125'),
(101, '2024-09-09', 5654.27, '127'),
(102, '2024-09-16', 7716.43, '101'),
(103, '2024-09-17', 5423.94, '102'),
(104, '2024-09-19', 4225.37, '103'),
(105, '2024-09-22', 7057.15, '125'),
(106, '2024-09-29', 7110.80, '127'),
(107, '2024-10-02', 5211.44, '101'),
(108, '2024-10-03', 3598.23, '102'),
(109, '2024-10-04', 2148.32, '103'),
(110, '2024-10-06', 4405.50, '125'),
(111, '2024-10-08', 4513.41, '127'),
(112, '2024-10-12', 4137.48, '101'),
(113, '2024-10-17', 5507.21, '102'),
(114, '2024-10-20', 1337.70, '103'),
(115, '2024-10-23', 1974.51, '125'),
(116, '2024-10-27', 3756.64, '127'),
(117, '2024-11-01', 6788.77, '101'),
(118, '2024-11-02', 2664.27, '102'),
(119, '2024-11-03', 4284.48, '103'),
(120, '2024-11-04', 2517.69, '125'),
(121, '2024-11-05', 6177.60, '127'),
(122, '2024-11-08', 2343.02, '101'),
(123, '2024-11-10', 7022.39, '102'),
(124, '2024-11-12', 6244.38, '103'),
(125, '2024-11-16', 3042.56, '125'),
(126, '2024-11-17', 3214.46, '127'),
(127, '2024-12-07', 2843.80, '101'),
(128, '2024-12-11', 4604.13, '102'),
(129, '2024-12-12', 7887.38, '103'),
(130, '2024-12-13', 4124.29, '125'),
(131, '2024-12-15', 2547.42, '127'),
(132, '2024-12-19', 5735.13, '101'),
(133, '2024-12-20', 5498.88, '102'),
(134, '2024-12-21', 1723.05, '103'),
(135, '2024-12-27', 5851.67, '125'),
(136, '2024-12-30', 1439.28, '127'),
(137, '2025-01-01', 4800.99, '101'),
(138, '2025-01-02', 1856.61, '102'),
(139, '2025-01-03', 5048.91, '103'),
(140, '2025-01-05', 7810.84, '125'),
(141, '2025-01-12', 6474.31, '127'),
(142, '2025-01-14', 2073.44, '101'),
(143, '2025-01-16', 3509.05, '102'),
(144, '2025-01-17', 6154.43, '103'),
(145, '2025-01-18', 5221.44, '125'),
(146, '2025-01-29', 7983.89, '127'),
(147, '2025-02-01', 1396.12, '101'),
(148, '2025-02-06', 2933.49, '102'),
(149, '2025-02-08', 3189.22, '103'),
(150, '2025-02-10', 6758.01, '125'),
(151, '2025-02-11', 2759.47, '127'),
(152, '2025-02-14', 3839.66, '101'),
(153, '2025-02-17', 4466.32, '102'),
(154, '2025-02-22', 6207.53, '103'),
(155, '2025-02-27', 2942.66, '125'),
(156, '2025-02-28', 962.70, '127'),
(157, '2025-03-02', 4385.26, '101'),
(158, '2025-03-07', 3063.41, '102'),
(159, '2025-03-09', 1262.59, '103'),
(160, '2025-03-12', 3014.02, '125'),
(161, '2025-03-13', 3677.45, '127'),
(162, '2025-03-14', 7593.52, '101'),
(163, '2025-03-16', 3237.34, '102'),
(164, '2025-03-21', 6786.44, '103'),
(165, '2025-03-23', 4522.82, '125'),
(166, '2025-03-30', 1789.26, '127'),
(167, '2025-04-04', 2647.87, '101'),
(168, '2025-04-10', 7663.64, '102'),
(169, '2025-04-11', 689.76, '103'),
(170, '2025-04-12', 7628.56, '125'),
(171, '2025-04-17', 3836.67, '127'),
(172, '2025-04-21', 1890.69, '101'),
(173, '2025-04-23', 2005.22, '102'),
(174, '2025-04-26', 5441.01, '103'),
(175, '2025-04-28', 6674.85, '125'),
(176, '2025-04-29', 4948.42, '127'),
(177, '2025-05-01', 1052.99, '101'),
(178, '2025-05-10', 2221.80, '102'),
(179, '2025-05-12', 2904.41, '103'),
(180, '2025-05-14', 5272.97, '125'),
(181, '2025-05-15', 5816.31, '127'),
(182, '2025-05-17', 760.67, '101'),
(183, '2025-05-18', 7149.04, '102'),
(184, '2025-05-20', 5448.17, '103'),
(185, '2025-05-24', 6052.90, '125'),
(186, '2025-05-31', 5671.21, '127'),
(187, '2025-06-03', 858.71, '101'),
(188, '2025-06-05', 2296.84, '102'),
(189, '2025-06-06', 5479.23, '103'),
(190, '2025-06-11', 2212.35, '125'),
(191, '2025-06-13', 3189.98, '127'),
(192, '2025-06-17', 3524.89, '101'),
(193, '2025-06-22', 5674.98, '102'),
(194, '2025-06-24', 2304.01, '103'),
(195, '2025-06-26', 3049.78, '125'),
(196, '2025-06-28', 3125.15, '127'),
(197, '2025-07-02', 4648.75, '101'),
(198, '2025-07-03', 4141.84, '102'),
(199, '2025-07-04', 3338.55, '103'),
(200, '2025-07-06', 7198.44, '125'),
(201, '2025-07-09', 6853.37, '127'),
(202, '2025-07-10', 5377.93, '101'),
(203, '2025-07-11', 6522.12, '102'),
(204, '2025-07-16', 6165.86, '103'),
(205, '2025-07-24', 1768.57, '125'),
(206, '2025-07-28', 3342.01, '127'),
(207, '2025-08-01', 2417.66, '101'),
(208, '2025-08-03', 7542.05, '102'),
(209, '2025-08-04', 1243.57, '103'),
(210, '2025-08-10', 2762.91, '125'),
(211, '2025-08-11', 1692.28, '127'),
(212, '2025-08-12', 3234.35, '101'),
(213, '2025-08-14', 1904.27, '102'),
(214, '2025-08-15', 6452.68, '103'),
(215, '2025-08-16', 3886.53, '125'),
(216, '2025-08-21', 7257.81, '127'),
(217, '2025-09-05', 917.63, '101'),
(218, '2025-09-13', 3643.75, '102'),
(219, '2025-09-15', 6648.45, '103'),
(220, '2025-09-16', 5186.83, '125'),
(221, '2025-09-17', 836.98, '127'),
(222, '2025-09-20', 777.14, '101'),
(223, '2025-09-21', 5130.67, '102'),
(224, '2025-09-22', 3300.41, '103'),
(225, '2025-09-25', 4001.24, '125'),
(226, '2025-09-26', 7106.24, '127'),
(227, '2025-10-02', 1860.37, '101'),
(228, '2025-10-09', 5874.86, '102'),
(229, '2025-10-13', 4713.52, '103'),
(230, '2025-10-14', 4804.69, '125'),
(231, '2025-10-16', 6442.74, '127'),
(232, '2025-10-17', 2228.68, '101'),
(233, '2025-10-25', 5014.13, '102'),
(234, '2025-10-27', 3601.65, '103'),
(235, '2025-10-28', 779.80, '125'),
(236, '2025-10-31', 1913.63, '127'),
(237, '2025-11-01', 2212.17, '101'),
(238, '2025-11-03', 6389.50, '102'),
(239, '2025-11-05', 2084.99, '103'),
(240, '2025-11-15', 4728.20, '125'),
(241, '2025-11-16', 1162.79, '127'),
(242, '2025-11-20', 7481.40, '101'),
(243, '2025-11-21', 4119.63, '102'),
(244, '2025-11-22', 5800.13, '103'),
(245, '2025-11-24', 4962.56, '125'),
(246, '2025-11-30', 5127.75, '127'),
(247, '2025-12-03', 6017.41, '101'),
(248, '2025-12-05', 898.30, '102'),
(249, '2025-12-13', 5643.55, '103'),
(250, '2025-12-16', 4327.02, '125'),
(251, '2025-12-22', 4190.11, '127'),
(252, '2025-12-25', 5665.77, '101'),
(253, '2025-12-26', 1272.85, '102'),
(254, '2025-12-28', 2903.98, '103'),
(255, '2025-12-30', 1368.61, '125'),
(256, '2025-12-31', 675.64, '127'),
(257, '2025-10-17', 8263.00, '102'),
(258, '2025-10-17', 8263.00, '102'),
(259, '2025-10-17', 8263.00, '102'),
(260, '2025-10-17', 8263.00, '102'),
(261, '2025-10-17', 8263.00, '102'),
(262, '2025-10-17', 8263.00, '102'),
(263, '2025-10-17', 8263.00, '102'),
(264, '2025-10-17', 8263.00, '102'),
(265, '2025-10-17', 12658.00, '102'),
(0, '2025-10-18', 965.20, '102');

-- --------------------------------------------------------

--
-- Table structure for table `stock_history`
--

CREATE TABLE `stock_history` (
  `ID` int(11) NOT NULL,
  `PROD_ID` int(20) NOT NULL,
  `PROD_NAME` varchar(50) NOT NULL,
  `QTY_ADDED` int(10) NOT NULL,
  `TOT_PRICE` int(10) NOT NULL,
  `DATE_ADD` date NOT NULL,
  `TIME_ADD` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `stock_history`
--

INSERT INTO `stock_history` (`ID`, `PROD_ID`, `PROD_NAME`, `QTY_ADDED`, `TOT_PRICE`, `DATE_ADD`, `TIME_ADD`) VALUES
(10, 1, 'BEEF WAGYU', 4, 1000, '2025-09-19', '10:10 PM'),
(11, 11, 'HUNGARIAN SAUSAGE', 30, 6000, '2025-10-11', '05:41 PM'),
(12, 21, 'KIWI SYRUP', 1800, 360000, '2025-10-11', '05:44 PM'),
(13, 16, 'BLUEBERRY SYRUP', 1000, 120000, '2025-10-11', '05:44 PM'),
(14, 23, 'COFFEE BASE', 1000, 80000, '2025-10-11', '05:44 PM'),
(15, 18, 'LYCHEE SYRUP', 1000, 150000, '2025-10-11', '05:44 PM'),
(16, 17, 'MANGO SYRUP', 1000, 150000, '2025-10-11', '05:44 PM'),
(17, 13, 'MILK', 1000, 75000, '2025-10-11', '05:45 PM'),
(18, 19, 'STRAWBERRY SYRUP', 1000, 150000, '2025-10-11', '05:45 PM'),
(19, 22, 'TEA BASE', 400, 30000, '2025-10-11', '05:45 PM'),
(20, 22, 'TEA BASE', 1405, 105349, '2025-10-11', '07:40 PM'),
(21, 20, 'WATERMELON SYRUP', 1800, 360000, '2025-10-11', '11:02 PM'),
(0, 10, 'PEPPER BEEF', 60, 15000, '2025-10-18', '03:31 PM');

-- --------------------------------------------------------

--
-- Table structure for table `unified_inventory`
--

CREATE TABLE `unified_inventory` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `is_liquid` tinyint(1) DEFAULT 0,
  `current_quantity` decimal(10,3) NOT NULL DEFAULT 0.000,
  `min_threshold` decimal(10,3) NOT NULL DEFAULT 5.000,
  `cost_per_unit` decimal(10,2) NOT NULL,
  `supplier_info` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unified_inventory`
--

INSERT INTO `unified_inventory` (`item_id`, `item_name`, `category`, `unit`, `is_liquid`, `current_quantity`, `min_threshold`, `cost_per_unit`, `supplier_info`, `last_updated`) VALUES
(1, 'BEEF WAGYU', 'PROTEINS', 'PCS', 0, 21.000, 10.000, 250.00, NULL, '2025-10-17 13:35:09'),
(2, 'BEEF BULGOGI', 'PROTEINS', 'PCS', 0, 94.000, 10.000, 150.00, NULL, '2025-10-11 06:25:19'),
(3, 'BEEF TENDERLOIN', 'PROTEINS', 'PCS', 0, 51.000, 5.000, 200.00, NULL, '2025-08-26 12:29:27'),
(4, 'BURGER PATTIES', 'PROTEINS', 'PCS', 0, 69.000, 20.000, 30.00, NULL, '2025-10-18 09:11:28'),
(5, 'CHICKEN', 'PROTEINS', 'PCS', 0, 93.000, 20.000, 120.00, NULL, '2025-10-17 06:39:02'),
(6, 'CHICKEN WINGS', 'PROTEINS', 'PCS', 0, 165.000, 50.000, 25.00, NULL, '2025-10-18 09:12:03'),
(7, 'PORK ASADO', 'PROTEINS', 'PCS', 0, 100.000, 10.000, 120.00, NULL, '2025-06-03 06:58:39'),
(8, 'PORK LIEMPO', 'PROTEINS', 'PCS', 0, 100.000, 10.000, 150.00, NULL, '2025-06-03 06:58:39'),
(9, 'DOUBLE PORKCHOP', 'PROTEINS', 'PCS', 0, 100.000, 10.000, 200.00, NULL, '2025-06-03 06:58:39'),
(10, 'PEPPER BEEF', 'PROTEINS', 'PCS', 0, 90.000, 10.000, 250.00, NULL, '2025-10-18 07:31:19'),
(11, 'HUNGARIAN SAUSAGE', 'PROTEINS', 'PCS', 0, 41.000, 10.000, 200.00, NULL, '2025-10-11 09:41:05'),
(12, 'TUNA', 'PROTEINS', 'PCS', 0, 98.000, 10.000, 40.00, NULL, '2025-10-17 13:35:09'),
(13, 'MILK', 'DAIRY', 'LITER', 1, 1095.850, 10.000, 75.00, NULL, '2025-10-17 14:39:33'),
(14, 'CREAM CHEESE', 'DAIRY', 'KG', 0, 99.940, 5.000, 120.00, NULL, '2025-10-11 06:26:36'),
(15, 'CHEESE', 'DAIRY', 'PCS', 0, 53.000, 20.000, 56.00, NULL, '2025-10-18 09:11:28'),
(16, 'BLUEBERRY SYRUP', 'SYRUP', 'LITER', 1, 1094.910, 5.000, 120.00, NULL, '2025-10-18 09:11:28'),
(17, 'MANGO SYRUP', 'SYRUP', 'LITER', 1, 1095.940, 5.000, 150.00, NULL, '2025-10-17 08:08:00'),
(18, 'LYCHEE SYRUP', 'SYRUP', 'LITER', 1, 1098.800, 5.000, 150.00, NULL, '2025-10-11 09:44:49'),
(19, 'STRAWBERRY SYRUP', 'SYRUP', 'LITER', 1, 1100.000, 5.000, 150.00, NULL, '2025-10-11 09:45:07'),
(20, 'WATERMELON SYRUP', 'SYRUP', 'LITER', 1, 1900.000, 5.000, 200.00, NULL, '2025-10-11 15:02:24'),
(21, 'KIWI SYRUP', 'SYRUP', 'LITER', 1, 1900.000, 5.000, 200.00, NULL, '2025-10-11 09:44:24'),
(22, 'TEA BASE', 'BEVERAGES', 'LITER', 1, 1899.150, 10.000, 75.00, NULL, '2025-10-17 14:39:33'),
(23, 'COFFEE BASE', 'BEVERAGES', 'LITER', 1, 1099.800, 10.000, 80.00, NULL, '2025-10-11 09:44:44'),
(24, 'RICE', 'GRAINS', 'KG', 0, 94.400, 20.000, 60.00, NULL, '2025-10-17 13:35:09'),
(25, 'BUNS', 'BAKERY', 'PCS', 0, 41.000, 30.000, 25.00, NULL, '2025-10-18 09:11:28'),
(26, 'FRIES', 'FROZEN', 'KG', 0, 98.200, 10.000, 80.00, NULL, '2025-10-17 09:38:14'),
(27, 'PASTA NOODLES', 'GRAINS', 'KG', 0, 99.300, 10.000, 75.00, NULL, '2025-10-17 13:35:09'),
(28, 'BLACK PEARLS', 'TOPPINGS', 'KG', 0, 99.490, 5.000, 80.00, NULL, '2025-10-17 14:39:33'),
(29, 'CRYSTALS', 'TOPPINGS', 'KG', 0, 99.910, 5.000, 80.00, NULL, '2025-10-17 08:07:59'),
(30, 'PLASTIC CUPS', 'DISPOSABLES', 'PCS', 0, 100.000, 1.000, 200.00, NULL, '2025-10-18 06:07:45'),
(31, 'TISSUE', 'DISPOSABLES', 'PCS', 0, 60.000, 1.000, 100.00, NULL, '2025-10-18 06:08:46');

-- --------------------------------------------------------

--
-- Table structure for table `unified_menu_system`
--

CREATE TABLE `unified_menu_system` (
  `id` int(11) NOT NULL,
  `menu_item_id` varchar(50) NOT NULL,
  `menu_item_name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `section` enum('drinks','food','addons') NOT NULL,
  `size_id` varchar(50) NOT NULL DEFAULT 'REG',
  `price` decimal(10,2) NOT NULL,
  `ingredient_id` int(11) DEFAULT NULL,
  `quantity_required` decimal(10,3) DEFAULT NULL,
  `is_liquid_ingredient` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unified_menu_system`
--

INSERT INTO `unified_menu_system` (`id`, `menu_item_id`, `menu_item_name`, `category`, `section`, `size_id`, `price`, `ingredient_id`, `quantity_required`, `is_liquid_ingredient`, `description`, `is_available`, `created_at`) VALUES
(1, 'CHEESEBURGER', 'Cheeseburger', 'BURGERS', 'food', 'REG', 109.00, 4, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(2, 'CHEESEBURGER', 'Cheeseburger', 'BURGERS', 'food', 'REG', 109.00, 25, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(3, 'CHEESEBURGER', 'Cheeseburger', 'BURGERS', 'food', 'REG', 109.00, 15, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(4, 'BBQMUSHROOMBURGER', 'BBQ Mushroom Burger', 'BURGERS', 'food', 'REG', 119.00, 4, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(5, 'BBQMUSHROOMBURGER', 'BBQ Mushroom Burger', 'BURGERS', 'food', 'REG', 119.00, 25, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(6, 'MONSTERBURGER', 'Monster Burger', 'BURGERS', 'food', 'REG', 139.00, 4, 2.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(7, 'MONSTERBURGER', 'Monster Burger', 'BURGERS', 'food', 'REG', 139.00, 25, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(8, 'MONSTERBURGER', 'Monster Burger', 'BURGERS', 'food', 'REG', 139.00, 15, 2.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(9, 'BEEFWAGYU', 'Beef Wagyu', 'RICEDISH', 'food', 'REG', 189.00, 1, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(10, 'BEEFWAGYU', 'Beef Wagyu', 'RICEDISH', 'food', 'REG', 189.00, 24, 0.200, 0, NULL, 1, '2025-06-03 06:58:39'),
(11, 'BEEFBULGOGI', 'Beef Bulgogi', 'RICEDISH', 'food', 'REG', 179.00, 2, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(12, 'BEEFBULGOGI', 'Beef Bulgogi', 'RICEDISH', 'food', 'REG', 179.00, 24, 0.200, 0, NULL, 1, '2025-06-03 06:58:39'),
(13, 'CHICKENINASAL', 'Chicken Inasal', 'SIZZLERS', 'food', 'REG', 189.00, 5, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(14, 'CHICKENINASAL', 'Chicken Inasal', 'SIZZLERS', 'food', 'REG', 189.00, 24, 0.200, 0, NULL, 1, '2025-06-03 06:58:39'),
(15, 'WINGS5PCS', '5 pcs Wings', 'WINGS', 'food', 'REG', 129.00, 6, 5.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(16, 'WINGS10PCS', '10 pcs Wings', 'WINGS', 'food', 'REG', 239.00, 6, 10.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(17, 'WINGS15PCS', '15 pcs Wings', 'WINGS', 'food', 'REG', 339.00, 6, 15.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(18, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'REG', 89.00, 13, 0.200, 1, NULL, 1, '2025-06-03 06:58:39'),
(19, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'REG', 89.00, 16, 0.050, 1, NULL, 1, '2025-06-03 06:58:39'),
(20, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'REG', 89.00, 22, 0.150, 1, NULL, 1, '2025-06-03 06:58:39'),
(21, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'REG', 89.00, 28, 0.030, 0, NULL, 1, '2025-06-03 06:58:39'),
(22, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'GRANDE', 79.00, 13, 0.250, 1, NULL, 1, '2025-06-03 06:58:39'),
(23, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'GRANDE', 79.00, 16, 0.070, 1, NULL, 1, '2025-06-03 06:58:39'),
(24, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'GRANDE', 79.00, 22, 0.180, 1, NULL, 1, '2025-06-03 06:58:39'),
(25, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'GRANDE', 79.00, 28, 0.040, 0, NULL, 1, '2025-06-03 06:58:39'),
(26, 'HOKKAIDO', 'Hokkaido Premium Milk Tea', 'PREMIUM MILKTEA', 'drinks', 'REG', 99.00, 13, 0.250, 1, NULL, 1, '2025-06-03 06:58:39'),
(27, 'HOKKAIDO', 'Hokkaido Premium Milk Tea', 'PREMIUM MILKTEA', 'drinks', 'REG', 99.00, 22, 0.150, 1, NULL, 1, '2025-06-03 06:58:39'),
(28, 'HOKKAIDO', 'Hokkaido Premium Milk Tea', 'PREMIUM MILKTEA', 'drinks', 'REG', 99.00, 14, 0.020, 0, NULL, 1, '2025-06-03 06:58:39'),
(29, 'HOKKAIDO', 'Hokkaido Premium Milk Tea', 'PREMIUM MILKTEA', 'drinks', 'GRANDE', 89.00, 13, 0.300, 1, NULL, 1, '2025-06-03 06:58:39'),
(30, 'HOKKAIDO', 'Hokkaido Premium Milk Tea', 'PREMIUM MILKTEA', 'drinks', 'GRANDE', 89.00, 22, 0.200, 1, NULL, 1, '2025-06-03 06:58:39'),
(31, 'HOKKAIDO', 'Hokkaido Premium Milk Tea', 'PREMIUM MILKTEA', 'drinks', 'GRANDE', 89.00, 14, 0.030, 0, NULL, 1, '2025-06-03 06:58:39'),
(32, 'MANGO', 'Mango Fruit Tea', 'FRUIT TEA', 'drinks', 'REG', 79.00, 17, 0.100, 1, NULL, 1, '2025-06-03 06:58:39'),
(33, 'MANGO', 'Mango Fruit Tea', 'FRUIT TEA', 'drinks', 'REG', 79.00, 22, 0.200, 1, NULL, 1, '2025-06-03 06:58:39'),
(34, 'MANGO', 'Mango Fruit Tea', 'FRUIT TEA', 'drinks', 'REG', 79.00, 29, 0.030, 0, NULL, 1, '2025-06-03 06:58:39'),
(35, 'LYCHEE_FRUITTEA', 'Lychee Fruit Tea', 'FRUIT TEA', 'drinks', 'REG', 79.00, 18, 0.100, 1, NULL, 1, '2025-06-03 06:58:39'),
(36, 'LYCHEE_FRUITTEA', 'Lychee Fruit Tea', 'FRUIT TEA', 'drinks', 'REG', 79.00, 22, 0.200, 1, NULL, 1, '2025-06-03 06:58:39'),
(37, 'BLUEBERRY_COOLRS', 'Blueberry Cooler', 'COOLERS', 'drinks', 'REG', 100.00, 16, 0.080, 1, NULL, 1, '2025-06-03 06:58:39'),
(38, 'BLUEBERRY_COOLRS', 'Blueberry Cooler', 'COOLERS', 'drinks', 'GRANDE', 59.00, 16, 0.100, 1, NULL, 1, '2025-06-03 06:58:39'),
(39, 'MNGO', 'Mango Cooler', 'COOLERS', 'drinks', 'REG', 69.00, 17, 0.080, 1, NULL, 1, '2025-06-03 06:58:39'),
(40, 'MNGO', 'Mango Cooler', 'COOLERS', 'drinks', 'GRANDE', 59.00, 17, 0.100, 1, NULL, 1, '2025-06-03 06:58:39'),
(41, 'CAPPUCCINO', 'Cappuccino Frappe', 'FRAPPE', 'drinks', 'REG', 119.00, 23, 0.150, 1, NULL, 1, '2025-06-03 06:58:39'),
(42, 'CAPPUCCINO', 'Cappuccino Frappe', 'FRAPPE', 'drinks', 'REG', 119.00, 13, 0.100, 1, NULL, 1, '2025-06-03 06:58:39'),
(43, 'CAPPUCCINO', 'Cappuccino Frappe', 'FRAPPE', 'drinks', 'GRANDE', 109.00, 23, 0.200, 1, NULL, 1, '2025-06-03 06:58:39'),
(44, 'CAPPUCCINO', 'Cappuccino Frappe', 'FRAPPE', 'drinks', 'GRANDE', 109.00, 13, 0.150, 1, NULL, 1, '2025-06-03 06:58:39'),
(45, 'CARBONARA', 'Carbonara', 'PASTA', 'food', 'REG', 119.00, 27, 0.100, 0, NULL, 1, '2025-06-03 06:58:39'),
(46, 'CARBONARA', 'Carbonara', 'PASTA', 'food', 'REG', 119.00, 15, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(47, 'CARBONARA', 'Carbonara', 'PASTA', 'food', 'REG', 119.00, 13, 0.050, 1, NULL, 1, '2025-06-03 06:58:39'),
(48, 'TUNAPASTA', 'Tuna Pasta', 'PASTA', 'food', 'REG', 89.00, 27, 0.100, 0, NULL, 1, '2025-06-03 06:58:39'),
(49, 'TUNAPASTA', 'Tuna Pasta', 'PASTA', 'food', 'REG', 89.00, 12, 1.000, 0, NULL, 1, '2025-06-03 06:58:39'),
(50, 'FRIES', 'French Fries', 'SNACKS', 'food', 'REG', 59.00, 26, 0.150, 0, NULL, 1, '2025-06-03 06:58:39'),
(51, 'PLAINRICE', 'Plain Rice', 'ADDONS', 'addons', 'REG', 19.00, 24, 0.100, 0, NULL, 1, '2025-06-03 06:58:39'),
(52, 'GARLICRICE', 'Garlic Rice', 'ADDONS', 'addons', 'REG', 29.00, 24, 0.100, 0, NULL, 1, '2025-06-03 06:58:39'),
(53, 'BLACKPEARL', 'Black Pearl', 'ADDONS', 'addons', 'REG', 19.00, 28, 0.030, 0, NULL, 1, '2025-06-03 06:58:39'),
(54, 'CRYSTAL', 'Crystal', 'ADDONS', 'addons', 'REG', 19.00, 29, 0.030, 0, NULL, 1, '2025-06-03 06:58:39'),
(57, 'MNGO', 'Mango Cooler', 'COOLERS', 'drinks', 'REG', 69.00, 30, 1.000, 0, NULL, 1, '2025-10-11 09:30:12'),
(58, 'MNGO', 'Mango Cooler', 'COOLERS', 'drinks', 'GRANDE', 59.00, 30, 1.000, 0, NULL, 1, '2025-10-11 09:30:12'),
(59, 'MNGO', 'Mango Cooler', 'COOLERS', 'drinks', 'REG', 69.00, 31, 1.000, 0, NULL, 1, '2025-10-16 16:51:37'),
(60, 'MNGO', 'Mango Cooler', 'COOLERS', 'drinks', 'GRANDE', 59.00, 31, 1.000, 0, NULL, 1, '2025-10-16 16:51:37'),
(61, 'UNLICHICKENWINGS', 'Unli Chicken Wings', 'WINGS', 'food', 'REG', 100.00, NULL, NULL, 0, '', 1, '2025-10-17 08:37:56'),
(62, 'UNLICHICKENWINGS', 'Unli Chicken Wings', 'WINGS', 'food', 'REG', 100.00, 6, 6.000, 0, NULL, 1, '2025-10-17 08:38:40'),
(0, 'MNGO', 'Mango Cooler', 'COOLERS', 'drinks', 'REG', 69.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'MNGO', 'Mango Cooler', 'COOLERS', 'drinks', 'GRANDE', 59.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'CAPPUCCINO', 'Cappuccino Frappe', 'FRAPPE', 'drinks', 'REG', 119.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'CAPPUCCINO', 'Cappuccino Frappe', 'FRAPPE', 'drinks', 'GRANDE', 109.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'MANGO', 'Mango Fruit Tea', 'FRUIT TEA', 'drinks', 'REG', 79.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'REG', 89.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'BLBERRY', 'Blueberry Milk Tea', 'MILKTEA', 'drinks', 'GRANDE', 79.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'HOKKAIDO', 'Hokkaido Premium Milk Tea', 'PREMIUM MILKTEA', 'drinks', 'REG', 99.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'HOKKAIDO', 'Hokkaido Premium Milk Tea', 'PREMIUM MILKTEA', 'drinks', 'GRANDE', 89.00, 0, 1.000, 0, NULL, 1, '2025-10-18 06:07:45'),
(0, 'WINGS10PCS', '10 pcs Wings', 'WINGS', 'food', 'REG', 239.00, 0, 5.000, 0, NULL, 1, '2025-10-18 06:08:46'),
(0, 'WINGS15PCS', '15 pcs Wings', 'WINGS', 'food', 'REG', 339.00, 0, 5.000, 0, NULL, 1, '2025-10-18 06:08:46'),
(0, 'WINGS5PCS', '5 pcs Wings', 'WINGS', 'food', 'REG', 129.00, 0, 5.000, 0, NULL, 1, '2025-10-18 06:08:46'),
(0, 'UNLICHICKENWINGS', 'Unli Chicken Wings', 'WINGS', 'food', 'REG', 100.00, 0, 5.000, 0, NULL, 1, '2025-10-18 06:08:46'),
(0, 'ITALIANSPAGHETTI', 'Italian Spaghetti', 'PASTA', 'food', 'REG', 150.00, NULL, NULL, 0, '', 1, '2025-10-18 07:29:19'),
(0, 'ONIONRINGS', 'Onion Rings', 'SNACKS', 'food', 'REG', 89.00, NULL, NULL, 0, '', 1, '2025-10-18 07:29:46'),
(0, 'ITALIANSPAGHETTI', 'Italian Spaghetti', 'PASTA', 'food', 'REG', 150.00, 27, 1.000, 0, NULL, 1, '2025-10-18 07:30:54');

-- --------------------------------------------------------

--
-- Table structure for table `void_logs`
--

CREATE TABLE `void_logs` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `size` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `flavor` varchar(100) DEFAULT NULL,
  `void_type` varchar(20) NOT NULL,
  `voided_by` varchar(50) NOT NULL,
  `pin_used` varchar(255) NOT NULL,
  `void_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `void_logs`
--

INSERT INTO `void_logs` (`id`, `item_name`, `size`, `quantity`, `price`, `flavor`, `void_type`, `voided_by`, `pin_used`, `void_time`) VALUES
(10, 'Blueberry Cooler', 'REG', 1, 69.00, '', 'Void All', 'Manager', '1234', '2025-06-04 09:20:30'),
(11, 'Mango Cooler', 'REG', 1, 69.00, '', 'Void All', 'Manager', '1234', '2025-06-04 09:20:30'),
(12, 'Mango Cooler', 'REG', 1, 69.00, '', 'Single Item', 'Manager', '1234', '2025-06-04 09:38:36'),
(13, 'Monster Burger', 'REG', 1, 139.00, '', 'Void All', 'Manager', '0000', '2025-07-04 09:39:40'),
(14, 'Cheeseburger', 'REG', 1, 109.00, '', 'Single Item', 'Manager', '0000', '2025-07-04 09:41:52'),
(15, 'Cheeseburger', 'REG', 1, 109.00, '', 'Void All', 'Manager', '0000.', '2025-07-04 09:42:54'),
(16, 'Cheeseburger', 'REG', 1, 109.00, '', 'Void All', 'Manager', '0000', '2025-07-04 09:44:10'),
(17, 'Cheeseburger', 'REG', 1, 109.00, '', 'Void All', 'Manager', '0000', '2025-07-04 09:46:18'),
(18, 'Cheeseburger', 'REG', 1, 109.00, '', 'Void All', 'Manager', '0000', '2025-07-04 09:48:49'),
(19, 'Mango Cooler', 'REG', 1, 69.00, '', 'Void All', 'Manager', '0000', '2025-07-04 09:54:28'),
(20, 'Cheeseburger', 'REG', 1, 109.00, '', 'Void All', 'Manager', '0000', '2025-07-04 09:54:28'),
(21, 'BBQ Mushroom Burger', 'REG', 1, 119.00, '', 'Void All', 'Manager', '0000', '2025-07-04 10:00:07'),
(22, 'Cheeseburger', 'REG', 1, 109.00, '', 'Void All', 'Manager', '0000', '2025-07-04 10:01:41'),
(23, 'Mango Cooler', 'REG', 1, 69.00, '', 'Void All', 'Manager', '0000', '2025-07-04 10:14:59'),
(24, 'Mango Cooler', 'REG', 1, 69.00, '', 'Void All', 'Manager', '0000', '2025-07-08 12:05:27'),
(25, 'Mango Cooler', 'REG', 1, 69.00, '', 'Void All', 'Manager', '0000', '2025-07-08 12:07:10'),
(26, 'Mango Cooler', 'REG', 1, 69.00, '', 'Single Item', 'Manager', '0000', '2025-08-21 08:03:07'),
(27, 'Cheeseburger', 'REG', 1, 109.00, '', 'Single Item', 'Manager', '0000', '2025-08-29 05:14:36'),
(28, 'BBQ Mushroom Burger', 'REG', 1, 119.00, '', 'Single Item', 'Manager', '0000', '2025-09-10 13:21:15'),
(29, '10 pcs Wings', 'REG', 1, 239.00, 'Original', 'Void All', 'Manager', '1234', '2025-09-10 13:21:29'),
(30, '10 pcs Wings', 'REG', 2, 478.00, 'Original', 'Single Item', 'Manager', '0000', '2025-09-10 13:33:05'),
(31, '5 pcs Wings', 'REG', 1, 129.00, 'Buffalo', 'Single Item', 'Manager', '0000', '2025-10-01 22:05:33'),
(32, 'Blueberry Milk Tea', 'REG', 1, 89.00, '', 'Void All', 'Manager', '0000', '2025-10-11 10:12:40'),
(33, 'Blueberry Cooler', 'REG', 5, 500.00, '', 'Void All', 'Manager', '0000', '2025-10-11 10:12:40'),
(34, 'Unli Chicken Wings', 'REG', 1, 100.00, 'Honey Garlic', 'Single Item', 'Manager', '0000', '2025-10-17 16:11:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`ACC_ID`);

--
-- Indexes for table `acc_archive`
--
ALTER TABLE `acc_archive`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `unified_inventory`
--
ALTER TABLE `unified_inventory`
  ADD PRIMARY KEY (`item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `unified_inventory`
--
ALTER TABLE `unified_inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
