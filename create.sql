CREATE DATABASE `mintyphp_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'mintyphp_test'@'localhost' IDENTIFIED BY 'mintyphp_test';
GRANT ALL PRIVILEGES ON `mintyphp_test`.* TO 'mintyphp_test'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
