
CREATE DATABASE IF NOT EXISTS fbtest;
USE fbtest;


DROP TABLE IF EXISTS users;
CREATE TABLE users (
                       user_id INT AUTO_INCREMENT PRIMARY KEY,
                       name VARCHAR(255) NOT NULL,
                       email VARCHAR(255),
                       block TINYINT(1) DEFAULT 0
);


INSERT INTO users (name, email, block) VALUES
                                           ('Alice', 'alice@example.com', 0),
                                           ('Bob', 'bob@example.com', 0),
                                           ('Charlie', 'charlie@example.com', 1);

