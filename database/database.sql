SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS historia_statusow_wizyt, wizyty, wyjatki_grafiku_lekarza, dostepnosc_lekarza, lekarz_usluga, uslugi, lekarz_placowka, lekarz_specjalizacja, specjalizacje, placowki, profil_lekarza, profil_pacjenta, uzytkownicy;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE uzytkownicy (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    imie VARCHAR(80) NOT NULL,
    nazwisko VARCHAR(80) NOT NULL,
    email VARCHAR(254) NOT NULL,
    haslo VARCHAR(255) NOT NULL,
    rola ENUM('pacjent', 'lekarz', 'administrator') NOT NULL DEFAULT 'pacjent',
    telefon VARCHAR(30) DEFAULT NULL,
    czy_aktywny BOOLEAN NOT NULL DEFAULT TRUE,
    email_zweryfikowany_at DATETIME DEFAULT NULL,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zaktualizowano_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_active (rola, czy_aktywny),
    KEY idx_users_name (nazwisko, imie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profil_pacjenta (
    uzytkownik_id INT UNSIGNED NOT NULL,
    data_urodzenia DATE DEFAULT NULL,
    adres VARCHAR(255) DEFAULT NULL,
    miasto VARCHAR(100) DEFAULT NULL,
    kod_pocztowy VARCHAR(12) DEFAULT NULL,
    kontakt_alarmowy_imie VARCHAR(160) DEFAULT NULL,
    kontakt_alarmowy_telefon VARCHAR(30) DEFAULT NULL,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zaktualizowano_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (uzytkownik_id),
    CONSTRAINT fk_patient_profiles_user
        FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profil_lekarza (
    uzytkownik_id INT UNSIGNED NOT NULL,
    numer_pwz VARCHAR(40) NOT NULL,
    biogram TEXT DEFAULT NULL,
    domyslny_czas_wizyty SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zaktualizowano_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (uzytkownik_id),
    UNIQUE KEY uq_doctor_profiles_license (numer_pwz),
    CONSTRAINT fk_doctor_profiles_user
        FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE placowki (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nazwa VARCHAR(160) NOT NULL,
    telefon VARCHAR(30) DEFAULT NULL,
    email VARCHAR(254) DEFAULT NULL,
    ulica VARCHAR(180) NOT NULL,
    miasto VARCHAR(100) NOT NULL,
    kod_pocztowy VARCHAR(12) NOT NULL,
    czy_aktywny BOOLEAN NOT NULL DEFAULT TRUE,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zaktualizowano_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clinics_name_address (nazwa, ulica, miasto),
    KEY idx_clinics_city_active (miasto, czy_aktywny)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE specjalizacje (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nazwa VARCHAR(100) NOT NULL,
    opis TEXT DEFAULT NULL,
    czy_aktywny BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_specializations_name (nazwa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lekarz_specjalizacja (
    lekarz_id INT UNSIGNED NOT NULL,
    specjalizacja_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (lekarz_id, specjalizacja_id),
    KEY idx_doctor_specialization_specialization (specjalizacja_id, lekarz_id),
    CONSTRAINT fk_doctor_specialization_doctor
        FOREIGN KEY (lekarz_id) REFERENCES profil_lekarza (uzytkownik_id) ON DELETE CASCADE,
    CONSTRAINT fk_doctor_specialization_specialization
        FOREIGN KEY (specjalizacja_id) REFERENCES specjalizacje (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lekarz_placowka (
    lekarz_id INT UNSIGNED NOT NULL,
    placowka_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (lekarz_id, placowka_id),
    KEY idx_doctor_clinics_clinic (placowka_id, lekarz_id),
    CONSTRAINT fk_doctor_clinics_doctor
        FOREIGN KEY (lekarz_id) REFERENCES profil_lekarza (uzytkownik_id) ON DELETE CASCADE,
    CONSTRAINT fk_doctor_clinics_clinic
        FOREIGN KEY (placowka_id) REFERENCES placowki (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE uslugi (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specjalizacja_id INT UNSIGNED NOT NULL,
    nazwa VARCHAR(160) NOT NULL,
    opis TEXT DEFAULT NULL,
    czas_trwania_minut SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    cena DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    czy_aktywny BOOLEAN NOT NULL DEFAULT TRUE,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zaktualizowano_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_specialization_name (specjalizacja_id, nazwa),
    KEY idx_services_active (czy_aktywny, specjalizacja_id),
    CONSTRAINT fk_services_specialization
        FOREIGN KEY (specjalizacja_id) REFERENCES specjalizacje (id) ON DELETE RESTRICT,
    CONSTRAINT chk_services_duration CHECK (czas_trwania_minut > 0),
    CONSTRAINT chk_services_price CHECK (cena >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lekarz_usluga (
    lekarz_id INT UNSIGNED NOT NULL,
    usluga_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (lekarz_id, usluga_id),
    KEY idx_doctor_services_service (usluga_id, lekarz_id),
    CONSTRAINT fk_doctor_services_doctor
        FOREIGN KEY (lekarz_id) REFERENCES profil_lekarza (uzytkownik_id) ON DELETE CASCADE,
    CONSTRAINT fk_doctor_services_service
        FOREIGN KEY (usluga_id) REFERENCES uslugi (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dostepnosc_lekarza (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lekarz_id INT UNSIGNED NOT NULL,
    placowka_id INT UNSIGNED NOT NULL,
    dzien_tygodnia TINYINT UNSIGNED NOT NULL COMMENT 'Dzien tygodnia ISO: poniedzialek=1, niedziela=7',
    godzina_od TIME NOT NULL,
    godzina_do TIME NOT NULL,
    obowiazuje_od DATE DEFAULT NULL,
    obowiazuje_do DATE DEFAULT NULL,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_doctor_availability_period
        (lekarz_id, placowka_id, dzien_tygodnia, godzina_od, godzina_do),
    KEY idx_doctor_availability_lookup (placowka_id, dzien_tygodnia, obowiazuje_od, obowiazuje_do),
    CONSTRAINT fk_doctor_availability_doctor_clinic
        FOREIGN KEY (lekarz_id, placowka_id)
        REFERENCES lekarz_placowka (lekarz_id, placowka_id) ON DELETE CASCADE,
    CONSTRAINT chk_doctor_availability_day CHECK (dzien_tygodnia BETWEEN 1 AND 7),
    CONSTRAINT chk_doctor_availability_time CHECK (godzina_do > godzina_od),
    CONSTRAINT chk_doctor_availability_dates
        CHECK (obowiazuje_do IS NULL OR obowiazuje_od IS NULL OR obowiazuje_do >= obowiazuje_od)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wyjatki_grafiku_lekarza (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lekarz_id INT UNSIGNED NOT NULL,
    placowka_id INT UNSIGNED NOT NULL,
    data_wyjatku DATE NOT NULL,
    typ_wyjatku ENUM('niedostepny', 'dodatkowe_godziny') NOT NULL,
    godzina_od TIME DEFAULT NULL,
    godzina_do TIME DEFAULT NULL,
    powod VARCHAR(255) DEFAULT NULL,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_schedule_exceptions_lookup (lekarz_id, placowka_id, data_wyjatku),
    CONSTRAINT fk_schedule_exceptions_doctor_clinic
        FOREIGN KEY (lekarz_id, placowka_id)
        REFERENCES lekarz_placowka (lekarz_id, placowka_id) ON DELETE CASCADE,
    CONSTRAINT chk_schedule_exceptions_time
        CHECK (
            (godzina_od IS NULL AND godzina_do IS NULL)
            OR (godzina_od IS NOT NULL AND godzina_do IS NOT NULL AND godzina_do > godzina_od)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wizyty (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pacjent_id INT UNSIGNED NOT NULL,
    lekarz_id INT UNSIGNED NOT NULL,
    placowka_id INT UNSIGNED NOT NULL,
    usluga_id INT UNSIGNED NOT NULL,
    data_wizyty DATE NOT NULL,
    godzina_wizyty TIME NOT NULL,
    czas_trwania_minut SMALLINT UNSIGNED NOT NULL,
    cena DECIMAL(8,2) NOT NULL,
    status ENUM('zaplanowana', 'potwierdzona', 'zarejestrowana', 'zakonczona', 'anulowana', 'nieobecnosc')
        NOT NULL DEFAULT 'zaplanowana',
    notatki TEXT DEFAULT NULL,
    powod_anulowania VARCHAR(255) DEFAULT NULL,
    anulowal_uzytkownik_id INT UNSIGNED DEFAULT NULL,
    aktywna_data_wizyty DATE
        GENERATED ALWAYS AS (
            CASE
                WHEN status IN ('zaplanowana', 'potwierdzona', 'zarejestrowana') THEN data_wizyty
                ELSE NULL
            END
        ) STORED,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zaktualizowano_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_appointments_doctor_active_slot
        (lekarz_id, aktywna_data_wizyty, godzina_wizyty),
    UNIQUE KEY uq_appointments_patient_active_slot
        (pacjent_id, aktywna_data_wizyty, godzina_wizyty),
    KEY idx_appointments_patient_history (pacjent_id, data_wizyty, godzina_wizyty),
    KEY idx_appointments_doctor_schedule (lekarz_id, data_wizyty, status),
    KEY idx_appointments_clinic_schedule (placowka_id, data_wizyty, status),
    KEY idx_appointments_service (usluga_id),
    CONSTRAINT fk_appointments_patient
        FOREIGN KEY (pacjent_id) REFERENCES profil_pacjenta (uzytkownik_id) ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_doctor_clinic
        FOREIGN KEY (lekarz_id, placowka_id)
        REFERENCES lekarz_placowka (lekarz_id, placowka_id) ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_doctor_service
        FOREIGN KEY (lekarz_id, usluga_id)
        REFERENCES lekarz_usluga (lekarz_id, usluga_id) ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_cancelled_by
        FOREIGN KEY (anulowal_uzytkownik_id) REFERENCES uzytkownicy (id) ON DELETE SET NULL,
    CONSTRAINT chk_appointments_duration CHECK (czas_trwania_minut > 0),
    CONSTRAINT chk_appointments_price CHECK (cena >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE historia_statusow_wizyt (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    wizyta_id INT UNSIGNED NOT NULL,
    poprzedni_status ENUM('zaplanowana', 'potwierdzona', 'zarejestrowana', 'zakonczona', 'anulowana', 'nieobecnosc')
        DEFAULT NULL,
    nowy_status ENUM('zaplanowana', 'potwierdzona', 'zarejestrowana', 'zakonczona', 'anulowana', 'nieobecnosc')
        NOT NULL,
    zmienil_uzytkownik_id INT UNSIGNED DEFAULT NULL,
    notatka VARCHAR(255) DEFAULT NULL,
    utworzono_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appointment_history (wizyta_id, utworzono_at),
    CONSTRAINT fk_appointment_history_appointment
        FOREIGN KEY (wizyta_id) REFERENCES wizyty (id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_history_user
        FOREIGN KEY (zmienil_uzytkownik_id) REFERENCES uzytkownicy (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO uzytkownicy (imie, nazwisko, email, haslo, rola, telefon) VALUES
    ('Jan', 'Administrator', 'admin@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'administrator', '123456789'),
    ('Piotr', 'Kowalski', 'lekarz@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'lekarz', '987654321'),
    ('Ewa', 'Wisniewska', 'okulista@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'lekarz', '987654322'),
    ('Tomasz', 'Lewandowski', 'pediatra@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'lekarz', '987654323'),
    ('Katarzyna', 'Wojcik', 'rodzinny@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'lekarz', '987654324'),
    ('Marta', 'Kaminska', 'dermatolog@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'lekarz', '987654325'),
    ('Anna', 'Nowak', 'pacjent@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'pacjent', '555666777'),
    ('Maria', 'Zielinska', 'pacjent2@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'pacjent', '555666778'),
    ('Adam', 'Wozniak', 'pacjent3@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'pacjent', '555666779'),
    ('Julia', 'Kaminska', 'pacjent4@pochodnia.pl',
     '$2y$10$OjMQ09b4shKZnsPReuXUbus2AyPyiG/KgsErViT6GRjCcqlHlYy3K', 'pacjent', '555666780');

INSERT IGNORE INTO profil_lekarza (uzytkownik_id, numer_pwz, biogram, domyslny_czas_wizyty)
SELECT id, 'PWZ-000001', 'Specjalista kardiologii. Przyjmuje osoby dorosle.', 30
FROM uzytkownicy WHERE email = 'lekarz@pochodnia.pl' AND rola = 'lekarz';

INSERT IGNORE INTO profil_lekarza (uzytkownik_id, numer_pwz, biogram, domyslny_czas_wizyty)
SELECT id, 'PWZ-000002', 'Specjalista okulistyki.', 30
FROM uzytkownicy WHERE email = 'okulista@pochodnia.pl' AND rola = 'lekarz';

INSERT IGNORE INTO profil_lekarza (uzytkownik_id, numer_pwz, biogram, domyslny_czas_wizyty)
SELECT id, 'PWZ-000003', 'Specjalista pediatrii.', 30
FROM uzytkownicy WHERE email = 'pediatra@pochodnia.pl' AND rola = 'lekarz';

INSERT IGNORE INTO profil_lekarza (uzytkownik_id, numer_pwz, biogram, domyslny_czas_wizyty)
SELECT id, 'PWZ-000004', 'Specjalista medycyny rodzinnej.', 20
FROM uzytkownicy WHERE email = 'rodzinny@pochodnia.pl' AND rola = 'lekarz';

INSERT IGNORE INTO profil_lekarza (uzytkownik_id, numer_pwz, biogram, domyslny_czas_wizyty)
SELECT id, 'PWZ-000005', 'Specjalista dermatologii.', 30
FROM uzytkownicy WHERE email = 'dermatolog@pochodnia.pl' AND rola = 'lekarz';

INSERT IGNORE INTO profil_pacjenta (uzytkownik_id, data_urodzenia)
SELECT id, '1990-05-12' FROM uzytkownicy WHERE email = 'pacjent@pochodnia.pl' AND rola = 'pacjent';

INSERT IGNORE INTO profil_pacjenta (uzytkownik_id, data_urodzenia)
SELECT id, '1985-09-23' FROM uzytkownicy WHERE email = 'pacjent2@pochodnia.pl' AND rola = 'pacjent';

INSERT IGNORE INTO profil_pacjenta (uzytkownik_id, data_urodzenia)
SELECT id, '2001-02-14' FROM uzytkownicy WHERE email = 'pacjent3@pochodnia.pl' AND rola = 'pacjent';

INSERT IGNORE INTO profil_pacjenta (uzytkownik_id, data_urodzenia)
SELECT id, '1995-11-08' FROM uzytkownicy WHERE email = 'pacjent4@pochodnia.pl' AND rola = 'pacjent';

INSERT IGNORE INTO placowki (nazwa, telefon, email, ulica, miasto, kod_pocztowy)
VALUES ('Przychodnia Pochodnia - Centrum', '221234567', 'kontakt@pochodnia.pl',
        'ul. Zdrowa 10', 'Warszawa', '00-001');

INSERT IGNORE INTO specjalizacje (nazwa, opis) VALUES
    ('Kardiologia', 'Diagnostyka i leczenie chorob serca i ukladu krazenia'),
    ('Okulistyka', 'Badanie wzroku i leczenie chorob oczu'),
    ('Pediatria', 'Profilaktyka i leczenie dzieci'),
    ('Medycyna rodzinna', 'Podstawowa opieka zdrowotna dla calej rodziny'),
    ('Dermatologia', 'Diagnostyka i leczenie chorob skory');

INSERT IGNORE INTO lekarz_specjalizacja (lekarz_id, specjalizacja_id)
SELECT u.id, s.id FROM uzytkownicy u
JOIN specjalizacje s ON s.nazwa = 'Kardiologia'
WHERE u.email = 'lekarz@pochodnia.pl' AND u.rola = 'lekarz';

INSERT IGNORE INTO lekarz_specjalizacja (lekarz_id, specjalizacja_id)
SELECT u.id, s.id FROM uzytkownicy u
JOIN specjalizacje s ON s.nazwa = 'Okulistyka'
WHERE u.email = 'okulista@pochodnia.pl' AND u.rola = 'lekarz';

INSERT IGNORE INTO lekarz_specjalizacja (lekarz_id, specjalizacja_id)
SELECT u.id, s.id FROM uzytkownicy u
JOIN specjalizacje s ON s.nazwa = 'Pediatria'
WHERE u.email = 'pediatra@pochodnia.pl' AND u.rola = 'lekarz';

INSERT IGNORE INTO lekarz_specjalizacja (lekarz_id, specjalizacja_id)
SELECT u.id, s.id FROM uzytkownicy u
JOIN specjalizacje s ON s.nazwa = 'Medycyna rodzinna'
WHERE u.email = 'rodzinny@pochodnia.pl' AND u.rola = 'lekarz';

INSERT IGNORE INTO lekarz_specjalizacja (lekarz_id, specjalizacja_id)
SELECT u.id, s.id FROM uzytkownicy u
JOIN specjalizacje s ON s.nazwa = 'Dermatologia'
WHERE u.email = 'dermatolog@pochodnia.pl' AND u.rola = 'lekarz';

INSERT IGNORE INTO lekarz_placowka (lekarz_id, placowka_id)
SELECT u.id, c.id FROM uzytkownicy u CROSS JOIN placowki c
WHERE u.email IN (
    'lekarz@pochodnia.pl', 'okulista@pochodnia.pl', 'pediatra@pochodnia.pl',
    'rodzinny@pochodnia.pl', 'dermatolog@pochodnia.pl'
)
  AND u.rola = 'lekarz' AND c.nazwa = 'Przychodnia Pochodnia - Centrum';

INSERT IGNORE INTO uslugi (specjalizacja_id, nazwa, opis, czas_trwania_minut, cena)
SELECT id, 'Konsultacja kardiologiczna', 'Pierwsza konsultacja kardiologiczna', 30, 180.00
FROM specjalizacje WHERE nazwa = 'Kardiologia';

INSERT IGNORE INTO uslugi (specjalizacja_id, nazwa, opis, czas_trwania_minut, cena)
SELECT id, 'Kontrola kardiologiczna', 'Wizyta kontrolna u kardiologa', 20, 130.00
FROM specjalizacje WHERE nazwa = 'Kardiologia';

INSERT IGNORE INTO uslugi (specjalizacja_id, nazwa, opis, czas_trwania_minut, cena)
SELECT id, 'Konsultacja okulistyczna', 'Badanie okulistyczne', 30, 160.00
FROM specjalizacje WHERE nazwa = 'Okulistyka';

INSERT IGNORE INTO uslugi (specjalizacja_id, nazwa, opis, czas_trwania_minut, cena)
SELECT id, 'Konsultacja pediatryczna', 'Konsultacja lekarska dziecka', 30, 150.00
FROM specjalizacje WHERE nazwa = 'Pediatria';

INSERT IGNORE INTO uslugi (specjalizacja_id, nazwa, opis, czas_trwania_minut, cena)
SELECT id, 'Konsultacja lekarza rodzinnego', 'Konsultacja w ramach podstawowej opieki', 20, 120.00
FROM specjalizacje WHERE nazwa = 'Medycyna rodzinna';

INSERT IGNORE INTO uslugi (specjalizacja_id, nazwa, opis, czas_trwania_minut, cena)
SELECT id, 'Konsultacja dermatologiczna', 'Konsultacja dermatologiczna', 30, 170.00
FROM specjalizacje WHERE nazwa = 'Dermatologia';

INSERT IGNORE INTO lekarz_usluga (lekarz_id, usluga_id)
SELECT ds.lekarz_id, s.id
FROM lekarz_specjalizacja ds
JOIN uslugi s ON s.specjalizacja_id = ds.specjalizacja_id;

INSERT IGNORE INTO dostepnosc_lekarza
    (lekarz_id, placowka_id, dzien_tygodnia, godzina_od, godzina_do)
SELECT u.id, c.id, days.dzien_tygodnia, '08:00:00', '16:00:00'
FROM uzytkownicy u
CROSS JOIN placowki c
CROSS JOIN (
    SELECT 1 AS dzien_tygodnia UNION ALL SELECT 2 UNION ALL SELECT 3
    UNION ALL SELECT 4 UNION ALL SELECT 5
) AS days
WHERE u.email IN (
    'lekarz@pochodnia.pl', 'okulista@pochodnia.pl', 'pediatra@pochodnia.pl',
    'rodzinny@pochodnia.pl', 'dermatolog@pochodnia.pl'
)
  AND u.rola = 'lekarz' AND c.nazwa = 'Przychodnia Pochodnia - Centrum';