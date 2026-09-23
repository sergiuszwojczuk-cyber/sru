-- =============================================
-- BAZA DANYCH: Przychodnia Pochodnia
-- Etap 1 - Projekt systemu, GitHub i baza danych
-- Klasa 5 Technikum Informatycznego
-- Data: 17.09.2026
-- =============================================

CREATE DATABASE IF NOT EXISTS przychodnia_pochodnia 
CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
USE przychodnia_pochodnia;

-- =============================================
-- Tabela użytkowników (wszystkie role w jednej tabeli)
-- =============================================
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)     NOT NULL,
    last_name       VARCHAR(50)     NOT NULL,
    email           VARCHAR(100)    NOT NULL UNIQUE,
    password        VARCHAR(255)    NOT NULL,
    phone           VARCHAR(15),
    pesel           VARCHAR(11)     UNIQUE,
    role            ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
    is_active       TINYINT(1)      DEFAULT 1,
    created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_role (role)
) ENGINE=InnoDB COMMENT='Tabela użytkowników - pacjenci, lekarze i administrator';

-- =============================================
-- Specjalizacje lekarskie
-- =============================================
CREATE TABLE specializations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80)     NOT NULL UNIQUE,
    description     TEXT,
    created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Specjalizacje medyczne';

-- =============================================
-- Relacja N:M - który lekarz ma jakie specjalizacje
-- =============================================
CREATE TABLE doctor_specialization (
    doctor_id       INT NOT NULL,
    specialization_id INT NOT NULL,
    PRIMARY KEY (doctor_id, specialization_id),
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Przypisanie lekarzy do specjalizacji';

-- =============================================
-- Dostępność / grafik lekarzy
-- =============================================
CREATE TABLE doctor_availability (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id       INT NOT NULL,
    day_of_week     TINYINT(1) NOT NULL COMMENT '0=niedziela, 1=poniedziałek...',
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Grafik pracy lekarzy';

-- =============================================
-- Wizyty / rezerwacje
-- =============================================
CREATE TABLE appointments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    patient_id          INT NOT NULL,
    doctor_id           INT NOT NULL,
    specialization_id   INT NOT NULL,
    appointment_date    DATE NOT NULL,
    start_time          TIME NOT NULL,
    end_time            TIME NOT NULL,
    status              ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show') 
                        DEFAULT 'pending',
    comment             TEXT,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id)      REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (doctor_id)       REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_appointments_date (appointment_date),
    INDEX idx_appointments_patient (patient_id),
    INDEX idx_appointments_doctor (doctor_id)
) ENGINE=InnoDB COMMENT='Rezerwacje wizyt lekarskich';

-- =============================================
-- WSTAWIANIE DANYCH TESTOWYCH
-- =============================================

-- Użytkownicy
INSERT INTO users (first_name, last_name, email, password, phone, role) VALUES
('Administrator', 'Pochodnia', 'admin@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123456789', 'admin'),
('Jan', 'Kowalski', 'dr.kowalski@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600111222', 'doctor'),
('Anna', 'Nowak', 'dr.nowak@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600333444', 'doctor'),
('Piotr', 'Wiśniewski', 'piotr.wisniewski@email.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '700555666', 'patient'),
('Katarzyna', 'Lewandowska', 'kasia.lewandowska@email.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '700777888', 'patient');

-- Specjalizacje
INSERT INTO specializations (name, description) VALUES
('Kardiologia', 'Choroby serca i układu krążenia'),
('Pediatria', 'Lekarz dziecięcy'),
('Internista', 'Lekarz chorób wewnętrznych'),
('Laryngologia', 'Choroby uszu, nosa i gardła'),
('Dermatologia', 'Choroby skóry'),
('Okulistyka', 'Choroby oczu'),
('Ortopedia', 'Choroby układu ruchu');

-- Przypisanie specjalizacji do lekarzy
INSERT INTO doctor_specialization (doctor_id, specialization_id) VALUES
(2, 1), (2, 3),        -- dr Kowalski - Kardiologia + Internista
(3, 2), (3, 4);        -- dr Nowak - Pediatria + Laryngologia

-- Grafik lekarzy
INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time) VALUES
(2, 1, '08:00:00', '16:00:00'),   -- Poniedziałek - dr Kowalski
(2, 2, '09:00:00', '17:00:00'),   -- Wtorek - dr Kowalski
(3, 1, '10:00:00', '18:00:00'),   -- Poniedziałek - dr Nowak
(3, 3, '08:00:00', '15:00:00');   -- Środa - dr Nowak

-- Przykładowe wizyty
INSERT INTO appointments (patient_id, doctor_id, specialization_id, appointment_date, start_time, end_time, status, comment) VALUES
(4, 2, 1, '2026-09-22', '09:00:00', '09:30:00', 'confirmed', 'Bóle w klatce piersiowej, kontrola EKG'),
(5, 3, 2, '2026-09-23', '11:00:00', '11:20:00', 'pending', 'Kontrola dziecka - kaszel i gorączka');

-- =============================================
-- Dodatkowe indeksy dla wydajności
-- =============================================
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_doctor_availability_doctor ON doctor_availability(doctor_id);