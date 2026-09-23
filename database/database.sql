-- =============================================
-- BAZA DANYCH DLA RAILWAY.APP
-- Baza nazywa się "railway" (tak jak pokazuje SELECT DATABASE())
-- =============================================

USE railway;

-- Usuwamy istniejące tabele (żeby nie było konfliktów przy ponownym imporcie)
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS doctor_availability;
DROP TABLE IF EXISTS doctor_specialization;
DROP TABLE IF EXISTS specializations;
DROP TABLE IF EXISTS users;

-- Tworzymy tabele
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
    updated_at      DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE specializations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80)     NOT NULL UNIQUE,
    description     TEXT,
    created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE doctor_specialization (
    doctor_id       INT NOT NULL,
    specialization_id INT NOT NULL,
    PRIMARY KEY (doctor_id, specialization_id),
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE
);

CREATE TABLE doctor_availability (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id       INT NOT NULL,
    day_of_week     TINYINT(1) NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE appointments (
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
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id),
    FOREIGN KEY (specialization_id) REFERENCES specializations(id)
);

-- Dane testowe
INSERT INTO users (first_name, last_name, email, password, phone, role) VALUES
('Administrator', 'Pochodnia', 'admin@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123456789', 'admin'),
('Jan', 'Kowalski', 'dr.kowalski@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600111222', 'doctor'),
('Anna', 'Nowak', 'dr.nowak@pochodnia.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600333444', 'doctor'),
('Piotr', 'Wiśniewski', 'piotr.wisniewski@email.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '700555666', 'patient');

INSERT INTO specializations (name, description) VALUES
('Kardiologia', 'Choroby serca i układu krążenia'),
('Pediatria', 'Lekarz dziecięcy'),
('Internista', 'Choroby wewnętrzne'),
('Laryngologia', 'Choroby uszu, nosa i gardła');

SELECT '✅ Baza danych została pomyślnie utworzona w bazie "railway"!' as status;