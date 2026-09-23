SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS doctor_availability;
DROP TABLE IF EXISTS doctor_specialization;
DROP TABLE IF EXISTS specializations;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Tabela Użytkowników
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient', 'doctor', 'admin') DEFAULT 'patient',
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabela Specjalizacji
CREATE TABLE specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

-- 3. Łącznik Lekarz - Specjalizacja
CREATE TABLE doctor_specialization (
    doctor_id INT NOT NULL,
    specialization_id INT NOT NULL,
    PRIMARY KEY (doctor_id, specialization_id),
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE
);

-- 4. Dostępność Lekarza (Grafik)
CREATE TABLE doctor_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Tabela Wizyt
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Dane testowe (Hasło dla wszystkich kont to: haslo123)
INSERT INTO users (first_name, last_name, email, password, role, phone) VALUES
('Jan', 'Administrator', 'admin@pochodnia.pl', '$2y$10$eA.X/F5M7E/j5q0Xf4tM9eCqYQ8gJ2m3g1QyP1pXQeX.W9GzG6K1S', 'admin', '123456789'),
('Piotr', 'Kardiolog', 'lekarz@pochodnia.pl', '$2y$10$eA.X/F5M7E/j5q0Xf4tM9eCqYQ8gJ2m3g1QyP1pXQeX.W9GzG6K1S', 'doctor', '987654321'),
('Anna', 'Nowak', 'pacjent@pochodnia.pl', '$2y$10$eA.X/F5M7E/j5q0Xf4tM9eCqYQ8gJ2m3g1QyP1pXQeX.W9GzG6K1S', 'patient', '555666777');

INSERT INTO specializations (name, description) VALUES
('Kardiologia', 'Choroby serca i układu krążenia'),
('Okulistyka', 'Wzrok i leczenie wad oczu'),
('Pediatria', 'Leczenie dzieci');

INSERT INTO doctor_specialization (doctor_id, specialization_id) VALUES (2, 1);