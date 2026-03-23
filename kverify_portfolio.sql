-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 12, 2026 at 12:34 PM
-- Server version: 5.7.34
-- PHP Version: 8.2.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kverify_portfolio`
--

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `GetConversionRate` (IN `p_start_date` DATE, IN `p_end_date` DATE)
BEGIN
    SELECT 
        COUNT(DISTINCT CASE WHEN converted THEN session_id END) / 
        COUNT(DISTINCT session_id) * 100 as conversion_rate
    FROM user_sessions
    WHERE DATE(start_time) BETWEEN p_start_date AND p_end_date;
END$$

CREATE PROCEDURE `GetDailyActiveUsers` (IN `p_date` DATE)
BEGIN
    SELECT COUNT(DISTINCT visitor_id) as active_users
    FROM page_views
    WHERE DATE(visit_date) = p_date;
END$$

CREATE PROCEDURE `GetTopPages` (IN `p_start_date` DATE, IN `p_end_date` DATE, IN `p_limit` INT)
BEGIN
    SELECT page_url, 
           COUNT(*) as views,
           COUNT(DISTINCT visitor_id) as unique_visitors
    FROM page_views
    WHERE visit_date BETWEEN p_start_date AND p_end_date
    GROUP BY page_url
    ORDER BY views DESC
    LIMIT p_limit;
END$$

CREATE PROCEDURE `GetTrafficSources` (IN `p_start_date` DATE, IN `p_end_date` DATE)
BEGIN
    SELECT 
        CASE 
            WHEN referrer_url IS NULL OR referrer_url = '' THEN 'Direct'
            WHEN referrer_url LIKE '%google.%' THEN 'Google'
            WHEN referrer_url LIKE '%facebook.%' THEN 'Facebook'
            WHEN referrer_url LIKE '%twitter.%' THEN 'Twitter'
            WHEN referrer_url LIKE '%linkedin.%' THEN 'LinkedIn'
            WHEN referrer_url LIKE '%github.%' THEN 'GitHub'
            ELSE 'Other'
        END as source,
        COUNT(DISTINCT session_id) as sessions,
        COUNT(DISTINCT visitor_id) as visitors
    FROM user_sessions
    WHERE DATE(start_time) BETWEEN p_start_date AND p_end_date
    GROUP BY source
    ORDER BY sessions DESC;
END$$

CREATE PROCEDURE `UpdateSession` (IN `p_session_id` VARCHAR(100))
BEGIN
    UPDATE user_sessions 
    SET end_time = NOW(),
        duration = TIMESTAMPDIFF(SECOND, start_time, NOW()),
        bounced = CASE WHEN page_views <= 1 THEN TRUE ELSE FALSE END
    WHERE session_id = p_session_id;
END$$

DELIMITER ;

-- [Rest of your dump continues exactly as before...]
-- --------------------------------------------------------

--
-- Table structure for table `ab_tests`
--

CREATE TABLE `ab_tests` (
  `id` int(11) NOT NULL,
  `test_name` varchar(100) NOT NULL,
  `test_description` text,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','paused','completed') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `ab_test_results`
--

CREATE TABLE `ab_test_results` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `visitor_id` varchar(100) DEFAULT NULL,
  `impressions` int(11) DEFAULT '0',
  `conversions` int(11) DEFAULT '0',
  `revenue` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `ab_test_variants`
--

CREATE TABLE `ab_test_variants` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `variant_name` varchar(50) NOT NULL,
  `variant_code` text,
  `traffic_percentage` int(11) DEFAULT '50',
  `is_control` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `account_deletion_requests`
--

CREATE TABLE `account_deletion_requests` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `reason` text,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `requested_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `admin_notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `data` json DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `type`, `message`, `data`, `is_read`, `created_at`) VALUES
(1, 'new_client_message', 'New message from client: Amin Abdullah - Training', '{\"subject\": \"Training\", \"client_name\": \"Amin Abdullah\"}', 0, '2026-03-11 12:02:53'),
(2, 'new_client_message', 'New message from client: Amin Abdullah - Newsletter', '{\"subject\": \"Newsletter\", \"client_name\": \"Amin Abdullah\"}', 0, '2026-03-11 15:31:31'),
(3, 'new_client_message', 'New message from client: Muhammad Muktar - Test Subject', '{\"subject\": \"Test Subject\", \"client_name\": \"Muhammad Muktar\"}', 0, '2026-03-11 17:21:45'),
(4, 'new_client_message', 'New message from client: Muhammad Muktar - Test Subject', '{\"subject\": \"Test Subject\", \"client_name\": \"Muhammad Muktar\"}', 0, '2026-03-11 17:22:39');

-- --------------------------------------------------------

--
-- Table structure for table `analytics_alerts`
--

CREATE TABLE `analytics_alerts` (
  `id` int(11) NOT NULL,
  `alert_name` varchar(100) NOT NULL,
  `alert_condition` varchar(255) NOT NULL,
  `alert_threshold` decimal(10,2) DEFAULT NULL,
  `time_window` int(11) DEFAULT NULL,
  `notification_email` tinyint(1) DEFAULT '1',
  `notification_slack` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `last_triggered` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `api_analytics`
--

CREATE TABLE `api_analytics` (
  `id` int(11) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `response_time` int(11) DEFAULT NULL,
  `status_code` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `request_size` int(11) DEFAULT NULL,
  `response_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT '0',
  `display_order` int(11) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `blog_categories`
--

INSERT INTO `blog_categories` (`id`, `name`, `slug`, `description`, `image`, `parent_id`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Web Development', 'web-development', 'Tips, tutorials, and insights about web development', NULL, 0, 0, 1, '2026-02-16 09:40:08', '2026-02-16 09:40:08'),
(2, 'PHP Programming', 'php-programming', 'Deep dives into PHP, best practices, and modern techniques', NULL, 0, 0, 1, '2026-02-16 09:40:08', '2026-02-16 09:40:08'),
(3, 'JavaScript', 'javascript', 'Frontend development, frameworks, and JavaScript tips', NULL, 0, 0, 1, '2026-02-16 09:40:08', '2026-02-16 09:40:08'),
(4, 'Career Advice', 'career-advice', 'Guidance for developers on career growth and freelancing', NULL, 0, 0, 1, '2026-02-16 09:40:08', '2026-02-16 09:40:08'),
(5, 'Tutorials', 'tutorials', 'Step-by-step guides to help you learn new skills', NULL, 0, 0, 1, '2026-02-16 09:40:08', '2026-02-16 09:40:08');

-- --------------------------------------------------------

--
-- Table structure for table `blog_comments`
--

CREATE TABLE `blog_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT '0',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `comment` text NOT NULL,
  `is_approved` tinyint(1) DEFAULT '0',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `blog_comments`
--

INSERT INTO `blog_comments` (`id`, `post_id`, `parent_id`, `name`, `email`, `website`, `comment`, `is_approved`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 0, 'Usnan Adamu', 'adamuusnan87@gmail.com', 'https://e-shop.com.ng', 'There is no need to 6⁶66666666', 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-09 13:18:14'),
(2, 1, 0, 'ABDULAZIZ ADAMU', 'info@mmkexpress.com', 'https://mmkexpress.com', 'Thanks for your reply and for ensuring product', 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-12 09:41:55');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text,
  `content` longtext NOT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `views` int(11) DEFAULT '0',
  `reading_time` int(11) DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `allow_comments` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `slug`, `excerpt`, `content`, `featured_image`, `category_id`, `author_id`, `views`, `reading_time`, `is_featured`, `status`, `published_at`, `meta_title`, `meta_description`, `meta_keywords`, `allow_comments`, `created_at`, `updated_at`) VALUES
(1, 'How create Home Page', 'how-create-home-page', '', 'The Technology is a sequence that allows the user and to provide a better solution to a magnetic properties that can engage the business and business development and ancillary of business and corporate services in a hygienic production company providing a href business to help in the lower t market and to the cooled of a degree in the 12th century by a sample powder gets a little more of an electrical problem in a hygienic production system stand on a href and the use for this project will not found it is also the case that you are going through a href of a degree in the 12th century bytes.', '69aeac1169eda_1773054993.png', 1, 1, 39, 1, 1, 'published', '2026-03-09 11:41:00', '', '', '', 1, '2026-03-09 10:41:35', '2026-03-12 10:38:41');

-- --------------------------------------------------------

--
-- Table structure for table `blog_post_tags`
--

CREATE TABLE `blog_post_tags` (
  `post_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `blog_post_tags`
--

INSERT INTO `blog_post_tags` (`post_id`, `tag_id`) VALUES
(1, 1),
(1, 13),
(1, 14);

-- --------------------------------------------------------

--
-- Table structure for table `blog_tags`
--

CREATE TABLE `blog_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `blog_tags`
--

INSERT INTO `blog_tags` (`id`, `name`, `slug`, `created_at`) VALUES
(1, 'PHP', 'php', '2026-02-16 09:40:08'),
(2, 'Laravel', 'laravel', '2026-02-16 09:40:08'),
(3, 'MySQL', 'mysql', '2026-02-16 09:40:08'),
(4, 'JavaScript', 'javascript', '2026-02-16 09:40:08'),
(5, 'React', 'react', '2026-02-16 09:40:08'),
(6, 'Vue.js', 'vuejs', '2026-02-16 09:40:08'),
(7, 'HTML5', 'html5', '2026-02-16 09:40:08'),
(8, 'CSS3', 'css3', '2026-02-16 09:40:08'),
(9, 'Responsive Design', 'responsive-design', '2026-02-16 09:40:08'),
(10, 'SEO', 'seo', '2026-02-16 09:40:08'),
(11, 'Performance', 'performance', '2026-02-16 09:40:08'),
(12, 'Security', 'security', '2026-02-16 09:40:08'),
(13, 'html', 'html', '2026-03-09 10:41:35'),
(14, 'python', 'python', '2026-03-09 10:41:35');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','lead') DEFAULT 'lead',
  `completed_projects` int(11) DEFAULT '0',
  `source` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `user_id`, `company_name`, `contact_person`, `email`, `phone`, `address`, `website`, `logo`, `status`, `completed_projects`, `source`, `notes`, `created_at`, `updated_at`) VALUES
(13, NULL, 'MMK EXPRESS', 'Muhammad Muktar', 'adamuusnan87@gmail.com', '09034095385', 'EL-WAZIR ESTATE BOSSO MINNA, NIGER STATE', 'https://e-shop.com.ng', '69b19b23e1311_1773247267.jpg', 'active', 0, 'Rarely', '', '2026-03-11 15:41:07', '2026-03-11 16:41:07'),
(9, NULL, 'Eshop', 'Amin Abdullah', 'kverifydigitalsolutions@gmail.com', '07034098648', 'Gbacinku via lapai', 'https://e-shop.com.ng', '69b12fb0e8b81_1773219760.jpg', 'active', 1, 'Mmkexpress.com 9', '', '2026-03-11 08:02:40', '2026-03-11 13:42:28'),
(10, NULL, 'Portland', 'Usnan Adamu', 'abdulvirus6@gmail.com', '070123456978', 'EL-WAZIR ESTATE BOSSO MINNA, NIGER STATE', 'https://e-shop.com.ng', '69b1611118eb4_1773232401.jpg', 'active', 0, '', '', '2026-03-11 11:33:21', '2026-03-11 12:33:21');

-- --------------------------------------------------------

--
-- Table structure for table `client_activity_log`
--

CREATE TABLE `client_activity_log` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-circle',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `client_documents`
--

CREATE TABLE `client_documents` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `filename` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `client_invoices`
--

CREATE TABLE `client_invoices` (
  `client_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `client_messages`
--

CREATE TABLE `client_messages` (
  `id` int(11) NOT NULL,
  `reply_to_id` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `sender` enum('client','admin') DEFAULT 'client',
  `status` enum('unread','read','replied') DEFAULT 'unread',
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `client_messages`
--

INSERT INTO `client_messages` (`id`, `reply_to_id`, `client_id`, `admin_id`, `subject`, `message`, `sender`, `status`, `parent_id`, `created_at`) VALUES
(1, NULL, 8, NULL, 'Inquiry About Digital Skills Training Opportunities', 'The Technology Incubation complex has a href system for a href system 😳 that', 'client', 'read', NULL, '2026-03-11 11:21:10'),
(2, NULL, 8, NULL, 'Training', 'The Technology Incubation complex has a number called bacteria that can be used as an arithmetic for a given metal complexes to a magnetic properties in a refrigerator and the use for a given metal is a and it is also a good idea is to have the Technology to do the work and the use for this is the study in the 12th century in.', 'client', 'read', NULL, '2026-03-11 12:02:53'),
(3, 2, 8, NULL, 'Re: Training', 'Hi', 'client', '', NULL, '2026-03-11 14:50:40'),
(4, 3, 8, NULL, 'Re: Re: Training', 'Okay', 'admin', 'unread', NULL, '2026-03-11 15:24:05'),
(5, 4, 8, NULL, 'Re: Re: Re: Training', 'I will like to know about it.', 'client', 'read', NULL, '2026-03-11 15:29:34'),
(6, NULL, 8, NULL, 'Newsletter', 'I am a passionate Web Developer with experience in building web applications using Python, Django, and PHP. I enjoy developing functional, user-friendly, and efficient digital solutions that solve real-world problems.\r\n\r\nMy experience includes backend development, database management, and building dynamic web applications. I am comfortable working with technologies such as Django, PHP, HTML, CSS, and MySQL to create reliable and scalable systems.\r\n\r\nI am currently focused on improving my skills in web development, APIs, and full-stack application development. I enjoy learning new technologies, solving problems with code, and continuously improving my technical skills.\r\n\r\nI am open to collaboration, internships, and opportunities that allow me to grow as a developer and contribute to impactful projects.', 'client', '', NULL, '2026-03-11 15:31:31'),
(7, 5, 8, NULL, 'Re: Re: Re: Re: Training', 'Okay', 'admin', 'unread', NULL, '2026-03-11 16:07:21'),
(8, NULL, 12, NULL, 'Test Subject', 'The Technology Incubation complex is a and it', 'client', 'read', NULL, '2026-03-11 17:21:45'),
(9, 8, 12, NULL, 'Re: Test Subject', 'The card is a sequence is the study', 'admin', 'unread', NULL, '2026-03-11 17:22:30'),
(10, NULL, 12, NULL, 'Test Subject', 'The Technology Incubation complex is a and it', 'client', '', NULL, '2026-03-11 17:22:39');

-- --------------------------------------------------------

--
-- Table structure for table `client_notifications`
--

CREATE TABLE `client_notifications` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-info-circle',
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `client_projects`
--

CREATE TABLE `client_projects` (
  `client_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `client_remember_tokens`
--

CREATE TABLE `client_remember_tokens` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `selector` varchar(20) NOT NULL,
  `hashed_validator` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `client_remember_tokens`
--

INSERT INTO `client_remember_tokens` (`id`, `client_id`, `selector`, `hashed_validator`, `expires_at`) VALUES
(3, 8, '0e68a234534eba7f70', '$2y$10$Xi2E6ljR0uq6rvbFT1IlwecOxqeiYFr8tNm51lcNHrN0eQnOMPx1y', '2026-04-10 14:15:09'),
(4, 12, '5242713705cfdf7e32', '$2y$10$Toanv3WrI.RPIjNcJ1f8KuOJqtKIEId/NMxygghNoCxmX8ZldGKeK', '2026-04-10 16:44:03');

-- --------------------------------------------------------

--
-- Table structure for table `client_sessions`
--

CREATE TABLE `client_sessions` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `last_activity` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `client_sessions`
--

INSERT INTO `client_sessions` (`id`, `client_id`, `session_id`, `ip_address`, `user_agent`, `last_activity`, `created_at`) VALUES
(1, 8, 'a5709d5a8e3acee7c1c27e067b618c0c', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11 12:10:46', '2026-03-11 12:10:46'),
(2, 8, '0f75d914f4898d1d4eb85eb1325d51a7', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11 13:55:42', '2026-03-11 12:42:38'),
(3, 8, '1f5092a745e03590330ac02237c7c72c', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11 14:08:28', '2026-03-11 14:05:18'),
(4, 8, 'd92d10c53e57df4cb9e125a4f9892718', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11 15:30:44', '2026-03-11 14:15:10'),
(5, 12, '68ed51a949a17942327c405b4b9793d7', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11 16:44:21', '2026-03-11 16:44:21'),
(6, 8, '5ecf653e06a714b53602c5791c4c9ae0', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11 17:58:23', '2026-03-11 17:58:23');

-- --------------------------------------------------------

--
-- Table structure for table `client_users`
--

CREATE TABLE `client_users` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `email_notifications` tinyint(1) DEFAULT '1',
  `invoice_notifications` tinyint(1) DEFAULT '1',
  `project_updates` tinyint(1) DEFAULT '1',
  `settings` json DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT '0',
  `role` enum('primary','secondary','viewer') DEFAULT 'secondary',
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `client_users`
--

INSERT INTO `client_users` (`id`, `client_id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `company`, `avatar`, `email_notifications`, `invoice_notifications`, `project_updates`, `settings`, `two_factor_secret`, `two_factor_enabled`, `role`, `last_login`, `is_active`, `created_at`, `updated_at`) VALUES
(8, 9, 'kverifydigitalsolutions@gmail.com', '$2y$10$WXQtTsf.rj.VcYW5tXdGk.uf6jZ76CV6aJarx8jh3TmgYbGbVvoWy', 'Amin', 'Abdullah', NULL, NULL, NULL, 1, 1, 1, NULL, NULL, 0, 'primary', '2026-03-11 14:15:09', 1, '2026-03-11 08:02:41', '2026-03-11 14:15:09'),
(12, 13, 'adamuusnan87@gmail.com', '$2y$10$vTNPNTAFyDwFHiO.SqcMweA6Ismk3SdYaUCBrNd1W7Qdd7QOZbELG', 'Muhammad', 'Muktar', NULL, NULL, NULL, 1, 1, 1, NULL, NULL, 0, 'primary', NULL, 1, '2026-03-11 15:41:08', '2026-03-11 16:41:08'),
(9, 10, 'abdulvirus6@gmail.com', '$2y$10$fANuhLbeC8aRh9MJYShG8e9MHU.yGFSIVg7e.5ICIziJkf4X2qFDm', 'Usnan', 'Adamu', NULL, NULL, NULL, 1, 1, 1, NULL, NULL, 0, 'primary', NULL, 1, '2026-03-11 11:33:21', '2026-03-11 12:33:21');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(200) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `budget_range` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `is_replied` tinyint(1) DEFAULT '0',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `company`, `subject`, `message`, `budget_range`, `is_read`, `is_replied`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'ABDULAZIZ ADAMU', 'adamuusnan87@gmail.com', NULL, NULL, '', 'The card was sent to the measurement or the one I class had already', NULL, 1, 0, '127.0.0.1', NULL, '2026-02-17 21:52:06'),
(2, 'Usnan Adamu', 'adamuusnan87@gmail.com', NULL, NULL, 'Inquiry About Digital Skills Training Opportunities', 'Got it! I’ve updated your admin/clients.php to fully integrate sending welcome emails using the email template, while keeping all your existing client management features intact (create/update/delete/toggle status/logo upload). I also added error logging and fallback handling if email fails.', NULL, 1, 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11 08:17:40'),
(3, 'Abdullah Ibrahim', 'info@mmkexpress.com', NULL, NULL, 'Business Inquiry', 'Good afternoon. I would like to inquire about your services and pricing. Please let me know the available options. Thank you.', NULL, 1, 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-11 08:23:29'),
(4, 'Qasim Abdullaj', 'kuyabetech@gmail.com', NULL, NULL, 'Newsletter', 'The Technology is a critical preliminary process and the use of these tools is the optimum', NULL, 0, 0, '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 16:46:53');

-- --------------------------------------------------------

--
-- Table structure for table `custom_dimensions`
--

CREATE TABLE `custom_dimensions` (
  `id` int(11) NOT NULL,
  `dimension_name` varchar(100) NOT NULL,
  `dimension_value` varchar(255) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `visitor_id` varchar(100) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `dashboard_alerts`
--

CREATE TABLE `dashboard_alerts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `alert_type` enum('info','success','warning','danger') DEFAULT 'info',
  `icon` varchar(50) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_dismissible` tinyint(1) DEFAULT '1',
  `is_global` tinyint(1) DEFAULT '0',
  `user_id` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `dashboard_alerts`
--

INSERT INTO `dashboard_alerts` (`id`, `title`, `message`, `alert_type`, `icon`, `link`, `is_dismissible`, `is_global`, `user_id`, `expires_at`, `created_at`) VALUES
(1, 'Welcome to the new Dashboard!', 'You can now customize your dashboard by dragging widgets and saving layouts.', 'info', 'fas fa-info-circle', NULL, 1, 1, NULL, NULL, '2026-02-16 12:13:15'),
(2, 'System Update Available', 'A new version of the CMS is available. Please backup before updating.', 'warning', 'fas fa-exclamation-triangle', NULL, 1, 1, NULL, NULL, '2026-02-16 12:13:15');

-- --------------------------------------------------------

--
-- Table structure for table `dashboard_widgets`
--

CREATE TABLE `dashboard_widgets` (
  `id` int(11) NOT NULL,
  `widget_key` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT NULL,
  `widget_type` enum('chart','stats','list','table','custom') DEFAULT 'stats',
  `widget_size` enum('small','medium','large','full') DEFAULT 'medium',
  `refresh_interval` int(11) DEFAULT '0',
  `data_source` varchar(255) DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `dashboard_widgets`
--

INSERT INTO `dashboard_widgets` (`id`, `widget_key`, `title`, `description`, `icon`, `widget_type`, `widget_size`, `refresh_interval`, `data_source`, `settings`, `is_system`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'welcome_widget', 'Welcome to Dashboard', 'Quick overview and getting started guide', 'fas fa-hand-wave', 'custom', 'full', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(2, 'stats_overview', 'Quick Stats', 'Overview of your key metrics', 'fas fa-chart-pie', 'stats', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(3, 'recent_projects', 'Recent Projects', 'Latest projects and their status', 'fas fa-code-branch', 'list', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(4, 'recent_messages', 'Recent Messages', 'Latest contact form submissions', 'fas fa-envelope', 'list', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(5, 'project_progress', 'Project Progress', 'Project completion status', 'fas fa-tasks', 'chart', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(6, 'revenue_chart', 'Revenue Overview', 'Monthly revenue chart', 'fas fa-chart-line', 'chart', 'large', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(7, 'task_summary', 'Task Summary', 'Overview of pending tasks', 'fas fa-check-circle', 'stats', 'small', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(8, 'client_growth', 'Client Growth', 'New clients this month', 'fas fa-user-plus', 'stats', 'small', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(9, 'popular_pages', 'Popular Pages', 'Most visited pages', 'fas fa-star', 'list', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(10, 'system_health', 'System Health', 'Server and application status', 'fas fa-heartbeat', 'custom', 'small', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(11, 'quick_actions', 'Quick Actions', 'Common admin tasks', 'fas fa-bolt', 'custom', 'small', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(12, 'recent_activity', 'Recent Activity', 'Latest system activities', 'fas fa-history', 'list', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(13, 'upcoming_tasks', 'Upcoming Tasks', 'Tasks due soon', 'fas fa-clock', 'list', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(14, 'browser_stats', 'Browser Statistics', 'Visitor browser breakdown', 'fas fa-globe', 'chart', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15'),
(15, 'device_stats', 'Device Statistics', 'Visitor device breakdown', 'fas fa-mobile-alt', 'chart', 'medium', 0, NULL, NULL, 1, 1, '2026-02-16 12:13:15', '2026-02-16 12:13:15');

-- --------------------------------------------------------

--
-- Table structure for table `data_retention_policies`
--

CREATE TABLE `data_retention_policies` (
  `id` int(11) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `retention_days` int(11) NOT NULL,
  `enabled` tinyint(1) DEFAULT '1',
  `last_cleanup` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `data_retention_policies`
--

INSERT INTO `data_retention_policies` (`id`, `table_name`, `retention_days`, `enabled`, `last_cleanup`, `created_at`, `updated_at`) VALUES
(1, 'realtime_analytics', 1, 1, NULL, '2026-02-16 13:15:24', '2026-02-16 13:15:24'),
(2, 'user_events', 90, 1, NULL, '2026-02-16 13:15:24', '2026-02-16 13:15:24'),
(3, 'page_views', 365, 1, NULL, '2026-02-16 13:15:24', '2026-02-16 13:15:24'),
(4, 'performance_metrics', 30, 1, NULL, '2026-02-16 13:15:24', '2026-02-16 13:15:24'),
(5, 'api_analytics', 90, 1, NULL, '2026-02-16 13:15:24', '2026-02-16 13:15:24');

-- --------------------------------------------------------

--
-- Table structure for table `email_analytics`
--

CREATE TABLE `email_analytics` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `event_type` enum('sent','delivered','opened','clicked','bounced','unsubscribed') NOT NULL,
  `link_clicked` varchar(500) DEFAULT NULL,
  `user_agent` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `email_queue_id` int(11) DEFAULT NULL,
  `template_key` varchar(100) DEFAULT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `error_message` text,
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `template_key` varchar(100) DEFAULT NULL,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `attachments` text,
  `priority` enum('high','normal','low') DEFAULT 'normal',
  `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `attempts` int(11) DEFAULT '0',
  `max_attempts` int(11) DEFAULT '3',
  `error_message` text,
  `sent_at` datetime DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `template_key` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `variables` text,
  `category` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `template_key`, `name`, `subject`, `body`, `variables`, `category`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'welcome_client', 'Welcome Email for Clients', 'Welcome to {{site_name}} Client Portal', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n        .credentials { background: #e8f4fd; padding: 15px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .credentials code { background: #d4e6f1; padding: 3px 6px; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Welcome to {{site_name}}!</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hello {{client_name}},</p>\r\n\r\n            <p>Welcome to {{site_name}}! We\'ve created a client portal account for you to track your projects, communicate with us, and manage invoices.</p>\r\n\r\n            \r\n\r\n            <div class=\"credentials\">\r\n\r\n                <h3>Your Login Credentials:</h3>\r\n\r\n                <p><strong>Portal URL:</strong> <a href=\"{{portal_url}}\">{{portal_url}}</a></p>\r\n\r\n                <p><strong>Email:</strong> {{email}}</p>\r\n\r\n                <p><strong>Password:</strong> <code>{{password}}</code></p>\r\n\r\n                <p><small>Please change your password after first login for security.</small></p>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p>You can now:</p>\r\n\r\n            <ul>\r\n\r\n                <li>View your projects and their progress</li>\r\n\r\n                <li>Access project files and documents</li>\r\n\r\n                <li>Communicate with our team</li>\r\n\r\n                <li>View and pay invoices online</li>\r\n\r\n            </ul>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{portal_url}}\" class=\"button\">Access Your Portal</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, 'client', 1, '2026-02-16 12:12:47', '2026-02-16 12:12:47'),
(2, 'invoice_created', 'New Invoice Created', 'Invoice #{{invoice_number}} from {{site_name}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .invoice-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .invoice-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }\r\n\r\n        .total-row { font-weight: bold; font-size: 1.2rem; color: {{primary_color}}; border-bottom: none; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>New Invoice</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hello {{client_name}},</p>\r\n\r\n            <p>A new invoice has been created for your project \"{{project_title}}\".</p>\r\n\r\n            \r\n\r\n            <div class=\"invoice-details\">\r\n\r\n                <h3>Invoice #{{invoice_number}}</h3>\r\n\r\n                <div class=\"invoice-row\">\r\n\r\n                    <span>Invoice Date:</span>\r\n\r\n                    <span>{{invoice_date}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"invoice-row\">\r\n\r\n                    <span>Due Date:</span>\r\n\r\n                    <span>{{due_date}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"invoice-row\">\r\n\r\n                    <span>Amount:</span>\r\n\r\n                    <span>${{amount}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"invoice-row total-row\">\r\n\r\n                    <span>Total Due:</span>\r\n\r\n                    <span>${{balance_due}}</span>\r\n\r\n                </div>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{invoice_url}}\" class=\"button\">View & Pay Invoice</a>\r\n\r\n            </p>\r\n\r\n            \r\n\r\n            <p>If you have any questions about this invoice, please don\'t hesitate to contact us.</p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, 'invoice', 1, '2026-02-16 12:12:47', '2026-02-16 12:12:47'),
(3, 'payment_confirmation', 'Payment Confirmation', 'Payment Received for Invoice #{{invoice_number}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: #10b981; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .payment-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .payment-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }\r\n\r\n        .success-icon { text-align: center; font-size: 48px; color: #10b981; margin-bottom: 20px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Payment Received!</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <div class=\"success-icon\">✓</div>\r\n\r\n            \r\n\r\n            <p>Hello {{client_name}},</p>\r\n\r\n            <p>We\'ve received your payment for Invoice #{{invoice_number}}. Thank you for your prompt payment!</p>\r\n\r\n            \r\n\r\n            <div class=\"payment-details\">\r\n\r\n                <h3>Payment Details</h3>\r\n\r\n                <div class=\"payment-row\">\r\n\r\n                    <span>Invoice Number:</span>\r\n\r\n                    <span>#{{invoice_number}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"payment-row\">\r\n\r\n                    <span>Payment Amount:</span>\r\n\r\n                    <span>${{amount}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"payment-row\">\r\n\r\n                    <span>Payment Date:</span>\r\n\r\n                    <span>{{payment_date}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"payment-row\">\r\n\r\n                    <span>Payment Method:</span>\r\n\r\n                    <span>{{payment_method}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"payment-row\">\r\n\r\n                    <span>Transaction ID:</span>\r\n\r\n                    <span>{{transaction_id}}</span>\r\n\r\n                </div>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p>You can view your complete payment history and download receipts from your client portal.</p>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{portal_url}}\" class=\"button\">Go to Portal</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, 'payment', 1, '2026-02-16 12:12:47', '2026-02-16 12:12:47'),
(4, 'payment_reminder', 'Payment Reminder', 'Payment Reminder: Invoice #{{invoice_number}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: #f59e0b; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .invoice-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .invoice-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }\r\n\r\n        .urgent { color: #f59e0b; font-weight: bold; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Payment Reminder</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hello {{client_name}},</p>\r\n\r\n            <p>This is a friendly reminder that invoice #{{invoice_number}} is due for payment.</p>\r\n\r\n            \r\n\r\n            <div class=\"invoice-details\">\r\n\r\n                <h3>Invoice Summary</h3>\r\n\r\n                <div class=\"invoice-row\">\r\n\r\n                    <span>Invoice Number:</span>\r\n\r\n                    <span>#{{invoice_number}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"invoice-row\">\r\n\r\n                    <span>Due Date:</span>\r\n\r\n                    <span class=\"urgent\">{{due_date}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"invoice-row\">\r\n\r\n                    <span>Amount Due:</span>\r\n\r\n                    <span>${{balance_due}}</span>\r\n\r\n                </div>\r\n\r\n                <div class=\"invoice-row\">\r\n\r\n                    <span>Days Overdue:</span>\r\n\r\n                    <span class=\"urgent\">{{days_overdue}} days</span>\r\n\r\n                </div>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p>To avoid any interruption in services, please arrange payment at your earliest convenience.</p>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{invoice_url}}\" class=\"button\">Pay Now</a>\r\n\r\n            </p>\r\n\r\n            \r\n\r\n            <p>If you\'ve already made the payment, please disregard this message. Thank you for your business!</p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, 'reminder', 1, '2026-02-16 12:12:47', '2026-02-16 12:12:47'),
(5, 'project_update', 'Project Status Update', 'Update on Project: {{project_name}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .update-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n        .status-badge { display: inline-block; padding: 4px 12px; background: #e8f4fd; color: {{primary_color}}; border-radius: 20px; font-size: 0.9rem; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Project Update</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hello {{client_name}},</p>\r\n\r\n            <p>We have an update on your project \"{{project_name}}\".</p>\r\n\r\n            \r\n\r\n            <div class=\"update-box\">\r\n\r\n                <h3>Current Status: <span class=\"status-badge\">{{project_status}}</span></h3>\r\n\r\n                <p>{{update_message}}</p>\r\n\r\n                \r\n\r\n                {{progress_html}}\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p>You can view the complete project details and latest updates in your client portal.</p>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{project_url}}\" class=\"button\">View Project</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, 'project', 1, '2026-02-16 12:12:47', '2026-02-16 12:12:47'),
(6, 'task_assigned', 'New Task Assigned', 'New Task: {{task_name}}', '\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .task-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .priority-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.9rem; }\r\n\r\n        .priority-high { background: #fee2e2; color: #ef4444; }\r\n\r\n        .priority-medium { background: #fef3c7; color: #f59e0b; }\r\n\r\n        .priority-low { background: #e0f2fe; color: #3b82f6; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>New Task Assigned</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hello {{assignee_name}},</p>\r\n\r\n            <p>A new task has been assigned to you.</p>\r\n\r\n            \r\n\r\n            <div class=\"task-box\">\r\n\r\n                <h3>{{task_name}}</h3>\r\n\r\n                <p><strong>Project:</strong> {{project_name}}</p>\r\n\r\n                <p><strong>Priority:</strong> <span class=\"priority-badge priority-{{priority}}\">{{priority}}</span></p>\r\n\r\n                <p><strong>Due Date:</strong> {{due_date}}</p>\r\n\r\n                <p><strong>Description:</strong></p>\r\n\r\n                <p>{{task_description}}</p>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{task_url}}\" class=\"button\">View Task</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>© {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', '{{client_name}}, {{project_name}}, {{invoice_number}}, {{amount}}, {{due_date}}, {{site_name}}, {{site_url}}, {{year}}', 'task', 1, '2026-02-16 12:12:47', '2026-03-09 10:54:49'),
(7, 'message_notification', 'New Message', 'New Message Regarding {{project_name}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .message-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .message-meta { color: #666; font-size: 0.9rem; margin-bottom: 10px; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>New Message</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hello {{recipient_name}},</p>\r\n\r\n            <p>You have received a new message regarding project \"{{project_name}}\".</p>\r\n\r\n            \r\n\r\n            <div class=\"message-box\">\r\n\r\n                <div class=\"message-meta\">\r\n\r\n                    <strong>From:</strong> {{sender_name}}<br>\r\n\r\n                    <strong>Date:</strong> {{message_date}}\r\n\r\n                </div>\r\n\r\n                <p>{{message_preview}}</p>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{conversation_url}}\" class=\"button\">View Message</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, 'communication', 1, '2026-02-16 12:12:47', '2026-02-16 12:12:47'),
(8, 'file_uploaded', 'New File Uploaded', 'New File: {{file_name}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .file-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .file-icon { font-size: 48px; text-align: center; color: {{primary_color}}; margin-bottom: 10px; }\r\n\r\n        .file-details { background: #f5f5f5; padding: 15px; border-radius: 8px; margin-top: 15px; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>New File Uploaded</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hello {{recipient_name}},</p>\r\n\r\n            <p>A new file has been uploaded to project \"{{project_name}}\".</p>\r\n\r\n            \r\n\r\n            <div class=\"file-box\">\r\n\r\n                <div class=\"file-icon\">?</div>\r\n\r\n                <h3>{{file_name}}</h3>\r\n\r\n                <p>{{file_description}}</p>\r\n\r\n                \r\n\r\n                <div class=\"file-details\">\r\n\r\n                    <p><strong>Uploaded by:</strong> {{uploaded_by}}</p>\r\n\r\n                    <p><strong>File size:</strong> {{file_size}}</p>\r\n\r\n                    <p><strong>Category:</strong> {{file_category}}</p>\r\n\r\n                </div>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{file_url}}\" class=\"button\">Download File</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, 'file', 1, '2026-02-16 12:12:47', '2026-02-16 12:12:47'),
(9, 'milestone_completed', 'Project Milestone Completed', 'Milestone Completed: {{milestone_name}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: #10b981; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .milestone-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }\r\n\r\n        .progress-bar { height: 20px; background: #e0e0e0; border-radius: 10px; margin: 15px 0; overflow: hidden; }\r\n\r\n        .progress-fill { height: 100%; background: {{primary_color}}; width: {{progress_percent}}%; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Milestone Achieved! ?</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hello {{client_name}},</p>\r\n\r\n            <p>Great news! We\'ve completed a milestone on your project \"{{project_name}}\".</p>\r\n\r\n            \r\n\r\n            <div class=\"milestone-box\">\r\n\r\n                <h3>{{milestone_name}}</h3>\r\n\r\n                <p>{{milestone_description}}</p>\r\n\r\n                \r\n\r\n                <div class=\"progress-bar\">\r\n\r\n                    <div class=\"progress-fill\"></div>\r\n\r\n                </div>\r\n\r\n                <p><strong>Overall Project Progress: {{progress_percent}}%</strong></p>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p>You can view the completed work and next steps in your client portal.</p>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{project_url}}\" class=\"button\">View Progress</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, 'project', 1, '2026-02-16 12:12:47', '2026-02-16 12:12:47'),
(10, 'test_email', 'Test Email', 'Test Email from {{site_name}}', '\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .success { background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 8px; margin: 20px 0; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Test Email</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <div class=\"success\">\r\n\r\n                <strong>✅ Email Configuration Successful!</strong>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p>This is a test email from {{site_name}}. If you\'re reading this, your email system is working correctly.</p>\r\n\r\n            \r\n\r\n            <p><strong>Test Details:</strong></p>\r\n\r\n            <ul>\r\n\r\n                <li>Sent at: {{sent_time}}</li>\r\n\r\n                <li>Server: {{server_name}}</li>\r\n\r\n                <li>PHP Version: {{php_version}}</li>\r\n\r\n            </ul>\r\n\r\n            \r\n\r\n            <p>You can now use the email notification system for:</p>\r\n\r\n            <ul>\r\n\r\n                <li>Welcome emails to new clients</li>\r\n\r\n                <li>Invoice notifications</li>\r\n\r\n                <li>Payment confirmations</li>\r\n\r\n                <li>Payment reminders</li>\r\n\r\n                <li>Project updates</li>\r\n\r\n                <li>Task assignments</li>\r\n\r\n                <li>File upload notifications</li>\r\n\r\n            </ul>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>© {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', '{{client_name}}, {{project_name}}, {{invoice_number}}, {{amount}}, {{due_date}}, {{site_name}}, {{site_url}}, {{year}}', 'test', 1, '2026-02-16 12:12:47', '2026-02-16 20:17:57');

-- --------------------------------------------------------

--
-- Table structure for table `funnel_analytics`
--

CREATE TABLE `funnel_analytics` (
  `id` int(11) NOT NULL,
  `funnel_name` varchar(100) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `visitor_id` varchar(100) DEFAULT NULL,
  `current_step` int(11) DEFAULT '0',
  `completed` tinyint(1) DEFAULT '0',
  `entered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `abandoned_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `funnel_steps`
--

CREATE TABLE `funnel_steps` (
  `id` int(11) NOT NULL,
  `funnel_name` varchar(100) NOT NULL,
  `step_number` int(11) NOT NULL,
  `step_name` varchar(100) NOT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `event_trigger` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `geoip_cache`
--

CREATE TABLE `geoip_cache` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `isp` varchar(255) DEFAULT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `as_number` varchar(50) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `goal_conversions`
--

CREATE TABLE `goal_conversions` (
  `id` int(11) NOT NULL,
  `goal_name` varchar(100) NOT NULL,
  `goal_type` enum('page_view','event','duration','custom') NOT NULL,
  `goal_value` decimal(10,2) DEFAULT '0.00',
  `session_id` varchar(100) DEFAULT NULL,
  `visitor_id` varchar(100) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `converted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `item_type` enum('service','product','hourly','expense') DEFAULT 'service',
  `description` text NOT NULL,
  `quantity` decimal(10,2) DEFAULT '1.00',
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `tax_rate` decimal(5,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_type`, `description`, `quantity`, `unit_price`, `discount`, `tax_rate`, `tax_amount`, `total`, `sort_order`, `created_at`) VALUES
(1, 1, 'service', 'Project', 1.00, 1000.00, 2.00, 1.00, 9.98, 1007.98, 0, '2026-03-10 10:44:14'),
(2, 2, 'service', 'Projects ', 1.00, 1000.00, 0.00, 0.00, 0.00, 1000.00, 0, '2026-03-10 11:01:53'),
(3, 3, 'service', 'Project', 1.00, 1000.00, 0.00, 0.00, 0.00, 1000.00, 0, '2026-03-10 11:08:14'),
(4, 4, 'service', 'There are a couple things ', 1.00, 100.00, 0.00, 0.00, 0.00, 100.00, 0, '2026-03-11 13:45:10');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_payments`
--

CREATE TABLE `invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','check','bank_transfer','credit_card','paypal','stripe','other') NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'completed',
  `notes` text,
  `receipt_sent` tinyint(1) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-16 09:05:40'),
(2, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-16 10:05:59'),
(3, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-16 10:16:03'),
(4, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-16 10:33:19'),
(5, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-16 12:03:36'),
(6, 1, '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-02-16 13:03:44'),
(7, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-16 20:07:00'),
(8, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-16 21:07:14'),
(9, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-16 22:07:53'),
(10, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-17 05:57:16'),
(11, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-17 17:03:25'),
(12, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-17 17:28:23'),
(13, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-17 18:28:29'),
(14, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-17 19:12:42'),
(15, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-17 20:03:18'),
(16, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-17 21:03:36'),
(17, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-17 22:04:24'),
(18, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-18 15:41:20'),
(19, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-18 19:53:15'),
(20, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-19 04:32:05'),
(21, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', '2026-02-19 17:38:07'),
(22, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-09 10:28:45'),
(23, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-09 11:54:39'),
(24, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-09 12:20:34'),
(25, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-09 13:22:53'),
(26, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-09 14:44:28'),
(27, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-09 15:49:54'),
(28, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-10 09:49:47'),
(29, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-10 11:00:32'),
(30, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-10 11:37:35'),
(31, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-10 12:43:12'),
(32, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-10 12:50:52'),
(33, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-10 13:55:18'),
(34, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-11 03:56:41'),
(35, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-11 08:29:20'),
(36, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-11 09:40:48'),
(37, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-11 10:46:29'),
(38, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-11 12:30:21'),
(39, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-11 17:02:46'),
(40, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-12 09:28:23'),
(41, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-12 10:34:26'),
(42, 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-12 11:17:21');

-- --------------------------------------------------------

--
-- Table structure for table `message_replies`
--

CREATE TABLE `message_replies` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `reply_message` text NOT NULL,
  `sent_at` datetime NOT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `email_sent` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `message_replies`
--

INSERT INTO `message_replies` (`id`, `message_id`, `reply_message`, `sent_at`, `sent_by`, `email_sent`) VALUES
(1, 1, 'The card was sent out to', '2026-03-11 15:48:28', 1, 0),
(2, 3, 'Okay', '2026-03-11 16:04:13', 1, 0),
(3, 1, 'Okay 👍', '2026-03-11 16:23:10', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_campaigns`
--

CREATE TABLE `newsletter_campaigns` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `status` enum('draft','scheduled','sending','sent','cancelled') DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `recipient_count` int(11) DEFAULT '0',
  `opens` int(11) DEFAULT '0',
  `clicks` int(11) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_stats`
--

CREATE TABLE `newsletter_stats` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `subscriber_id` int(11) NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  `clicked_links` text,
  `unsubscribed_at` datetime DEFAULT NULL,
  `bounced` tinyint(1) DEFAULT '0',
  `bounce_reason` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `status` enum('active','unsubscribed','bounced') DEFAULT 'active',
  `subscribed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `source` varchar(100) DEFAULT 'website',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_templates`
--

CREATE TABLE `newsletter_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `newsletter_templates`
--

INSERT INTO `newsletter_templates` (`id`, `name`, `subject`, `content`, `thumbnail`, `category`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 'Welcome Email', 'Welcome to {{site_name}}!', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Welcome to {{site_name}}!</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hi {{first_name}},</p>\r\n\r\n            <p>Thank you for subscribing to our newsletter. We\'re excited to have you on board!</p>\r\n\r\n            <p>You\'ll receive updates about:</p>\r\n\r\n            <ul>\r\n\r\n                <li>New blog posts and tutorials</li>\r\n\r\n                <li>Latest projects and case studies</li>\r\n\r\n                <li>Industry insights and tips</li>\r\n\r\n                <li>Special offers and announcements</li>\r\n\r\n            </ul>\r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{site_url}}\" class=\"button\">Visit Our Website</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n            <p><a href=\"{{unsubscribe_url}}\">Unsubscribe</a> | <a href=\"{{update_preferences_url}}\">Update Preferences</a></p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, NULL, 1, '2026-02-16 12:13:37', '2026-02-16 12:13:37'),
(2, 'Monthly Newsletter', '{{site_name}} - Monthly Update', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .post { margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px; }\r\n\r\n        .post-title { font-size: 18px; margin-bottom: 10px; }\r\n\r\n        .post-meta { color: #999; font-size: 12px; margin-bottom: 10px; }\r\n\r\n        .button { display: inline-block; padding: 10px 20px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Monthly Update</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hi {{first_name}},</p>\r\n\r\n            <p>Here\'s what we\'ve been up to this month:</p>\r\n\r\n            \r\n\r\n            <div class=\"post\">\r\n\r\n                <h2 class=\"post-title\">Latest Blog Post</h2>\r\n\r\n                <div class=\"post-meta\">Published on {{date}}</div>\r\n\r\n                <p>{{blog_excerpt}}</p>\r\n\r\n                <a href=\"{{blog_url}}\" class=\"button\">Read More</a>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <div class=\"post\">\r\n\r\n                <h2 class=\"post-title\">New Project: {{project_name}}</h2>\r\n\r\n                <p>{{project_description}}</p>\r\n\r\n                <a href=\"{{project_url}}\" class=\"button\">View Project</a>\r\n\r\n            </div>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n            <p><a href=\"{{unsubscribe_url}}\">Unsubscribe</a></p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, NULL, 0, '2026-02-16 12:13:37', '2026-02-16 12:13:37'),
(3, 'Project Showcase', 'New Project: {{project_name}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .project-image { width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; }\r\n\r\n        .project-details { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }\r\n\r\n        .tech-tag { display: inline-block; padding: 5px 10px; background: #f0f0f0; border-radius: 4px; margin-right: 5px; font-size: 12px; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>New Project: {{project_name}}</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hi {{first_name}},</p>\r\n\r\n            <p>I\'m excited to share my latest project with you!</p>\r\n\r\n            \r\n\r\n            {{project_image}}\r\n\r\n            \r\n\r\n            <div class=\"project-details\">\r\n\r\n                <h2>{{project_name}}</h2>\r\n\r\n                <p>{{project_description}}</p>\r\n\r\n                <p><strong>Technologies used:</strong></p>\r\n\r\n                <div>\r\n\r\n                    {{project_technologies}}\r\n\r\n                </div>\r\n\r\n                <p><strong>Client:</strong> {{client_name}}</p>\r\n\r\n                <p><strong>Completed:</strong> {{completion_date}}</p>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{project_url}}\" class=\"button\">View Live Project</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n            <p><a href=\"{{unsubscribe_url}}\">Unsubscribe</a></p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, NULL, 0, '2026-02-16 12:13:37', '2026-02-16 12:13:37'),
(4, 'Blog Post Alert', 'New Blog Post: {{post_title}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .post-image { width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; }\r\n\r\n        .post-content { background: white; padding: 30px; border-radius: 8px; }\r\n\r\n        .read-more { display: inline-block; margin-top: 20px; color: {{primary_color}}; text-decoration: none; font-weight: bold; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>New Blog Post</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hi {{first_name}},</p>\r\n\r\n            <p>I\'ve just published a new blog post that I think you\'ll find interesting:</p>\r\n\r\n            \r\n\r\n            {{post_image}}\r\n\r\n            \r\n\r\n            <div class=\"post-content\">\r\n\r\n                <h2>{{post_title}}</h2>\r\n\r\n                <p>{{post_excerpt}}</p>\r\n\r\n                <p><strong>Reading time:</strong> {{reading_time}} min</p>\r\n\r\n                <a href=\"{{post_url}}\" class=\"button\">Read Full Article</a>\r\n\r\n            </div>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n            <p><a href=\"{{unsubscribe_url}}\">Unsubscribe</a></p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, NULL, 0, '2026-02-16 12:13:37', '2026-02-16 12:13:37'),
(5, 'Welcome Email', 'Welcome to {{site_name}}!', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Welcome to {{site_name}}!</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hi {{first_name}},</p>\r\n\r\n            <p>Thank you for subscribing to our newsletter. We\'re excited to have you on board!</p>\r\n\r\n            <p>You\'ll receive updates about:</p>\r\n\r\n            <ul>\r\n\r\n                <li>New blog posts and tutorials</li>\r\n\r\n                <li>Latest projects and case studies</li>\r\n\r\n                <li>Industry insights and tips</li>\r\n\r\n                <li>Special offers and announcements</li>\r\n\r\n            </ul>\r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{site_url}}\" class=\"button\">Visit Our Website</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n            <p><a href=\"{{unsubscribe_url}}\">Unsubscribe</a> | <a href=\"{{update_preferences_url}}\">Update Preferences</a></p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, NULL, 1, '2026-02-16 12:45:09', '2026-02-16 12:45:09'),
(6, 'Monthly Newsletter', '{{site_name}} - Monthly Update', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .post { margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px; }\r\n\r\n        .post-title { font-size: 18px; margin-bottom: 10px; }\r\n\r\n        .post-meta { color: #999; font-size: 12px; margin-bottom: 10px; }\r\n\r\n        .button { display: inline-block; padding: 10px 20px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>Monthly Update</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hi {{first_name}},</p>\r\n\r\n            <p>Here\'s what we\'ve been up to this month:</p>\r\n\r\n            \r\n\r\n            <div class=\"post\">\r\n\r\n                <h2 class=\"post-title\">Latest Blog Post</h2>\r\n\r\n                <div class=\"post-meta\">Published on {{date}}</div>\r\n\r\n                <p>{{blog_excerpt}}</p>\r\n\r\n                <a href=\"{{blog_url}}\" class=\"button\">Read More</a>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <div class=\"post\">\r\n\r\n                <h2 class=\"post-title\">New Project: {{project_name}}</h2>\r\n\r\n                <p>{{project_description}}</p>\r\n\r\n                <a href=\"{{project_url}}\" class=\"button\">View Project</a>\r\n\r\n            </div>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n            <p><a href=\"{{unsubscribe_url}}\">Unsubscribe</a></p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, NULL, 0, '2026-02-16 12:45:09', '2026-02-16 12:45:09'),
(7, 'Project Showcase', 'New Project: {{project_name}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .project-image { width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; }\r\n\r\n        .project-details { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }\r\n\r\n        .tech-tag { display: inline-block; padding: 5px 10px; background: #f0f0f0; border-radius: 4px; margin-right: 5px; font-size: 12px; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>New Project: {{project_name}}</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hi {{first_name}},</p>\r\n\r\n            <p>I\'m excited to share my latest project with you!</p>\r\n\r\n            \r\n\r\n            {{project_image}}\r\n\r\n            \r\n\r\n            <div class=\"project-details\">\r\n\r\n                <h2>{{project_name}}</h2>\r\n\r\n                <p>{{project_description}}</p>\r\n\r\n                <p><strong>Technologies used:</strong></p>\r\n\r\n                <div>\r\n\r\n                    {{project_technologies}}\r\n\r\n                </div>\r\n\r\n                <p><strong>Client:</strong> {{client_name}}</p>\r\n\r\n                <p><strong>Completed:</strong> {{completion_date}}</p>\r\n\r\n            </div>\r\n\r\n            \r\n\r\n            <p style=\"text-align: center;\">\r\n\r\n                <a href=\"{{project_url}}\" class=\"button\">View Live Project</a>\r\n\r\n            </p>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n            <p><a href=\"{{unsubscribe_url}}\">Unsubscribe</a></p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, NULL, 0, '2026-02-16 12:45:09', '2026-02-16 12:45:09'),
(8, 'Blog Post Alert', 'New Blog Post: {{post_title}}', '\r\n\r\n<!DOCTYPE html>\r\n\r\n<html>\r\n\r\n<head>\r\n\r\n    <meta charset=\"UTF-8\">\r\n\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\r\n\r\n    <style>\r\n\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n\r\n        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }\r\n\r\n        .content { padding: 30px; background: #f9f9f9; }\r\n\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }\r\n\r\n        .post-image { width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; }\r\n\r\n        .post-content { background: white; padding: 30px; border-radius: 8px; }\r\n\r\n        .read-more { display: inline-block; margin-top: 20px; color: {{primary_color}}; text-decoration: none; font-weight: bold; }\r\n\r\n        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }\r\n\r\n    </style>\r\n\r\n</head>\r\n\r\n<body>\r\n\r\n    <div class=\"container\">\r\n\r\n        <div class=\"header\">\r\n\r\n            <h1>New Blog Post</h1>\r\n\r\n        </div>\r\n\r\n        <div class=\"content\">\r\n\r\n            <p>Hi {{first_name}},</p>\r\n\r\n            <p>I\'ve just published a new blog post that I think you\'ll find interesting:</p>\r\n\r\n            \r\n\r\n            {{post_image}}\r\n\r\n            \r\n\r\n            <div class=\"post-content\">\r\n\r\n                <h2>{{post_title}}</h2>\r\n\r\n                <p>{{post_excerpt}}</p>\r\n\r\n                <p><strong>Reading time:</strong> {{reading_time}} min</p>\r\n\r\n                <a href=\"{{post_url}}\" class=\"button\">Read Full Article</a>\r\n\r\n            </div>\r\n\r\n        </div>\r\n\r\n        <div class=\"footer\">\r\n\r\n            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>\r\n\r\n            <p><a href=\"{{unsubscribe_url}}\">Unsubscribe</a></p>\r\n\r\n        </div>\r\n\r\n    </div>\r\n\r\n</body>\r\n\r\n</html>\r\n\r\n', NULL, NULL, 0, '2026-02-16 12:45:09', '2026-02-16 12:45:09');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `layout` enum('default','full-width','sidebar-left','sidebar-right') DEFAULT 'default',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `featured_image` varchar(255) DEFAULT NULL,
  `is_homepage` tinyint(1) DEFAULT '0',
  `sort_order` int(11) DEFAULT '0',
  `view_count` int(11) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `title`, `slug`, `meta_title`, `meta_description`, `meta_keywords`, `layout`, `status`, `featured_image`, `is_homepage`, `sort_order`, `view_count`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Home', 'home', '', '', '', 'full-width', 'published', '6994bc627e98b_1771355234.jpeg', 1, 0, 61, 1, '2026-02-17 18:01:41', '2026-02-17 21:45:09'),
(2, 'About', 'about', NULL, NULL, NULL, 'default', 'published', NULL, 0, 0, 0, NULL, '2026-02-17 18:01:41', '2026-02-17 18:01:41'),
(3, 'Contact', 'contact', NULL, NULL, NULL, 'default', 'published', NULL, 0, 0, 0, NULL, '2026-02-17 18:01:41', '2026-02-17 18:01:41'),
(4, 'Testimonials', 'testimonials', '', '', '', 'default', 'published', '69aeabc2154be_1773054914.jpg', 0, 0, 0, 1, '2026-02-17 21:11:19', '2026-03-09 11:15:14'),
(5, 'Blog', 'blog', '', '', '', 'default', 'published', '69aecd1d972eb_1773063453.jpg', 0, 0, 0, 1, '2026-03-09 13:37:33', '2026-03-09 13:37:33');

-- --------------------------------------------------------

--
-- Table structure for table `page_sections`
--

CREATE TABLE `page_sections` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `section_key` varchar(100) DEFAULT NULL,
  `section_type` enum('hero','about','services','portfolio','testimonials','contact','cta','features','gallery','team','pricing','faq','blog','custom','html','text','image') DEFAULT 'custom',
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `content` longtext,
  `settings` json DEFAULT NULL,
  `background_type` enum('color','image','video','none') DEFAULT 'none',
  `background_value` text,
  `text_color` varchar(50) DEFAULT NULL,
  `layout_style` varchar(100) DEFAULT 'default',
  `css_class` varchar(255) DEFAULT NULL,
  `custom_css` text,
  `sort_order` int(11) DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `page_sections`
--

INSERT INTO `page_sections` (`id`, `page_id`, `section_key`, `section_type`, `title`, `subtitle`, `content`, `settings`, `background_type`, `background_value`, `text_color`, `layout_style`, `css_class`, `custom_css`, `sort_order`, `is_visible`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'hero', 'Welcome to Our Site', 'About me', 'The Technology Incubation complex is 6', '{}', 'none', '', '#333333', 'default', '', '', 0, 1, '2026-02-17 18:01:42', '2026-02-17 19:18:58'),
(2, 1, NULL, 'about', 'About Us', '', '<h2>Kverify Digital Solutions</h2>\r\n\r\n<p><strong>Kverify Digital Solutions</strong> provides innovative technology solutions, specializing in web design, mobile app development, and custom software development for businesses and individuals. Our services enhance security, efficiency, and digital transformation.</p>\r\n\r\n<p>At <strong>Kverify Digital Solutions</strong>, we are committed to delivering secure, scalable, and high-quality digital solutions that empower businesses in the modern world.</p>\r\n\r\n<p><strong>Partner with us today!</strong></p>', '{}', 'none', '', '#333333', 'default', '', '', 1, 1, '2026-02-17 18:01:42', '2026-02-17 21:06:17'),
(3, 1, NULL, 'services', 'Our Services', NULL, NULL, NULL, 'none', NULL, NULL, 'default', NULL, NULL, 2, 1, '2026-02-17 18:01:42', '2026-02-17 18:01:42'),
(4, 2, NULL, 'about', 'About us', 'About', '<h2>About Kverify Digital Solutions</h2>\r\n<p><strong>Kverify Digital Solutions</strong> is a forward-thinking technology company delivering innovative digital solutions for businesses and individuals.</p>\r\n\r\n<h3>Our Services</h3>\r\n<p>We specialize in web design, mobile app development, and custom software development. Our solutions are designed to enhance security, improve efficiency, and drive digital transformation.</p>\r\n\r\n<h3>Our Commitment</h3>\r\n<p>We are committed to delivering secure, scalable, and high-quality digital solutions that empower businesses in the modern world.</p>\r\n\r\n<h3>Work With Us</h3>\r\n<p><strong>Partner with Kverify Digital Solutions today and take your business to the next level.</strong></p>', '[]', 'image', '', '#333333', 'default', '', '', 1, 1, '2026-02-17 18:12:28', '2026-02-17 18:12:28'),
(5, 2, NULL, 'about', 'About us', 'About', '<h2>About Kverify Digital Solutions</h2>\r\n<p><strong>Kverify Digital Solutions</strong> is a forward-thinking technology company delivering innovative digital solutions for businesses and individuals.</p>\r\n\r\n<h3>Our Services</h3>\r\n<p>We specialize in web design, mobile app development, and custom software development. Our solutions are designed to enhance security, improve efficiency, and drive digital transformation.</p>\r\n\r\n<h3>Our Commitment</h3>\r\n<p>We are committed to delivering secure, scalable, and high-quality digital solutions that empower businesses in the modern world.</p>\r\n\r\n<h3>Work With Us</h3>\r\n<p><strong>Partner with Kverify Digital Solutions today and take your business to the next level.</strong></p>', '[]', 'image', '', '#333333', 'default', '', '', 2, 1, '2026-02-17 18:14:14', '2026-02-17 18:14:14'),
(6, 0, NULL, 'custom', '', '', '', '[]', 'none', '', '#333333', 'default', '', '', 1, 1, '2026-02-17 18:52:13', '2026-02-17 18:52:13'),
(7, 1, NULL, 'custom', 'Testimonials', 'What our Clients Say', '', '{}', 'none', '', '#333333', 'default', '', '', 3, 1, '2026-02-17 21:14:47', '2026-02-17 21:22:26'),
(8, 1, NULL, 'custom', 'Skills', 'Skills', '', '{}', 'none', '', '#333333', 'default', '', '', 4, 1, '2026-02-17 21:20:20', '2026-02-17 21:20:31');

-- --------------------------------------------------------

--
-- Table structure for table `page_templates`
--

CREATE TABLE `page_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `thumbnail` varchar(255) DEFAULT NULL,
  `layout` json DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `page_templates`
--

INSERT INTO `page_templates` (`id`, `name`, `slug`, `description`, `thumbnail`, `layout`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 'Blank Page', 'blank', 'Start with a completely blank page', NULL, NULL, 1, '2026-02-17 18:01:41', '2026-02-17 18:01:41'),
(2, 'Home Page', 'home', 'Complete homepage with hero, about, services, portfolio sections', NULL, NULL, 0, '2026-02-17 18:01:41', '2026-02-17 18:01:41'),
(3, 'About Page', 'about', 'About page with team, skills, and timeline sections', NULL, NULL, 0, '2026-02-17 18:01:41', '2026-02-17 18:01:41'),
(4, 'Contact Page', 'contact', 'Contact page with form and map', NULL, NULL, 0, '2026-02-17 18:01:41', '2026-02-17 18:01:41'),
(5, 'Services Page', 'services', 'Services listing page', NULL, NULL, 0, '2026-02-17 18:01:41', '2026-02-17 18:01:41'),
(6, 'Portfolio Page', 'portfolio', 'Portfolio gallery page', NULL, NULL, 0, '2026-02-17 18:01:41', '2026-02-17 18:01:41');

-- --------------------------------------------------------

--
-- Table structure for table `page_views`
--

CREATE TABLE `page_views` (
  `id` int(11) NOT NULL,
  `page_url` varchar(500) NOT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `referrer_url` varchar(500) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time NOT NULL,
  `visit_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `session_id` varchar(100) DEFAULT NULL,
  `visitor_id` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `device_type` enum('desktop','tablet','mobile','bot') DEFAULT 'desktop',
  `browser` varchar(100) DEFAULT NULL,
  `browser_version` varchar(50) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `os_version` varchar(50) DEFAULT NULL,
  `screen_resolution` varchar(20) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `is_unique` tinyint(1) DEFAULT '0',
  `engagement_time` int(11) DEFAULT '0',
  `scroll_depth` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `page_views`
--

INSERT INTO `page_views` (`id`, `page_url`, `page_title`, `visitor_ip`, `user_agent`, `referrer_url`, `visit_date`, `visit_time`, `visit_timestamp`, `session_id`, `visitor_id`, `country`, `city`, `region`, `latitude`, `longitude`, `device_type`, `browser`, `browser_version`, `os`, `os_version`, `screen_resolution`, `language`, `timezone`, `is_unique`, `engagement_time`, `scroll_depth`, `created_at`) VALUES
(1, '/projects', NULL, '192.168.193.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NULL, '2026-02-01', '15:48:22', '2026-02-16 13:15:24', '8d999add-0b39-11f1-b30d-8690419422ed', 'visitor_77', NULL, NULL, NULL, NULL, NULL, 'mobile', 'Chrome', NULL, 'macOS', NULL, NULL, NULL, NULL, 0, 0, 0, '2026-02-16 13:15:24');

--
-- Triggers `page_views`
--
DELIMITER $$
CREATE TRIGGER `update_realtime_on_pageview` AFTER INSERT ON `page_views` FOR EACH ROW BEGIN
    INSERT INTO realtime_analytics (session_id, visitor_id, page_url, last_activity)
    VALUES (NEW.session_id, NEW.visitor_id, NEW.page_url, NOW())
    ON DUPLICATE KEY UPDATE
        last_activity = NOW(),
        current_page = NEW.page_url,
        time_on_current_page = 0;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_session_on_pageview` AFTER INSERT ON `page_views` FOR EACH ROW BEGIN
    UPDATE user_sessions 
    SET page_views = page_views + 1,
        last_activity = NOW()
    WHERE session_id = NEW.session_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateways`
--

CREATE TABLE `payment_gateways` (
  `id` int(11) NOT NULL,
  `gateway_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT '0',
  `api_key` text,
  `api_secret` text,
  `webhook_secret` text,
  `sandbox_mode` tinyint(1) DEFAULT '1',
  `sandbox_api_key` text,
  `sandbox_api_secret` text,
  `settings` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `payment_gateways`
--

INSERT INTO `payment_gateways` (`id`, `gateway_name`, `is_active`, `api_key`, `api_secret`, `webhook_secret`, `sandbox_mode`, `sandbox_api_key`, `sandbox_api_secret`, `settings`, `created_at`, `updated_at`) VALUES
(1, 'Stripe', 0, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-16 12:13:02', '2026-02-16 12:13:02'),
(2, 'PayPal', 0, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-16 12:13:02', '2026-02-16 12:13:02'),
(3, 'Bank Transfer', 1, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-16 12:13:02', '2026-03-09 10:38:17');

-- --------------------------------------------------------

--
-- Table structure for table `performance_metrics`
--

CREATE TABLE `performance_metrics` (
  `id` int(11) NOT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `load_time` float DEFAULT NULL,
  `dom_interactive` float DEFAULT NULL,
  `dom_complete` float DEFAULT NULL,
  `first_paint` float DEFAULT NULL,
  `first_contentful_paint` float DEFAULT NULL,
  `time_to_interactive` float DEFAULT NULL,
  `server_response_time` float DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` text,
  `full_description` text,
  `category` varchar(100) DEFAULT NULL,
  `technologies` text,
  `client_name` varchar(200) DEFAULT NULL,
  `client_website` varchar(255) DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `project_url` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `gallery_images` text,
  `is_featured` tinyint(1) DEFAULT '0',
  `status` enum('draft','published','planning','in_progress','completed','on_hold','cancelled') DEFAULT 'draft',
  `progress` int(11) DEFAULT '0',
  `views` int(11) DEFAULT '0',
  `display_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `budget` decimal(10,2) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT '0.00',
  `start_date` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `client_id`, `title`, `slug`, `short_description`, `full_description`, `category`, `technologies`, `client_name`, `client_website`, `completion_date`, `project_url`, `github_url`, `featured_image`, `gallery_images`, `is_featured`, `status`, `progress`, `views`, `display_order`, `created_at`, `updated_at`, `budget`, `paid_amount`, `start_date`) VALUES
(1, 9, 'AGPN', 'agpn', 'The Technology Incubation complex corporate business has a href', 'There are a couple things I want you want me for the centre through the way you are doing and that you will not have to do anything for you to do that ', 'Cms', 'HTML, CSS, JS, PHP and MYSQL', 'AMINA ABUJA', 'https://e-book', '2026-02-16', 'http://www.youtube.com', 'http://www.w3.org', '69aebd7049da1_1773059440.jpg', NULL, 1, 'published', 0, 0, 0, '2026-02-16 09:08:47', '2026-03-11 12:44:55', NULL, 0.00, NULL),
(2, 10, 'E-shop', 'e-shop', 'There is no need to apologize to anyone 6', 'There are pests of us to have the opportunity for you want next year and I hope that we will be notified by our next academic meeting with ', 'E-Commerce', 'HTML, CSS, JS, PHP and MYSQL', 'AMINA ABUJA', 'https://e-shop.com.ng', '2025-02-16', 'https://e-shop.com.ng', 'https://github.com/kuyabetech', '69aebd5dd56a5_1773059421.jpg', NULL, 1, 'published', 0, 0, 0, '2026-02-16 21:18:19', '2026-03-11 13:26:19', 10000.00, 0.00, '2026-03-11'),
(3, NULL, 'E-commerce Platform', 'e-commerce-platform', 'Modern e-commerce solution with React and Node.js', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'in_progress', 0, 0, 0, '2026-02-09 13:35:33', '2026-03-11 13:35:33', 25000.00, 0.00, '2026-02-09'),
(4, 9, 'Mobile App Development', 'mobile-app-development', 'iOS and Android app for food delivery', '', '', 'HTML, CSS, JS, PHP and MYSQL', '', '', '2026-03-11', '', '', NULL, NULL, 0, 'completed', 0, 0, 0, '2026-03-11 13:35:33', '2026-03-11 13:42:28', 15000.00, 0.00, '2026-03-11'),
(5, NULL, 'Website Redesign', 'website-redesign', 'Complete overhaul of corporate website', NULL, NULL, NULL, NULL, NULL, '2026-03-06', NULL, NULL, NULL, NULL, 0, 'completed', 0, 0, 0, '2026-01-10 13:35:33', '2026-03-11 13:35:33', 8000.00, 0.00, '2026-01-10');

--
-- Triggers `projects`
--
DELIMITER $$
CREATE TRIGGER `update_client_completed_projects` AFTER UPDATE ON `projects` FOR EACH ROW BEGIN

    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN

        UPDATE clients SET completed_projects = completed_projects + 1 WHERE id = NEW.client_id;

    ELSEIF NEW.status != 'completed' AND OLD.status = 'completed' THEN

        UPDATE clients SET completed_projects = completed_projects - 1 WHERE id = NEW.client_id;

    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `project_activity_log`
--

CREATE TABLE `project_activity_log` (
  `id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `project_documents`
--

CREATE TABLE `project_documents` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `filename` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `project_expenses`
--

CREATE TABLE `project_expenses` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `receipt_file` varchar(255) DEFAULT NULL,
  `billable` tinyint(1) DEFAULT '1',
  `billed_invoice_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `project_expenses`
--

INSERT INTO `project_expenses` (`id`, `project_id`, `expense_date`, `category`, `description`, `amount`, `tax_amount`, `receipt_file`, `billable`, `billed_invoice_id`, `status`, `approved_by`, `notes`, `created_by`, `created_at`) VALUES
(2, 2, '2026-03-10', 'hosting', 'Have you heard from a couple people that I have heard of this week or is there are', 30.00, 0.00, '69b00632368aa_1773143602.jpg', 1, NULL, 'approved', NULL, '', 1, '2026-03-10 11:52:54');

-- --------------------------------------------------------

--
-- Table structure for table `project_files`
--

CREATE TABLE `project_files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text,
  `is_client_visible` tinyint(1) DEFAULT '1',
  `downloads` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `project_invoices`
--

CREATE TABLE `project_invoices` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('draft','sent','viewed','paid','overdue','cancelled','refunded') DEFAULT 'draft',
  `bill_to` text NOT NULL,
  `ship_to` text,
  `items` json NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `discount_type` enum('percentage','fixed') DEFAULT NULL,
  `discount_value` decimal(10,2) DEFAULT '0.00',
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `shipping_amount` decimal(10,2) DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT '0.00',
  `balance_due` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_terms` text,
  `notes` text,
  `terms_conditions` text,
  `tax_id` varchar(100) DEFAULT NULL,
  `business_number` varchar(100) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `viewed_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sort_order` int(11) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `project_invoices`
--

INSERT INTO `project_invoices` (`id`, `project_id`, `client_id`, `invoice_number`, `invoice_date`, `due_date`, `status`, `bill_to`, `ship_to`, `items`, `subtotal`, `tax_rate`, `tax_amount`, `discount_type`, `discount_value`, `discount_amount`, `shipping_amount`, `total`, `paid_amount`, `balance_due`, `currency`, `payment_method`, `payment_terms`, `notes`, `terms_conditions`, `tax_id`, `business_number`, `pdf_path`, `sent_at`, `viewed_at`, `paid_at`, `created_by`, `created_at`, `updated_at`, `sort_order`) VALUES
(1, 2, 2, 'INV-202603-0001', '2026-03-10', '2026-04-09', 'sent', 'Gbacinku via lapai ', 'Gbacinku ', '[{\"type\": \"service\", \"discount\": 2, \"quantity\": 1, \"tax_rate\": 1, \"unit_price\": 1000, \"description\": \"Project\"}]', 1000.00, 0.00, 0.00, 'percentage', 1.20, 12.00, 0.00, 988.00, 0.00, 988.00, 'USD', NULL, 'due_on_receipt', 'The Technology is a href and the soluble is the study ', 'Payment is due within 30 days. Thank you for your business.', '123', '123456', 'invoices/invoice-1.pdf', NULL, NULL, NULL, 1, '2026-03-10 10:44:14', '2026-03-10 10:44:14', 0),
(2, 2, 2, 'INV-202603-0002', '2026-03-10', '2026-04-09', 'sent', 'Gbacinku via lapai ', 'Gbacinku ', '[{\"type\": \"service\", \"discount\": 0, \"quantity\": 1, \"tax_rate\": 0, \"unit_price\": 1000, \"description\": \"Projects \"}]', 1000.00, 0.00, 0.00, 'percentage', 0.20, 2.00, 0.00, 998.00, 0.00, 998.00, 'USD', NULL, 'due_on_receipt', 'The Technology is not luck and I will be paramagnetic and ', 'Payment is due within 30 days. Thank you for your business.', '123', '123456', 'invoices/invoice-2.pdf', NULL, NULL, NULL, 1, '2026-03-10 11:01:53', '2026-03-10 11:01:53', 0),
(3, 2, 2, 'INV-202603-0003', '2026-03-10', '2026-04-09', 'sent', 'Gbacinku via lapai ', 'Gbacinku ', '[{\"type\": \"service\", \"discount\": 0, \"quantity\": 1, \"tax_rate\": 0, \"unit_price\": 1000, \"description\": \"Project\"}]', 1000.00, 0.00, 0.00, 'percentage', 0.20, 2.00, 0.00, 998.00, 0.00, 998.00, 'USD', NULL, 'due_on_receipt', 'The Technology is not luck and I will be paramagnetic and ', 'Payment is due within 30 days. Thank you for your business.', '123', '123456', 'invoices/invoice-3.pdf', NULL, NULL, NULL, 1, '2026-03-10 11:08:14', '2026-03-10 11:08:14', 0),
(4, 3, 9, 'INV-202603-0004', '2026-03-11', '2026-04-10', 'sent', 'Baby is the optimum in your body ', 'The card is a 666', '[{\"type\": \"service\", \"discount\": 0, \"quantity\": 1, \"tax_rate\": 0, \"unit_price\": 100, \"description\": \"There are a couple things \"}]', 100.00, 0.00, 0.00, 'percentage', 0.00, 0.00, 0.00, 100.00, 0.00, 100.00, 'USD', NULL, 'due_on_receipt', '', 'Payment is due within 30 days. Thank you for your business.', '123', '123456', NULL, NULL, NULL, NULL, 1, '2026-03-11 13:45:10', '2026-03-11 13:45:10', 0);

-- --------------------------------------------------------

--
-- Table structure for table `project_messages`
--

CREATE TABLE `project_messages` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_client_message` tinyint(1) DEFAULT '0',
  `is_read` tinyint(1) DEFAULT '0',
  `attachments` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `project_milestones`
--

CREATE TABLE `project_milestones` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `status` enum('pending','completed','delayed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `project_tasks`
--

CREATE TABLE `project_tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `assigned_to` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','blocked') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `estimated_hours` decimal(5,2) DEFAULT NULL,
  `actual_hours` decimal(5,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `project_timeline`
--

CREATE TABLE `project_timeline` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `due_date` date NOT NULL,
  `completed` tinyint(1) DEFAULT '0',
  `completed_at` datetime DEFAULT NULL,
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `project_timeline`
--

INSERT INTO `project_timeline` (`id`, `project_id`, `title`, `description`, `due_date`, `completed`, `completed_at`, `sort_order`, `created_at`) VALUES
(1, 3, 'Requirements Gathering', 'Collect and document all project requirements', '2026-02-16', 1, NULL, 0, '2026-03-11 13:35:33'),
(2, 3, 'Design Phase', 'Create wireframes and design mockups', '2026-02-23', 1, NULL, 0, '2026-03-11 13:35:33'),
(3, 3, 'Development Sprint 1', 'Core functionality implementation', '2026-03-11', 0, NULL, 0, '2026-03-11 13:35:33'),
(4, 3, 'Testing & QA', 'Quality assurance and bug fixing', '2026-03-26', 0, NULL, 0, '2026-03-11 13:35:33');

-- --------------------------------------------------------

--
-- Table structure for table `realtime_analytics`
--

CREATE TABLE `realtime_analytics` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `visitor_id` varchar(100) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `current_page` varchar(500) DEFAULT NULL,
  `time_on_current_page` int(11) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_invoices`
--

CREATE TABLE `recurring_invoices` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `frequency` enum('weekly','biweekly','monthly','quarterly','biannually','annually') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_date` date NOT NULL,
  `last_generated` date DEFAULT NULL,
  `template` json NOT NULL,
  `status` enum('active','paused','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `revenue_tracking`
--

CREATE TABLE `revenue_tracking` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `visitor_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL,
  `products` json DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `converted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `saved_reports`
--

CREATE TABLE `saved_reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `report_name` varchar(255) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `report_config` json DEFAULT NULL,
  `date_range` varchar(50) DEFAULT NULL,
  `filters` json DEFAULT NULL,
  `chart_type` varchar(50) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT '0',
  `share_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `section_items`
--

CREATE TABLE `section_items` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `link_text` varchar(100) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `sort_order` int(11) DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1',
  `custom_fields` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `seo_analytics`
--

CREATE TABLE `seo_analytics` (
  `id` int(11) NOT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `page_type` varchar(50) DEFAULT NULL,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `referrer_url` text,
  `visit_date` date DEFAULT NULL,
  `visit_time` time DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `device_type` enum('desktop','tablet','mobile') DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `screen_resolution` varchar(20) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `seo_analytics`
--

INSERT INTO `seo_analytics` (`id`, `page_url`, `page_type`, `visitor_ip`, `user_agent`, `referrer_url`, `visit_date`, `visit_time`, `country`, `city`, `device_type`, `browser`, `os`, `screen_resolution`, `language`, `created_at`) VALUES
(1, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 'http://localhost:8081/admin/index.php', '2026-02-16', '10:06:13', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 10:06:13'),
(2, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '10:14:55', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 10:14:55'),
(3, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '10:14:58', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 10:14:58'),
(4, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '10:38:44', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 10:38:44'),
(5, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '12:19:31', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 12:19:31'),
(6, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '13:19:14', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 13:19:14'),
(7, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '14:21:21', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 14:21:21'),
(8, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '19:57:06', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 19:57:06'),
(9, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '19:57:19', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 19:57:19'),
(10, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '20:02:15', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 20:02:15'),
(11, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '20:02:49', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 20:02:49'),
(12, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '20:04:09', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 20:04:09'),
(13, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '20:39:50', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 20:39:50'),
(14, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '20:59:18', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 20:59:18'),
(15, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '21:19:18', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 21:19:18'),
(16, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '21:19:54', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 21:19:54'),
(17, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '21:20:49', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 21:20:49'),
(18, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '21:23:47', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 21:23:47'),
(19, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '22:00:40', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 22:00:40'),
(20, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '22:01:22', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 22:01:22'),
(21, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '22:02:09', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 22:02:09'),
(22, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-16', '22:02:18', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-16 22:02:18'),
(23, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2026-02-17', '06:37:31', NULL, NULL, 'desktop', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 06:37:31'),
(24, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2026-02-17', '06:40:01', NULL, NULL, 'desktop', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 06:40:01'),
(25, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2026-02-17', '07:15:15', NULL, NULL, 'desktop', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 07:15:15'),
(26, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-17', '07:15:20', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 07:15:20'),
(27, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-17', '07:16:00', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 07:16:00'),
(28, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', NULL, '2026-02-17', '07:18:01', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 07:18:01'),
(29, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', NULL, '2026-02-17', '17:42:09', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 17:42:09'),
(30, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', NULL, '2026-02-17', '18:02:41', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 18:02:41'),
(31, '/', 'home', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', NULL, '2026-02-17', '18:21:55', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, 'en', '2026-02-17 18:21:55'),
(32, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:18:04', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:18:04'),
(33, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:21:10', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:21:10'),
(34, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:21:17', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:21:17'),
(35, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:22:07', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:22:07'),
(36, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:24:10', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:24:10'),
(37, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:25:21', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:25:21'),
(38, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:25:55', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:25:55'),
(39, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:26:16', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:26:16'),
(40, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:29:39', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:29:39'),
(41, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:29:46', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:29:46'),
(42, '/index.php', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:34:13', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:34:13'),
(43, '/index.php?url=blog/how-create-home-page', 'page', '127.0.0.1', NULL, 'http://localhost:8081/index.php?url=blog/how-create-home-page', '2026-03-12', '10:35:45', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:35:45'),
(44, '/index.php?url=blog/how-create-home-page', 'page', '127.0.0.1', NULL, 'http://localhost:8081/blog.php', '2026-03-12', '10:36:07', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:36:07'),
(45, '/index.php?url=blog/how-create-home-page', 'page', '127.0.0.1', NULL, 'http://localhost:8081/blog.php', '2026-03-12', '10:38:26', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:38:26'),
(46, '/index.php?url=blog/how-create-home-page', 'page', '127.0.0.1', NULL, 'http://localhost:8081/index.php?url=blog/how-create-home-page', '2026-03-12', '10:38:40', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:38:40'),
(47, '/index.php?url=blog/how-create-home-page', 'page', '127.0.0.1', NULL, 'http://localhost:8081/index.php?url=blog/how-create-home-page', '2026-03-12', '10:38:41', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:38:41'),
(48, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:38:50', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:38:50'),
(49, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:42:46', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:42:46'),
(50, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '10:53:19', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 10:53:19'),
(51, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:09:28', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:09:28'),
(52, '/', 'home', '127.0.0.1', NULL, 'http://localhost:8081/project.php', '2026-03-12', '11:13:54', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:13:54'),
(53, '/', 'home', '127.0.0.1', NULL, 'http://localhost:8081/project.php', '2026-03-12', '11:14:19', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:14:19'),
(54, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:14:26', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:14:26'),
(55, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:15:52', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:15:52'),
(56, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:16:06', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:16:06'),
(57, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:16:08', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:16:08'),
(58, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:16:17', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:16:17'),
(59, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:16:41', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:16:41'),
(60, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:21:11', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:21:11'),
(61, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:21:14', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:21:14'),
(62, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:21:23', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:21:23'),
(63, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:21:54', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:21:54'),
(64, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:23:22', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:23:22'),
(65, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:25:50', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:25:50'),
(66, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:28:49', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:28:49'),
(67, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:30:57', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:30:57'),
(68, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:30:59', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:30:59'),
(69, '/', 'home', '127.0.0.1', NULL, '', '2026-03-12', '11:31:23', NULL, NULL, 'mobile', 'Chrome', 'Linux', NULL, NULL, '2026-03-12 11:31:23');

-- --------------------------------------------------------

--
-- Table structure for table `seo_metadata`
--

CREATE TABLE `seo_metadata` (
  `id` int(11) NOT NULL,
  `page_url` varchar(255) NOT NULL,
  `page_type` enum('home','projects','blog','contact','custom') DEFAULT 'custom',
  `title` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `meta_keywords` text,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text,
  `og_image` varchar(255) DEFAULT NULL,
  `twitter_title` varchar(255) DEFAULT NULL,
  `twitter_description` text,
  `twitter_image` varchar(255) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `noindex` tinyint(1) DEFAULT '0',
  `nofollow` tinyint(1) DEFAULT '0',
  `schema_markup` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `seo_metadata`
--

INSERT INTO `seo_metadata` (`id`, `page_url`, `page_type`, `title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `twitter_title`, `twitter_description`, `twitter_image`, `canonical_url`, `noindex`, `nofollow`, `schema_markup`, `created_at`, `updated_at`) VALUES
(1, '/', 'home', 'Kverify Digital Solutions - Professional Web Developer Portfolio', 'Expert web developer specializing in custom PHP solutions, responsive designs, and web applications. 5+ years experience delivering high-quality projects.', '', '', '', '69b1a04ba3e3c_1773248587.jpg', '', '', NULL, '', 0, 0, '', '2026-02-16 09:48:36', '2026-03-11 17:03:07');

-- --------------------------------------------------------

--
-- Table structure for table `seo_redirects`
--

CREATE TABLE `seo_redirects` (
  `id` int(11) NOT NULL,
  `old_url` varchar(255) NOT NULL,
  `new_url` varchar(255) NOT NULL,
  `status_code` int(11) DEFAULT '301',
  `hits` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `seo_sitemap_queue`
--

CREATE TABLE `seo_sitemap_queue` (
  `id` int(11) NOT NULL,
  `url` varchar(255) NOT NULL,
  `priority` decimal(2,1) DEFAULT '0.5',
  `change_frequency` enum('always','hourly','daily','weekly','monthly','yearly','never') DEFAULT 'weekly',
  `last_modified` timestamp NULL DEFAULT NULL,
  `last_submitted` timestamp NULL DEFAULT NULL,
  `status` enum('pending','submitted','error') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('text','textarea','image','color') DEFAULT 'text'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `setting_type`) VALUES
(1, 'site_name', 'Kverify Digital Solutions', 'text'),
(2, 'site_title', 'Professional Web Developer Portfolio', 'text'),
(3, 'site_description', 'Expert web developer specializing in custom PHP solutions', 'textarea'),
(4, 'contact_email', 'support@mmkexpress.com', 'text'),
(5, 'contact_phone', '+2349034095385', 'text'),
(6, 'address', 'EL-WAZIR ESTATE BOSSO MINNA, NIGER STATE', 'text'),
(7, 'primary_color', '#2563eb', 'color'),
(8, 'secondary_color', '#7c3aed', 'color'),
(9, 'github_url', 'https://github.com/kuyabetech', 'text'),
(10, 'linkedin_url', 'https://linkedin.com/in/kuyabetech', 'text'),
(11, 'twitter_url', 'https://twitter.com/kuyabetech', 'text'),
(12, 'site_keywords', '', 'text'),
(13, 'facebook_url', '', 'text'),
(14, 'instagram_url', 'http://Instagram.com/kuyabetech', 'text'),
(15, 'google_analytics', 'G-3L07WHV6B3', 'text'),
(16, 'robots_txt', 'User-agent: *\r\nAllow: /\r\nDisallow: /admin/\r\nSitemap: http://localhost:8081/admin/sitemap.xml', 'text'),
(17, 'smtp_host', 'mmkexpress.com', 'text'),
(18, 'smtp_port', '587', 'text'),
(19, 'smtp_encryption', 'tls', 'text'),
(20, 'smtp_username', 'info@mmkexpress.com', 'text'),
(21, 'smtp_password', 'Muktar@12/@', 'text'),
(22, 'maintenance_message', 'Site is under maintenance. Please check back later.', 'text'),
(23, 'maintenance_mode', '1', 'text'),
(24, 'newsletter_enabled', '1', 'text'),
(25, 'mailchimp_api_key', '', 'text'),
(26, 'mailchimp_list_id', '', 'text'),
(27, 'success_color', '#10b981', 'text'),
(28, 'warning_color', '#f59e0b', 'text'),
(29, 'danger_color', '#ef4444', 'text'),
(30, 'info_color', '#3b82f6', 'text'),
(31, 'dark_color', '#0f172a', 'text'),
(32, 'light_color', '#f8fafc', 'text'),
(33, 'youtube_url', 'http://youtube.com/kuyabetech', 'text'),
(34, 'facebook_pixel', '', 'text'),
(35, 'smtp_from_email', 'info@mmkexpress.com', 'text'),
(36, 'site_logo', '69aeab155d20a_1773054741.png', 'image'),
(37, 'favicon', '69aeab156319a_1773054741.png', 'image'),
(38, 'smtp_from_name', 'Kverify Digital Solutions', 'text'),
(39, 'smtp_reply_to', 'info@mmkexpress.com', 'text');

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT 'technical',
  `proficiency` int(11) DEFAULT NULL,
  `icon_class` varchar(100) DEFAULT NULL,
  `years_experience` decimal(3,1) DEFAULT NULL,
  `display_order` int(11) DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`id`, `name`, `category`, `proficiency`, `icon_class`, `years_experience`, `display_order`, `is_visible`) VALUES
(1, 'PHP', 'technical', 95, 'fab fa-php', 5.0, 1, 1),
(2, 'JavaScript', 'technical', 90, 'fab fa-js', 5.0, 2, 1),
(3, 'MySQL', 'technical', 92, 'fas fa-database', 4.0, 3, 1),
(4, 'HTML5/CSS3', 'technical', 98, 'fab fa-html5', 5.0, 4, 1),
(5, 'React', 'technical', 85, 'fab fa-react', 3.0, 5, 1),
(6, 'Laravel', 'technical', 88, 'fab fa-laravel', 4.0, 6, 1),
(7, 'Python', 'professional', 95, 'fa-brands fa-python', 5.0, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `ticket_number` varchar(20) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','waiting','resolved','closed') DEFAULT 'open',
  `last_reply_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `system_health`
--

CREATE TABLE `system_health` (
  `id` int(11) NOT NULL,
  `metric_key` varchar(100) NOT NULL,
  `metric_value` text,
  `metric_type` enum('gauge','counter','text') DEFAULT 'gauge',
  `status` enum('ok','warning','critical') DEFAULT 'ok',
  `last_checked` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tax_rates`
--

CREATE TABLE `tax_rates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `type` enum('inclusive','exclusive') DEFAULT 'exclusive',
  `is_default` tinyint(1) DEFAULT '0',
  `applies_to` varchar(50) DEFAULT 'all',
  `region` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `tax_rates`
--

INSERT INTO `tax_rates` (`id`, `name`, `rate`, `type`, `is_default`, `applies_to`, `region`, `created_at`) VALUES
(1, 'No Tax', 0.00, 'exclusive', 1, 'all', NULL, '2026-02-16 12:13:02'),
(2, 'VAT 20%', 20.00, 'exclusive', 0, 'all', NULL, '2026-02-16 12:13:02'),
(3, 'Sales Tax 10%', 10.00, 'exclusive', 0, 'all', NULL, '2026-02-16 12:13:02');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `client_name` varchar(200) NOT NULL,
  `client_position` varchar(200) DEFAULT NULL,
  `client_company` varchar(200) DEFAULT NULL,
  `client_image` varchar(255) DEFAULT NULL,
  `testimonial` text NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `status` enum('pending','approved') DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `client_name`, `client_position`, `client_company`, `client_image`, `testimonial`, `rating`, `project_id`, `is_featured`, `status`, `created_at`) VALUES
(1, 'SAMBO ZAMFARA', 'CEO', 'Mr five fingers', '6992e57ba1347_1771234683.jpeg', 'Very good health insurance Best health care for a new home and home', 4, 1, 1, 'approved', '2026-02-16 09:38:03'),
(2, 'Sarah Johnson', 'CEO', 'TechStart Inc.', NULL, 'Working with this developer was an absolute pleasure. They delivered our e-commerce platform ahead of schedule and exceeded all our expectations.', 5, 2, 1, 'approved', '2026-02-17 21:30:23'),
(3, 'Michael Chen', 'Founder', 'Creative Agency', NULL, 'One of the most talented developers I\'ve worked with. They not only built exactly what we needed but also suggested improvements that made our site even better.', 5, NULL, 1, 'approved', '2026-02-17 21:30:23'),
(4, 'Emily Rodriguez', 'Marketing Director', 'Growth Solutions', NULL, 'The web application developed for us has transformed our business operations. It\'s intuitive, fast, and our team loves using it.', 4, NULL, 0, 'approved', '2026-02-17 21:30:23');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_replies`
--

CREATE TABLE `ticket_replies` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_staff` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `bio` text,
  `role` enum('admin','editor') DEFAULT 'admin',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `profile_image`, `bio`, `role`, `last_login`, `created_at`) VALUES
(1, 'admin', 'admin@kverify.com', '$2y$10$EaPliU5sWiLZ2AlB7irj1uALZ6xU5GLG3qgwSmMnD9iAcuSw2S38K', 'ABDULRAHMAN ISHA', '69aeaa732867e_1773054579.jpg', '', 'admin', '2026-03-12 11:17:21', '2026-02-16 08:33:41');

-- --------------------------------------------------------

--
-- Table structure for table `user_dashboards`
--

CREATE TABLE `user_dashboards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `widget_id` int(11) NOT NULL,
  `position_x` int(11) DEFAULT '0',
  `position_y` int(11) DEFAULT '0',
  `width` int(11) DEFAULT '1',
  `height` int(11) DEFAULT '1',
  `custom_settings` json DEFAULT NULL,
  `is_visible` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `user_dashboards`
--

INSERT INTO `user_dashboards` (`id`, `user_id`, `widget_id`, `position_x`, `position_y`, `width`, `height`, `custom_settings`, `is_visible`, `created_at`, `updated_at`) VALUES
(32, 1, 6, 0, 0, 2, 2, NULL, 1, '2026-02-16 21:50:26', '2026-02-16 21:50:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_events`
--

CREATE TABLE `user_events` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `visitor_id` varchar(100) DEFAULT NULL,
  `event_type` enum('click','form_submit','download','video_play','video_complete','scroll','hover','exit_intent','conversion') NOT NULL,
  `event_category` varchar(100) DEFAULT NULL,
  `event_action` varchar(255) DEFAULT NULL,
  `event_label` varchar(255) DEFAULT NULL,
  `event_value` int(11) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `element_id` varchar(255) DEFAULT NULL,
  `element_class` varchar(255) DEFAULT NULL,
  `element_text` text,
  `coordinates` varchar(50) DEFAULT NULL,
  `form_data` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `visitor_id` varchar(100) NOT NULL,
  `entry_page` varchar(500) DEFAULT NULL,
  `exit_page` varchar(500) DEFAULT NULL,
  `referrer_url` varchar(500) DEFAULT NULL,
  `utm_source` varchar(100) DEFAULT NULL,
  `utm_medium` varchar(100) DEFAULT NULL,
  `utm_campaign` varchar(100) DEFAULT NULL,
  `utm_term` varchar(100) DEFAULT NULL,
  `utm_content` varchar(100) DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `duration` int(11) DEFAULT '0',
  `page_views` int(11) DEFAULT '1',
  `bounced` tinyint(1) DEFAULT '0',
  `converted` tinyint(1) DEFAULT '0',
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ab_tests`
--
ALTER TABLE `ab_tests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ab_test_results`
--
ALTER TABLE `ab_test_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `idx_test_id` (`test_id`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indexes for table `ab_test_variants`
--
ALTER TABLE `ab_test_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `account_deletion_requests`
--
ALTER TABLE `account_deletion_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `analytics_alerts`
--
ALTER TABLE `analytics_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `api_analytics`
--
ALTER TABLE `api_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_endpoint` (`endpoint`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status_code` (`status_code`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `blog_comments`
--
ALTER TABLE `blog_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `blog_post_tags`
--
ALTER TABLE `blog_post_tags`
  ADD PRIMARY KEY (`post_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `blog_tags`
--
ALTER TABLE `blog_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `client_activity_log`
--
ALTER TABLE `client_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `client_documents`
--
ALTER TABLE `client_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `client_invoices`
--
ALTER TABLE `client_invoices`
  ADD PRIMARY KEY (`client_id`,`invoice_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_invoice` (`invoice_id`);

--
-- Indexes for table `client_messages`
--
ALTER TABLE `client_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_client_status` (`client_id`,`status`);

--
-- Indexes for table `client_notifications`
--
ALTER TABLE `client_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_client_read` (`client_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `client_projects`
--
ALTER TABLE `client_projects`
  ADD PRIMARY KEY (`client_id`,`project_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `client_remember_tokens`
--
ALTER TABLE `client_remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `client_sessions`
--
ALTER TABLE `client_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Indexes for table `client_users`
--
ALTER TABLE `client_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `custom_dimensions`
--
ALTER TABLE `custom_dimensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dimension_name` (`dimension_name`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indexes for table `dashboard_alerts`
--
ALTER TABLE `dashboard_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `dashboard_widgets`
--
ALTER TABLE `dashboard_widgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `widget_key` (`widget_key`);

--
-- Indexes for table `data_retention_policies`
--
ALTER TABLE `data_retention_policies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_table` (`table_name`);

--
-- Indexes for table `email_analytics`
--
ALTER TABLE `email_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign_id` (`campaign_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_event_type` (`event_type`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_scheduled` (`scheduled_at`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_key` (`template_key`);

--
-- Indexes for table `funnel_analytics`
--
ALTER TABLE `funnel_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_funnel_name` (`funnel_name`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_completed` (`completed`);

--
-- Indexes for table `funnel_steps`
--
ALTER TABLE `funnel_steps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_funnel_step` (`funnel_name`,`step_number`);

--
-- Indexes for table `geoip_cache`
--
ALTER TABLE `geoip_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_country` (`country_code`);

--
-- Indexes for table `goal_conversions`
--
ALTER TABLE `goal_conversions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_goal_name` (`goal_name`),
  ADD KEY `idx_converted_at` (`converted_at`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_number` (`payment_number`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `message_replies`
--
ALTER TABLE `message_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_message_replies` (`message_id`);

--
-- Indexes for table `newsletter_campaigns`
--
ALTER TABLE `newsletter_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `newsletter_stats`
--
ALTER TABLE `newsletter_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_campaign_subscriber` (`campaign_id`,`subscriber_id`),
  ADD KEY `subscriber_id` (`subscriber_id`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `newsletter_templates`
--
ALTER TABLE `newsletter_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `page_sections`
--
ALTER TABLE `page_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_page_id` (`page_id`),
  ADD KEY `idx_sort_order` (`sort_order`);

--
-- Indexes for table `page_templates`
--
ALTER TABLE `page_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `page_views`
--
ALTER TABLE `page_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visit_date` (`visit_date`),
  ADD KEY `idx_page_url` (`page_url`(255)),
  ADD KEY `idx_visitor_id` (`visitor_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_device_type` (`device_type`),
  ADD KEY `idx_country` (`country`);

--
-- Indexes for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_page_url` (`page_url`(255)),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_client_status` (`client_id`,`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_client` (`client_id`);

--
-- Indexes for table `project_activity_log`
--
ALTER TABLE `project_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `project_documents`
--
ALTER TABLE `project_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_client` (`client_id`);

--
-- Indexes for table `project_expenses`
--
ALTER TABLE `project_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `billed_invoice_id` (`billed_invoice_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `project_invoices`
--
ALTER TABLE `project_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_client_status` (`client_id`,`status`);

--
-- Indexes for table `project_messages`
--
ALTER TABLE `project_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `project_milestones`
--
ALTER TABLE `project_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_tasks`
--
ALTER TABLE `project_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `project_timeline`
--
ALTER TABLE `project_timeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_due_date` (`due_date`);

--
-- Indexes for table `realtime_analytics`
--
ALTER TABLE `realtime_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_last_activity` (`last_activity`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `recurring_invoices`
--
ALTER TABLE `recurring_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `revenue_tracking`
--
ALTER TABLE `revenue_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_converted_at` (`converted_at`);

--
-- Indexes for table `saved_reports`
--
ALTER TABLE `saved_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_report_type` (`report_type`);

--
-- Indexes for table `section_items`
--
ALTER TABLE `section_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_section_id` (`section_id`);

--
-- Indexes for table `seo_analytics`
--
ALTER TABLE `seo_analytics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seo_metadata`
--
ALTER TABLE `seo_metadata`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_url` (`page_url`);

--
-- Indexes for table `seo_redirects`
--
ALTER TABLE `seo_redirects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_old_url` (`old_url`);

--
-- Indexes for table `seo_sitemap_queue`
--
ALTER TABLE `seo_sitemap_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `system_health`
--
ALTER TABLE `system_health`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tax_rates`
--
ALTER TABLE `tax_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_dashboards`
--
ALTER TABLE `user_dashboards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_widget` (`user_id`,`widget_id`),
  ADD KEY `widget_id` (`widget_id`);

--
-- Indexes for table `user_events`
--
ALTER TABLE `user_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_visitor_id` (`visitor_id`),
  ADD KEY `idx_start_time` (`start_time`),
  ADD KEY `idx_utm_source` (`utm_source`),
  ADD KEY `idx_converted` (`converted`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ab_tests`
--
ALTER TABLE `ab_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ab_test_results`
--
ALTER TABLE `ab_test_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ab_test_variants`
--
ALTER TABLE `ab_test_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `account_deletion_requests`
--
ALTER TABLE `account_deletion_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `analytics_alerts`
--
ALTER TABLE `analytics_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_analytics`
--
ALTER TABLE `api_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `blog_comments`
--
ALTER TABLE `blog_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `blog_tags`
--
ALTER TABLE `blog_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `client_activity_log`
--
ALTER TABLE `client_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_documents`
--
ALTER TABLE `client_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_messages`
--
ALTER TABLE `client_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `client_notifications`
--
ALTER TABLE `client_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_remember_tokens`
--
ALTER TABLE `client_remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `client_sessions`
--
ALTER TABLE `client_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `client_users`
--
ALTER TABLE `client_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `custom_dimensions`
--
ALTER TABLE `custom_dimensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dashboard_alerts`
--
ALTER TABLE `dashboard_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dashboard_widgets`
--
ALTER TABLE `dashboard_widgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `data_retention_policies`
--
ALTER TABLE `data_retention_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_analytics`
--
ALTER TABLE `email_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `funnel_analytics`
--
ALTER TABLE `funnel_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `funnel_steps`
--
ALTER TABLE `funnel_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `geoip_cache`
--
ALTER TABLE `geoip_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `goal_conversions`
--
ALTER TABLE `goal_conversions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `message_replies`
--
ALTER TABLE `message_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `newsletter_campaigns`
--
ALTER TABLE `newsletter_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter_stats`
--
ALTER TABLE `newsletter_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter_templates`
--
ALTER TABLE `newsletter_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `page_sections`
--
ALTER TABLE `page_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `page_templates`
--
ALTER TABLE `page_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `page_views`
--
ALTER TABLE `page_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `project_activity_log`
--
ALTER TABLE `project_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_documents`
--
ALTER TABLE `project_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_expenses`
--
ALTER TABLE `project_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_invoices`
--
ALTER TABLE `project_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project_messages`
--
ALTER TABLE `project_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_milestones`
--
ALTER TABLE `project_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_tasks`
--
ALTER TABLE `project_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_timeline`
--
ALTER TABLE `project_timeline`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `realtime_analytics`
--
ALTER TABLE `realtime_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recurring_invoices`
--
ALTER TABLE `recurring_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `revenue_tracking`
--
ALTER TABLE `revenue_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saved_reports`
--
ALTER TABLE `saved_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `section_items`
--
ALTER TABLE `section_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seo_analytics`
--
ALTER TABLE `seo_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `seo_metadata`
--
ALTER TABLE `seo_metadata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `seo_redirects`
--
ALTER TABLE `seo_redirects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seo_sitemap_queue`
--
ALTER TABLE `seo_sitemap_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_health`
--
ALTER TABLE `system_health`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_rates`
--
ALTER TABLE `tax_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_dashboards`
--
ALTER TABLE `user_dashboards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `user_events`
--
ALTER TABLE `user_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `message_replies`
--
ALTER TABLE `message_replies`
  ADD CONSTRAINT `fk_message_replies` FOREIGN KEY (`message_id`) REFERENCES `contact_messages` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
