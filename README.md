## Przychodnia Pochodnia - System Rezerwacji Wizyt Lekarskich

## Autorzy
- Sergiusz Wojczuk 5kt
- Jakub Goławski 5kt

## O projekcie

## Technologie

- **Backend:** PHP 8 + PDO
- **Baza danych:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript
- **Wersjonowanie:** Git + GitHub
- **Dodatkowo:** Bootstrap 5 (planowane), FullCalendar (planowane)

## Role użytkowników

- **Klient (pacjent)** – rejestracja, logowanie, edycja własnego profilu i zmiana hasła
- **Pracownik (lekarz)** – dostęp do własnego grafiku dostępności i edycja własnego profilu
- **Administrator** – dostęp do panelu administracyjnego i listy kont użytkowników

Nazwy ról przechowywane w bazie (`pacjent`, `lekarz`, `administrator`) pozostają
zgodne z dotychczasowym schematem. Aplikacja prezentuje je odpowiednio jako
klienta, pracownika i administratora; każdy panel weryfikuje rolę po stronie PHP.

## Planowane:

## Główne funkcjonalności

- Rejestracja i logowanie z rolami
- Zaawansowany system rezerwacji z sprawdzaniem kolizji terminów
- Zarządzanie grafikiem lekarzy
- Panele dla każdej roli
- Bezpieczne przechowywanie haseł (`password_hash()`)
- Weryfikacja haseł (`password_verify()`) i zmiana hasła po podaniu aktualnego
- Walidacja danych po stronie serwera i ochrona formularzy tokenami CSRF
- Ograniczanie prób logowania: blokada adresu e-mail po 5 i adresu IP po 20
  błędnych próbach w 15-minutowym oknie
- Bezpieczne sesje z limitem bezczynności, zmianą identyfikatora po logowaniu
  i wylogowaniem metodą POST
- Ochrona panelu pracownika i administratora oraz edycja własnego profilu
- Responsywny interfejs

## Baza danych

Schemat bazy MySQL/MariaDB znajduje się w [`database/database.sql`](database/database.sql),
a jego polska wersja w formacie DBML — gotowa do wklejenia do
[dbdiagram.io](https://dbdiagram.io) — w [`database/baza_przychodni.dbml`](database/baza_przychodni.dbml).
Otwórz plik DBML, skopiuj całą jego zawartość i wklej ją do edytora na dbdiagram.io.
Oba schematy zawierają te same tabele i relacje. Nazwy tabel, kolumn, ról i
statusów są po polsku (bez polskich znaków w identyfikatorach, aby uniknąć
problemów ze zgodnością narzędzi).

Skrypt tworzy brakujące tabele i dodaje przykładowe konta oraz ofertę. Nie usuwa
tabel ani istniejących danych. Jest to schemat startowy, a nie migracja
poprzedniej wersji bazy: nową instalację uruchom na pustej bazie. Przed zmianą
już używanej bazy przygotuj i przetestuj osobną migrację oraz kopię zapasową;
ponowne uruchomienie skryptu nie zmieni istniejących tabel. Przy ponownym
uruchomieniu skrypt utworzy brakującą tabelę `auth_login_attempts`, wymaganą
przez ograniczanie prób logowania.

Ustaw zmienne środowiskowe `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`,
`MYSQLPASSWORD` i `MYSQLDATABASE`, a następnie uruchom instalację z terminala:

```sh
php public/setup-db.php
```

Diagnostykę połączenia i liczby tabel uruchom poleceniem
`php public/test-db.php`. Oba narzędzia są dostępne wyłącznie z terminala.

Konta demonstracyjne (hasło dla każdego: `demo1234`):

- `admin@pochodnia.pl` — administrator
- `lekarz@pochodnia.pl`, `okulista@pochodnia.pl`, `pediatra@pochodnia.pl`,
  `rodzinny@pochodnia.pl`, `dermatolog@pochodnia.pl` — lekarze pięciu specjalizacji
- `pacjent@pochodnia.pl`, `pacjent2@pochodnia.pl`, `pacjent3@pochodnia.pl`,
  `pacjent4@pochodnia.pl` — pacjenci

Nie używaj kont demonstracyjnych w środowisku produkcyjnym i zmień hasło
administratora po pierwszym logowaniu. Wizyta może być
zarezerwowana ponownie po anulowaniu lub zakończeniu; aplikacja rezerwująca
powinna dodatkowo sprawdzać nakładające się przedziały czasu w transakcji,
ponieważ indeks bazy blokuje tylko identyczny czas rozpoczęcia wizyty.

## O projekcie

System rezerwacji wizyt lekarskich w przychodni **Pochodnia**. Umożliwia pacjentom wygodne umawianie wizyt online, lekarzom zarządzanie swoim grafikiem oraz administratorowi pełną kontrolę nad przychodnią.

Główny proces rezerwacji przebiega według schematu:  
**Specjalizacja → Lekarz → Termin → Godzina → Potwierdzenie**

Projekt spełnia wszystkie wymagania dokumentu projektowego, ze szczególnym naciskiem na:
- Poprawnie znormalizowaną bazę danych
- Bezpieczne uwierzytelnianie i kontrolę dostępu
- Inteligentne sprawdzanie kolizji terminów
- Responsywny interfejs