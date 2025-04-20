-- create database time_tracking;
USE time_tracking;


CREATE TABLE employees (
    employee_id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE time_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50),
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);

CREATE TABLE admins (
    admin_id VARCHAR(50) PRIMARY KEY,
    password VARCHAR(255) NOT NULL
);

-- Sample data
INSERT INTO employees (employee_id, name) VALUES
('1', 'John Doe'),
('2', 'Jane Smith'),
('3', 'claw kabongo');

INSERT INTO admins (admin_id, password) VALUES
('claw', '$2y$10$G6aYMZEFcblbutj/OfGmGeasjuHrDDzHrgH1kgfBDW.rWz18yIPdG'); 
