# System rezerwacji wizyt lekarskich

### Autorzy
- Sergiusz Wojczuk, klasa 5KT
- Jakub Goławski, klasa 5KT

### Opis
Projekt systemu przychodni z kontami pacjentów, personelem, placówkami,
specjalizacjami, usługami i modelem wizyt. Aplikacja udostępnia rejestrację,
logowanie, profile, zmianę hasła, panele ról, listę kont administratorowi oraz
podgląd grafiku pracownika. Internetowy moduł rezerwowania i zarządzania
wizytami nie jest jeszcze dostępny w interfejsie.

### Technologie
- PHP 8.1+ i PDO
- MySQL 8.0.16+ lub zgodna wersja MariaDB, silnik InnoDB
- HTML, CSS i JavaScript
- Git i GitHub

Role w bazie to `pacjent`, `lekarz` i `administrator`. Dostęp do paneli jest
weryfikowany po stronie PHP.

### Baza danych
Pełny skrypt instalacyjny i dane demonstracyjne znajdują się w
[`database/database.sql`](database/database.sql). Model logiczny DBML znajduje
się w [`database/baza_przychodni.dbml`](database/baza_przychodni.dbml), a
diagram ERD w [`docs/erd-diagram.png`](docs/erd-diagram.png). DBML można
otworzyć w [dbdiagram.io](https://dbdiagram.io).

Schemat obejmuje 14 tabel: użytkowników i profile ról, placówki,
specjalizacje, usługi, przypisania lekarzy, dostępność, wyjątki grafiku, wizyty
i historię ich statusów. Zawiera klucze obce, relacje wiele-do-wielu, unikalny
e-mail, indeksy wyszukiwania oraz ograniczenia integralności. Wizyta zachowuje
cenę i czas trwania z chwili rezerwacji. Triggery SQL automatycznie zapisują
utworzenie wizyty i zmianę jej statusu. Aby przypisać zmianę do użytkownika,
aplikacja powinna ustawić `@aktor_uzytkownik_id` na tym samym połączeniu PDO
przed zapisem.

Dostępność cykliczna może mieć zakres dat, a wyjątki grafiku obsługują dzień
niedostępności lub dodatkowy przedział godzin. Indeksy blokują dwie aktywne
wizyty tego samego pacjenta lub lekarza o identycznej godzinie rozpoczęcia.
Nie wykrywają nakładających się przedziałów, więc przyszły moduł rezerwacji
musi sprawdzać kolizje w transakcji.

Skrypt można uruchamiać ponownie: tworzy brakujące tabele i uzupełnia dane
demonstracyjne bez usuwania danych. Nie aktualizuje struktury już istniejących
tabel i nie zastępuje migracji. Do instalacji na działającej bazie przygotuj
osobną migrację oraz kopię zapasową.

### Uruchomienie
Wymagane: PHP 8.1+ z rozszerzeniem `pdo_mysql`, dostępny serwer MySQL/MariaDB
oraz wcześniej utworzona baza danych. Ustaw dane połączenia:

```sh
export MYSQLHOST="127.0.0.1"
export MYSQLPORT="3306"
export MYSQLUSER="twoj_uzytkownik"
export MYSQLPASSWORD="twoje_haslo"
export MYSQLDATABASE="pochodnia"
php public/setup-db.php
php public/test-db.php
```

Instalator i diagnostyka działają wyłącznie z terminala. Użytkownik bazy musi
mieć uprawnienia do tworzenia tabel, indeksów i triggerów.

### Konta demonstracyjne
Hasło dla wszystkich poniższych kont: `demo1234`.

- `admin@pochodnia.pl` — administrator
- `lekarz@pochodnia.pl`, `okulista@pochodnia.pl`, `pediatra@pochodnia.pl`, `rodzinny@pochodnia.pl`, `dermatolog@pochodnia.pl` — lekarze
- `pacjent@pochodnia.pl`, `pacjent2@pochodnia.pl`, `pacjent3@pochodnia.pl`, `pacjent4@pochodnia.pl` — pacjenci

Nie używaj kont demonstracyjnych produkcyjnie. Zmień hasło administratora po
pierwszym logowaniu.