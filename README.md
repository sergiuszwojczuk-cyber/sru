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

- **Pacjent** – rejestracja, logowanie, przeglądanie oferty, rezerwacja wizyt, zarządzanie swoimi wizytami, anulowanie rezerwacji
- **Lekarz** – przeglądanie swoich wizyt, zmiana statusu wizyty, historia pacjentów
- **Administrator** – pełna administracja systemem (zarządzanie lekarzami, specjalizacjami, grafikiem, użytkownikami i rezerwacjami)

## Planowane:

## Główne funkcjonalności

- Rejestracja i logowanie z rolami
- Zaawansowany system rezerwacji z sprawdzaniem kolizji terminów
- Zarządzanie grafikiem lekarzy
- Panele dla każdej roli
- Bezpieczne przechowywanie haseł (`password_hash()`)
- Walidacja danych po stronie serwera
- Responsywny interfejs

## O projekcie

System rezerwacji wizyt lekarskich w przychodni **VitaMed**. Umożliwia pacjentom wygodne umawianie wizyt online, lekarzom zarządzanie swoim grafikiem oraz administratorowi pełną kontrolę nad przychodnią.

Główny proces rezerwacji przebiega według schematu:  
**Specjalizacja → Lekarz → Termin → Godzina → Potwierdzenie**

Projekt spełnia wszystkie wymagania dokumentu projektowego, ze szczególnym naciskiem na:
- Poprawnie znormalizowaną bazę danych
- Bezpieczne uwierzytelnianie i kontrolę dostępu
- Inteligentne sprawdzanie kolizji terminów
- Responsywny interfejs