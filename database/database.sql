-- =============================================
-- BAZA DANYCH: Przychodnia Pochodnia (dla Railway)
-- Railway tworzy bazę o nazwie 'railway'
-- =============================================

USE railway;

-- =============================================
-- Tabela użytkowników
-- =============================================
CREATE TABLE IF NOT EXISTS users (
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
    updated_at      DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Specjalizacje
-- =============================================
CREATE TABLE IF NOT EXISTS specializations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80)     NOT NULL UNIQUE,
    description     TEXT,
    created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Relacja lekarz - specjalizacja
-- =============================================
CREATE TABLE IF NOT EXISTS doctor_specialization (
    doctor_id       INT NOT NULL,
    specialization_id INT NOT NULL,
    PRIMARY KEY (doctor_id, specialization_id),
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- Grafik lekarzy
-- =============================================
CREATE TABLE IF NOT EXISTS doctor_availability (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id       INT NOT NULL,
    day_of_week     TINYINT(1) NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- Wizyty
-- =============================================
CREATE TABLE IF NOT EXISTS appointments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    patient_id          INT NOT NULL,
    doctor_id           INT NOT NULL,
    specialization_id   INT NOT NULL,
    appointment_date    DATE NOT NULL,
    start_time          TIME NOT NULL,
    end_time            TIME NOT NULL,
    status              ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    comment             TEXT,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)      REFERENCES users(id),
    FOREIGN KEY (doctor_id)       REFERENCES users(id),
    FOREIGN KEY (specialization_id) REFERENCES specializations(id)
) ENGINE=InnoDB;

-- =============================================
-- DANE TESTOWE
-- =============================================
INSERT IGNORE INTO users (first_name, last_name, email, password, phone, role) VALUES
('Administrator', 'Pochodnia', 'admin@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123456789', 'admin'),
('Jan', 'Kowalski', 'dr.kowalski@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600111222', 'doctor'),
('Anna', 'Nowak', 'dr.nowak@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600333444', 'doctor'),
('Piotr', 'Wiśniewski', 'piotr.wisniewski@email.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '700555666', 'patient');

INSERT IGNORE INTO specializations (name, description) VALUES
('Kardiologia', 'Choroby serca'),
('Pediatria', 'Lekarz dziecięcy'),
('Internista', 'Choroby wewnętrzne'),
('Laryngologia', 'Uszy, nos, gardło');

-- =============================================
-- Indeksy
-- =============================================
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date);

SELECT '✅ Baza Przychodnia Pochodnia została pomyślnie zaimportowana!' as info;