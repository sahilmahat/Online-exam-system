-- Default master admin.  Login: admin@example.com / Admin@12345   (change the password after first login!)
INSERT INTO `admin_table`
  (`admin_email_address`, `admin_password`, `admin_verfication_code`, `admin_type`, `admin_created_on`, `email_verified`)
VALUES
  ('admin@example.com', '$2y$10$49hn1.UqiIZTyvUF/3UddOpq4gxYLh/vEz0xUgSvTDcqeJanMpCim', '', 'master', NOW(), 'yes');
