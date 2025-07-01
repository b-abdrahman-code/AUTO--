# Garage Management System | AUTO-يقظة

A  Garage and Car Repair Shop Management System built with PHP and MySQL. named `AUTO-يقظة`  is designed to streamline operations for mechanics, administrators, and clients, providing digital interface for managing repairs, clients, inventory, and invoices... in a basic way.... just did this project for a friend and i never used php before...

---

## ✨ Key Features

The system offers three distinct user roles: **Admin**, **Mechanic**, and **Client (User)**, each with a tailored dashboard and specific permissions.

### 👤 **Admin Features:**
- **📊 Global Dashboard:** At-a-glance view of active repairs, pending requests, completed jobs, unpaid invoices, and monthly revenue.
- **👥 Client Management:** Full CRUD (Create, Read, Update, Delete) functionality for clients. View detailed client profiles with their associated vehicles and repair history.
- **🚗 Vehicle Management:** Add, edit, or delete vehicles for any client.
- **🛠️ Repair Order Management:**
    - Create new repair orders.
    - Assign or re-assign mechanics to pending repairs.
    - View all repairs across the system with powerful filter tabs (All, Pending, In Progress, Completed).
    - Add special services like a car wash to a repair order.
- **📦 Inventory Control:**
    - Manage parts inventory with full CRUD operations.
    - Track stock levels with low-stock alerts and status indicators.
    - Adjust stock quantities manually.
- **📄 Invoice Generation:**
    - Automatically generate detailed, professional invoices from completed repairs.
    - Print invoices for clients.
    - Manage invoice statuses (Unpaid, Paid, Overdue).
    - Record payments with different methods.

### 🛠️ **Mechanic Features:**
- **📋 Personalized Dashboard:** Shows a summary of assigned tasks (pending and in-progress).
- **🔧 Repair Execution:**
    - View a list of all assigned repairs.
    - Start a repair, changing its status from `Pending` to `In Progress`.
    - Add labor tasks and associated costs to a repair.
    - Add parts from inventory to a repair, automatically decrementing stock.
    - Mark repairs as `Completed`.

### 🚗 **Client Features:**
- **🏠 Simplified Dashboard:** View personal vehicle count, active repairs, and total spending.
- **🚘 My Garage:**
    - View a list of all personal vehicles registered with the garage.
    - Add new vehicles to their profile.
- **🙋 Request a Repair:** Submit a new repair request for one of their vehicles by describing the problem.
- **📜 Repair History:** View the status and history of all their repairs.

---

## 💻 Technology Stack

- **Backend:** PHP (Procedural)
- **Database:** MySQL
- **Frontend:** HTML5, Bootstrap 5, Vanilla JavaScript, Font Awesome

---

## 🚀 Getting Started

Follow these instructions to get a local copy up and running for development and testing purposes.

### Prerequisites

You need a local web server environment. XAMPP is a great option as it includes Apache, MySQL, and PHP.
- [Download XAMPP](https://www.apachefriends.org/index.html)

### Installation & Setup

1.  **Clone the repository** to your web server's root directory (`htdocs` in XAMPP):
    ```sh
    git clone https://github.com/your-username/your-repo-name.git
    ```

2.  **Start Apache and MySQL** from your XAMPP control panel.

3.  **Create the Database:**
    - Open your browser and go to `http://localhost/phpmyadmin`.
    - Create a new database named `garage_management2`.

4.  **Import the SQL Schema:**
    - Select the `garage_management2` database you just created.
    - Click on the `Import` tab.
    - Upload the `database.sql` file provided in this repository and click `Go`. This will create all the necessary tables and add sample data.

5.  **Configure the Database Connection:**
    - Open the `config.php` file.
    - Update the database credentials to match your local setup. The default for XAMPP is usually `root` with no password.
    ```php
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'garage_management2');
    define('DB_USER', 'root');
    define('DB_PASS', ''); // Default XAMPP password is empty
    ```
    -btw this is the database sql script:
   ```sql
    --
-- Database: `garage_management2`
--
CREATE DATABASE IF NOT EXISTS `garage_management2` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `garage_management2`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','mechanic','user') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `role`, `full_name`) VALUES
(1, 'admin', '$2y$10$wEFLpL81JV22b39QnF1CzeO5qA1YkG5P.YkR1m.N/gI4b4w6Q71B.', 'admin', 'Admin User'),
(2, 'mechanic1', '$2y$10$2H0QjS0G7c4/1dJ1g4f9j.XzE8r2rQp1Z/t9Nq1b1bXp6n0n1L9zO', 'mechanic', 'Bob the Builder'),
(3, 'client1', '$2y$10$kP7p2lY3mO5u.g.p0F.b3.E6L7z8w4rR9m5V6C8d1A3f5g1H1I2j4', 'user', 'John Doe');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`client_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `name`, `phone`, `address`, `user_id`, `created_at`) VALUES
(1, 'John Doe', '123-456-7890', '123 Main St, Anytown, USA', 3, '2023-10-27 10:00:00'),
(2, 'Jane Smith', '987-654-3210', '456 Oak Ave, Otherville, USA', NULL, '2023-10-27 11:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `mechanics`
--

CREATE TABLE `mechanics` (
  `mechanic_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `hire_date` date NOT NULL,
  PRIMARY KEY (`mechanic_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `mechanics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mechanics`
--

INSERT INTO `mechanics` (`mechanic_id`, `name`, `user_id`, `hire_date`) VALUES
(1, 'Bob the Builder', 2, '2022-01-15');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL AUTO_INCREMENT,
  `license_plate` varchar(20) NOT NULL,
  `type` varchar(50) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` year(4) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `license_plate` (`license_plate`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `license_plate`, `type`, `brand`, `model`, `year`, `client_id`, `created_at`) VALUES
(1, 'ABC-123', 'Car', 'Toyota', 'Camry', '2020', 1, '2023-10-27 10:05:00'),
(2, 'XYZ-789', 'SUV', 'Ford', 'Explorer', '2021', 2, '2023-10-27 11:05:00');

-- --------------------------------------------------------

--
-- Table structure for table `repairs`
--

CREATE TABLE `repairs` (
  `repair_id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `mechanic_id` int(11) DEFAULT NULL,
  `problem_description` text NOT NULL,
  `start_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wash_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`repair_id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `mechanic_id` (`mechanic_id`),
  CONSTRAINT `repairs_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  CONSTRAINT `repairs_ibfk_2` FOREIGN KEY (`mechanic_id`) REFERENCES `mechanics` (`mechanic_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repairs`
--
INSERT INTO `repairs` (`repair_id`, `vehicle_id`, `mechanic_id`, `problem_description`, `start_date`, `completion_date`, `status`, `total_cost`) VALUES
(1, 1, 1, 'Engine making a strange noise and check engine light is on.', '2023-10-27', NULL, 'In Progress', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `repair_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `issue_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_status` enum('Unpaid','Paid','Overdue') NOT NULL DEFAULT 'Unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`invoice_id`),
  KEY `repair_id` (`repair_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`repair_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parts`
--

CREATE TABLE `parts` (
  `part_id` int(11) NOT NULL AUTO_INCREMENT,
  `part_reference` varchar(50) NOT NULL,
  `designation` varchar(255) NOT NULL,
  `quantity_in_stock` int(11) NOT NULL DEFAULT 0,
  `price_per_unit` decimal(10,2) NOT NULL,
  `min_stock_level` int(11) NOT NULL DEFAULT 5,
  PRIMARY KEY (`part_id`),
  UNIQUE KEY `part_reference` (`part_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parts`
--
INSERT INTO `parts` (`part_id`, `part_reference`, `designation`, `quantity_in_stock`, `price_per_unit`, `min_stock_level`) VALUES
(1, 'OF-001', 'Oil Filter', 50, 15.50, 10),
(2, 'SP-004', 'Spark Plug - NGK', 100, 8.75, 20);

-- --------------------------------------------------------

--
-- Table structure for table `repair_parts`
--

CREATE TABLE `repair_parts` (
  `repair_part_id` int(11) NOT NULL AUTO_INCREMENT,
  `repair_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `quantity_used` int(11) NOT NULL,
  `price_at_time_of_use` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`repair_part_id`),
  UNIQUE KEY `repair_id_part_id` (`repair_id`, `part_id`),
  KEY `part_id` (`part_id`),
  CONSTRAINT `repair_parts_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`repair_id`) ON DELETE CASCADE,
  CONSTRAINT `repair_parts_ibfk_2` FOREIGN KEY (`part_id`) REFERENCES `parts` (`part_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `repair_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `duration` decimal(5,2) DEFAULT NULL, -- Duration in hours
  `cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`task_id`),
  KEY `repair_id` (`repair_id`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`repair_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
   ```


---

## 🕹️ Usage & Demo Credentials

You can log in using one of the pre-configured accounts to test the different user roles:

| Role     | Username  | Password |
| :------- | :-------- | :------- |
| **Admin**  | `admin`     | `password` |
| **Mechanic** | `mechanic1` | `password` |
| **Client**   | `client1`   | `password` |

You can also register new accounts for `Mechanic` and `Client` roles directly from the login page. Admin registration is disabled for security.

---

## 🗺️ Future Enhancements

- **Move to MVC:** Refactor the codebase to follow a modern MVC (Model-View-Controller) pattern using a framework like Laravel or Symfony for better scalability and maintainability.
- **REST API:** Develop a RESTful API to decouple the backend from the frontend, allowing for native mobile apps or modern JavaScript frameworks.
- **Enhanced Reporting:** Add a dedicated reporting module with graphs and data exports (PDF, CSV).
- **Notifications:** Implement email or real-time notifications for repair status updates, invoices, etc.
- **Unit & Integration Testing:** Add a robust testing suite to ensure code quality and reliability.

---

## 🤝 Contributing

Contributions are welcome! Please feel free to fork the repository, make improvements, and submit a pull request.

1.  Fork the Project.
2.  Create your Feature Branch (`git checkout -b feature/AmazingFeature`).
3.  Commit your Changes (`git commit -m 'Add some AmazingFeature'`).
4.  Push to the Branch (`git push origin feature/AmazingFeature`).
5.  Open a Pull Request.

---

## 📄 License

This project is licensed under the MIT License. See the `LICENSE` file for details.
