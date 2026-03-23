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
