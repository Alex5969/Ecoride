CREATE DATABASE IF NOT EXISTS ecoride_bdd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ecoride_bdd;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('traveler', 'driver', 'employee', 'admin', 'suspended') NOT NULL,
    credits DECIMAL(10, 2) DEFAULT 20.00,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    address VARCHAR(255),
    birth_date DATE,
    profile_picture VARCHAR(255) DEFAULT 'https://placehold.co/100x100/A0D6A7/2E7D32?text=PP'
);

CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    registration_date DATE NOT NULL,
    color VARCHAR(30),
    energy ENUM('petrol', 'diesel', 'electric', 'hybrid') NOT NULL,
    seats_available INT NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS driver_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    smoking_allowed BOOLEAN DEFAULT FALSE,
    animals_allowed BOOLEAN DEFAULT FALSE,
    custom_preference TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    vehicle_id INT,
    departure_city VARCHAR(100) NOT NULL,
    arrival_city VARCHAR(100) NOT NULL,
    departure_datetime DATETIME NOT NULL,
    available_seats INT NOT NULL,
    price DECIMAL(8, 2) NOT NULL,
    description TEXT,
    trip_status ENUM('scheduled', 'started', 'completed', 'cancelled') DEFAULT 'scheduled',
    platform_fee DECIMAL(8, 2) DEFAULT 2.00,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    traveler_id INT NOT NULL,
    number_of_seats INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'pending_validation', 'completed') DEFAULT 'pending',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (traveler_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    driver_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS issue_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    reporter_id INT NOT NULL,
    issue_description TEXT NOT NULL,
    report_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'in_review', 'resolved') DEFAULT 'new',
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Ajout administrateur avec mot de passe en clair pour l'examen
INSERT INTO utilisateurs (pseudo, email, mot_de_passe, role, est_verifie)
VALUES ('admin_exam', 'admin@ecoride.com', 'admin_password_123', 'administrateur', TRUE);