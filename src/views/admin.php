?>
<section class="content-section account-layout admin-page">
    <div class="section-heading">
        <p class="eyebrow">Zarządzanie systemem</p>
        <h1>Panel administratora</h1>
        <p>Zarządzaj przychodnią, kontroluj terminy i sprawdzaj bieżące statystyki.</p>
    </div>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" role="status"><?= $escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <nav class="admin-shortcuts" aria-label="Nawigacja panelu administratora">
        <a href="#overview">Podsumowanie</a><a href="#calendar">Kalendarz</a><a href="#appointments">Wizyty</a>
        <a href="#employees">Pracownicy</a><a href="#availability">Dostępność</a><a href="#services">Usługi</a>
        <a href="#categories">Kategorie</a><a href="#accounts">Konta</a><a href="#history">Historia</a>
    </nav>

    <section id="overview" class="admin-section">
    <div class="admin-stats">
        <article class="feature-card"><span class="feature-number">KONTA</span><h2><?= (int)$counts['users'] ?></h2><p>Użytkownicy ogółem</p></article>
        <article class="feature-card"><span class="feature-number">PACJENCI</span><h2><?= (int)$counts['patients'] ?></h2><p>Pacjenci przychodni</p></article>
        <article class="feature-card"><span class="feature-number">PERSONEL</span><h2><?= (int)$counts['employees'] ?></h2><p>Konta pracowników</p></article>
        <article class="feature-card"><span class="feature-number">DZISIAJ</span><h2><?= (int)$todayStats['total'] ?></h2><p>Wizyty, <?= (int)$todayStats['active'] ?> aktywne</p></article>
        <article class="feature-card"><span class="feature-number">TEN MIESIĄC</span><h2><?= (int)$monthStats['total'] ?></h2><p>Wizyty łącznie</p></article>
        <article class="feature-card"><span class="feature-number">ZAKOŃCZONE</span><h2><?= (int)$monthStats['completed'] ?></h2><p>Wizyty w tym miesiącu</p></article>
        <article class="feature-card"><span class="feature-number">ANULOWANE</span><h2><?= (int)$monthStats['cancelled'] ?></h2><p>Wizyty w tym miesiącu</p></article>
        <article class="feature-card"><span class="feature-number">PRZYCHÓD</span><h2><?= $escape(number_format((float)$monthStats['revenue'], 2, ',', ' ')) ?> zł</h2><p>Z zakończonych wizyt w tym miesiącu</p></article>
        <article class="feature-card"><span class="feature-number">ADMINISTRATORZY</span><h2><?= (int)$counts['administrators'] ?></h2><p>Konta administratorów</p></article>
    </div>

    <section class="auth-card admin-section admin-chart-card" aria-labelledby="visits-chart-title">
        <div class="admin-section-heading">
            <div><h2 class="form-section-title mt-0" id="visits-chart-title">Wizyty — ostatnie 6 miesięcy</h2><p class="admin-help">Liczba wizyt zarejestrowanych w każdym miesiącu.</p></div>
            <span class="admin-chart-total"><?= array_sum(array_column($chart, 'total')) ?> wizyt</span>
        </div>
        <div class="admin-chart" role="img" aria-label="Wykres liczby wizyt w ostatnich sześciu miesiącach">
            <?php foreach ($chart as $month): ?>
                <div class="admin-chart-column" aria-label="<?= $escape($month['label'] . ': ' . $month['total'] . ' wizyt') ?>">
                    <span><?= (int)$month['total'] ?></span>
                    <div class="admin-chart-track"><i style="height: <?= max(3, (int)round($month['total'] / $chartMax * 100)) ?>%"></i></div>
                    <strong><?= $escape($month['label']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    </section>

    <section class="auth-card admin-section" id="calendar">
        <?php
        $monthNames = [1 => 'styczeń', 2 => 'luty', 3 => 'marzec', 4 => 'kwiecień', 5 => 'maj', 6 => 'czerwiec', 7 => 'lipiec', 8 => 'sierpień', 9 => 'wrzesień', 10 => 'październik', 11 => 'listopad', 12 => 'grudzień'];
        $calendarTitle = $monthNames[(int)$calendarStart->format('n')] . ' ' . $calendarStart->format('Y');
        $firstWeekdayOffset = (int)$calendarStart->format('N') - 1;
        $daysInMonth = (int)$calendarStart->format('t');
        ?>
        <div class="admin-section-heading">
            <div><h2 class="form-section-title mt-0">Kalendarz wizyt</h2><p class="admin-help">Podgląd dziennych rezerwacji w wybranym miesiącu.</p></div>
            <div class="admin-calendar-navigation">
                <a class="button button-outline button-small" href="admin.php?calendar_month=<?= $escape($calendarPrevious) ?>#calendar" aria-label="Poprzedni miesiąc">‹</a>
                <strong><?= $escape(ucfirst($calendarTitle)) ?></strong>
                <a class="button button-outline button-small" href="admin.php?calendar_month=<?= $escape($calendarNext) ?>#calendar" aria-label="Następny miesiąc">›</a>
            </div>
        </div>
        <div class="admin-calendar">
            <?php foreach (['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Niedz'] as $weekday): ?><div class="admin-calendar-weekday"><?= $weekday ?></div><?php endforeach; ?>
            <?php for ($blank = 0; $blank < $firstWeekdayOffset; $blank++): ?><div class="admin-calendar-day is-empty" aria-hidden="true"></div><?php endfor; ?>
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php $dayStats = $calendarDays[$day] ?? null; ?>
                <a class="admin-calendar-day <?= $dayStats ? 'has-appointments' : '' ?>" href="admin.php?date_from=<?= $escape($calendarStart->format('Y-m-') . sprintf('%02d', $day)) ?>&amp;date_to=<?= $escape($calendarStart->format('Y-m-') . sprintf('%02d', $day)) ?>#appointments">
                    <strong><?= $day ?></strong>
                    <?php if ($dayStats): ?><span><?= (int)$dayStats['total'] ?> wiz.</span><small><?= (int)$dayStats['active'] ?> aktywne</small><?php endif; ?>
                </a>
            <?php endfor; ?>
        </div>
    </section>

    <section class="auth-card admin-section" id="categories">
        <h2 class="form-section-title mt-0"><?= $editingCategory ? 'Edytuj kategorię' : 'Dodaj kategorię' ?></h2>
        <form method="post" action="admin.php#categories" class="admin-form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="category_save">
            <input type="hidden" name="category_id" value="<?= $editingCategory ? (int)$editingCategory['id'] : '' ?>">
            <div><label class="form-label" for="category-name">Nazwa kategorii *</label><input class="form-control" id="category-name" name="name" maxlength="100" required value="<?= $escape($editingCategory['nazwa'] ?? '') ?>"></div>
            <div><label class="form-label" for="category-description">Opis</label><textarea class="form-control" id="category-description" name="description" rows="2" maxlength="10000"><?= $escape($editingCategory['opis'] ?? '') ?></textarea></div>
            <div class="admin-form-actions"><button class="button button-primary button-small" type="submit"><?= $editingCategory ? 'Zapisz kategorię' : 'Dodaj kategorię' ?></button><?php if ($editingCategory): ?><a class="button button-outline button-small" href="admin.php#categories">Anuluj</a><?php endif; ?></div>
        </form>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Kategoria</th><th>Usługi</th><th>Akcje</th></tr></thead><tbody>
            <?php foreach ($categories as $category): ?>
                <tr><td><strong><?= $escape($category['nazwa']) ?></strong><?php if ($category['opis']): ?><br><span class="text-muted"><?= $escape($category['opis']) ?></span><?php endif; ?></td><td><?= (int)$category['service_count'] ?></td><td class="admin-actions"><a class="button button-outline button-small" href="admin.php?edit_category=<?= (int)$category['id'] ?>#categories">Edytuj</a><form method="post" action="admin.php#categories" onsubmit="return confirm('Usunąć kategorię? Można usunąć tylko kategorię niepowiązaną z usługami i pracownikami.');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="category_delete"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><button class="button button-outline button-small button-danger" type="submit">Usuń</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$categories): ?><tr><td colspan="3">Brak kategorii. Dodaj pierwszą powyżej.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="services">
        <h2 class="form-section-title mt-0"><?= $editingService ? 'Edytuj usługę' : 'Dodaj usługę' ?></h2>
        <form method="post" action="admin.php#services" class="admin-form-grid admin-service-form">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="service_save">
            <input type="hidden" name="service_id" value="<?= $editingService ? (int)$editingService['id'] : '' ?>">
            <div><label class="form-label" for="service-name">Nazwa usługi *</label><input class="form-control" id="service-name" name="name" maxlength="160" required value="<?= $escape($editingService['nazwa'] ?? '') ?>"></div>
            <div><label class="form-label" for="service-category">Kategoria *</label><select class="form-select form-control" id="service-category" name="category_id" required><option value="">Wybierz kategorię</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($editingService['specjalizacja_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= $escape($category['nazwa']) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="service-duration">Czas trwania (min) *</label><input class="form-control" id="service-duration" type="number" name="duration" min="1" max="65535" required value="<?= $escape($editingService['czas_trwania_minut'] ?? 30) ?>"></div>
            <div><label class="form-label" for="service-price">Cena (zł) *</label><input class="form-control" id="service-price" type="number" name="price" min="0" max="999999.99" step="0.01" required value="<?= $escape($editingService['cena'] ?? '0.00') ?>"></div>
            <div class="admin-form-wide"><label class="form-label" for="service-description">Opis</label><textarea class="form-control" id="service-description" name="description" rows="2" maxlength="10000"><?= $escape($editingService['opis'] ?? '') ?></textarea></div>
            <label class="admin-checkbox"><input type="checkbox" name="active" value="1" <?= !$editingService || (bool)$editingService['czy_aktywny'] ? 'checked' : '' ?>> Usługa aktywna</label>
            <div class="admin-form-actions"><button class="button button-primary button-small" type="submit"><?= $editingService ? 'Zapisz usługę' : 'Dodaj usługę' ?></button><?php if ($editingService): ?><a class="button button-outline button-small" href="admin.php#services">Anuluj</a><?php endif; ?></div>
        </form>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Usługa</th><th>Kategoria</th><th>Czas</th><th>Cena</th><th>Status</th><th>Akcje</th></tr></thead><tbody>
            <?php foreach ($services as $service): ?>
                <tr><td><strong><?= $escape($service['nazwa']) ?></strong><?php if ($service['opis']): ?><br><span class="text-muted"><?= $escape($service['opis']) ?></span><?php endif; ?></td><td><?= $escape($service['category_name']) ?></td><td><?= (int)$service['czas_trwania_minut'] ?> min</td><td><?= $escape(number_format((float)$service['cena'], 2, ',', ' ')) ?> zł</td><td><?= (bool)$service['czy_aktywny'] ? 'Aktywna' : 'Nieaktywna' ?></td><td class="admin-actions"><a class="button button-outline button-small" href="admin.php?edit_service=<?= (int)$service['id'] ?>#services">Edytuj</a><form method="post" action="admin.php#services" onsubmit="return confirm('<?= (bool)$service['czy_aktywny'] ? 'Dezaktywować' : 'Aktywować' ?> tę usługę?');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="service_toggle"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><input type="hidden" name="active" value="<?= (bool)$service['czy_aktywny'] ? 0 : 1 ?>"><button class="button button-outline button-small" type="submit"><?= (bool)$service['czy_aktywny'] ? 'Dezaktywuj' : 'Aktywuj' ?></button></form><form method="post" action="admin.php#services" onsubmit="return confirm('Usunąć usługę? Można usunąć tylko usługę bez przypisanych wizyt.');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="service_delete"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><button class="button button-outline button-small button-danger" type="submit">Usuń</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$services): ?><tr><td colspan="6">Brak usług. Dodaj pierwszą powyżej.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="employees">
        <h2 class="form-section-title mt-0"><?= $editingEmployeeId > 0 ? 'Przypisz usługi pracownikowi' : 'Dodaj pracownika' ?></h2>
        <p class="admin-help">Nowy pracownik otrzyma kategorię, aktywne usługi z tej kategorii i przypisanie do pierwszej aktywnej placówki.</p>
        <?php if ($editingEmployeeId > 0): ?>
            <?php
            $editingEmployee = null;
            foreach ($employees as $employee) {
                if ((int)$employee['id'] === $editingEmployeeId) {
                    $editingEmployee = $employee;
                    break;
                }
            }
            ?>
            <?php if ($editingEmployee): ?>
                <p class="admin-help">Pracownik: <strong><?= $escape($editingEmployee['imie'] . ' ' . $editingEmployee['nazwisko']) ?></strong>. Usługi z wcześniejszymi wizytami muszą pozostać przypisane.</p>
                <form method="post" action="admin.php#employees" class="admin-service-assignments">
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="employee_services_save">
                    <input type="hidden" name="employee_id" value="<?= $editingEmployeeId ?>">
                    <?php foreach ($servicesForEmployee as $serviceOption): ?>
                        <label class="admin-service-option">
                            <input type="checkbox" name="service_ids[]" value="<?= (int)$serviceOption['id'] ?>" <?= (bool)$serviceOption['assigned'] ? 'checked' : '' ?>>
                            <span><strong><?= $escape($serviceOption['nazwa']) ?></strong><small><?= $escape($serviceOption['category_name']) ?><?= !(bool)$serviceOption['czy_aktywny'] ? ' · nieaktywna' : '' ?></small></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!$servicesForEmployee): ?><p class="admin-help">Brak usług zgodnych ze specjalizacją pracownika.</p><?php endif; ?>
                    <div class="admin-form-actions"><button class="button button-primary button-small" type="submit">Zapisz przypisania</button><a class="button button-outline button-small" href="admin.php#employees">Anuluj</a></div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">Nie znaleziono pracownika do edycji.</div>
            <?php endif; ?>
        <?php else: ?>
        <form method="post" action="admin.php#employees" class="admin-form-grid admin-employee-form">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="employee_create">
            <div><label class="form-label" for="employee-first-name">Imię *</label><input class="form-control" id="employee-first-name" name="first_name" maxlength="80" required></div>
            <div><label class="form-label" for="employee-last-name">Nazwisko *</label><input class="form-control" id="employee-last-name" name="last_name" maxlength="80" required></div>
            <div><label class="form-label" for="employee-email">E-mail *</label><input class="form-control" id="employee-email" type="email" name="email" maxlength="254" required></div>
            <div><label class="form-label" for="employee-phone">Telefon</label><input class="form-control" id="employee-phone" type="tel" name="phone" maxlength="30"></div>
            <div><label class="form-label" for="employee-password">Hasło początkowe *</label><input class="form-control" id="employee-password" type="password" name="password" minlength="8" maxlength="72" required></div>
            <div><label class="form-label" for="employee-license">Numer PWZ *</label><input class="form-control" id="employee-license" name="license" maxlength="40" required></div>
            <div><label class="form-label" for="employee-category">Kategoria *</label><select class="form-select form-control" id="employee-category" name="category_id" required><option value="">Wybierz kategorię</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= $escape($category['nazwa']) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="employee-duration">Domyślny czas wizyty (min) *</label><input class="form-control" id="employee-duration" type="number" name="default_duration" min="1" max="65535" value="30" required></div>
            <div class="admin-form-actions"><button class="button button-primary button-small" type="submit">Utwórz konto pracownika</button></div>
        </form>
        <?php endif; ?>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Pracownik</th><th>E-mail</th><th>Numer PWZ</th><th>Kategorie</th><th>Status</th><th>Akcje</th></tr></thead><tbody>
            <?php foreach ($employees as $employee): ?>
                <tr><td><?= $escape($employee['imie'] . ' ' . $employee['nazwisko']) ?></td><td><?= $escape($employee['email']) ?></td><td><?= $escape($employee['numer_pwz']) ?></td><td><?= $escape($employee['categories'] ?? '—') ?></td><td><?= (bool)$employee['czy_aktywny'] ? 'Aktywny' : 'Nieaktywny' ?></td><td class="admin-actions"><a class="button button-outline button-small" href="admin.php?edit_employee_services=<?= (int)$employee['id'] ?>#employees">Usługi</a><form method="post" action="admin.php#employees" onsubmit="return confirm('<?= (bool)$employee['czy_aktywny'] ? 'Dezaktywować' : 'Aktywować' ?> konto tego pracownika?');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="employee_toggle"><input type="hidden" name="employee_id" value="<?= (int)$employee['id'] ?>"><input type="hidden" name="active" value="<?= (bool)$employee['czy_aktywny'] ? 0 : 1 ?>"><button class="button button-outline button-small" type="submit"><?= (bool)$employee['czy_aktywny'] ? 'Dezaktywuj' : 'Aktywuj' ?></button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$employees): ?><tr><td colspan="6">Brak pracowników.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="availability">
        <h2 class="form-section-title mt-0">Dostępność pracowników</h2>
        <p class="admin-help">Dodawaj cykliczne godziny przyjęć z opcjonalnym zakresem obowiązywania. Dni wolne można prowadzić jako wyjątki grafiku w bazie.</p>
        <form method="post" action="admin.php#availability" class="admin-form-grid">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="availability_add">
            <div><label class="form-label" for="availability-employee">Pracownik i placówka *</label><select class="form-select form-control" id="availability-employee" name="employee_clinic" required>
                <option value="">Wybierz pracownika / placówkę</option>
                <?php foreach ($employeeClinics as $employeeClinic): ?>
                    <option value="<?= (int)$employeeClinic['lekarz_id'] ?>:<?= (int)$employeeClinic['placowka_id'] ?>"><?= $escape($employeeClinic['employee_name'] . ' — ' . $employeeClinic['clinic_name']) ?></option>
                <?php endforeach; ?>
            </select></div>
            <div><label class="form-label" for="availability-day">Dzień tygodnia *</label><select class="form-select form-control" id="availability-day" name="day" required><option value="">Wybierz dzień</option><?php foreach ($days as $dayNumber => $dayLabel): ?><option value="<?= $dayNumber ?>"><?= $escape($dayLabel) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="availability-start">Godzina od *</label><input class="form-control" id="availability-start" type="time" name="start_time" required></div>
            <div><label class="form-label" for="availability-end">Godzina do *</label><input class="form-control" id="availability-end" type="time" name="end_time" required></div>
            <div><label class="form-label" for="availability-from">Obowiązuje od</label><input class="form-control" id="availability-from" type="date" name="valid_from"></div>
            <div><label class="form-label" for="availability-until">Obowiązuje do</label><input class="form-control" id="availability-until" type="date" name="valid_until"></div>
            <div class="admin-form-actions"><button class="button button-primary button-small" type="submit">Dodaj dostępność</button></div>
        </form>
        <div class="table-responsive admin-table-wrap">
            <table class="table align-middle"><thead><tr><th>Pracownik</th><th>Dzień</th><th>Godziny</th><th>Placówka</th><th>Obowiązuje</th><th>Akcja</th></tr></thead><tbody>
            <?php foreach ($availability as $schedule): ?>
                <tr><td><?= $escape($schedule['employee_name']) ?></td><td><?= $escape($days[(int)$schedule['dzien_tygodnia']] ?? '—') ?></td><td><?= $escape(substr($schedule['godzina_od'], 0, 5) . '–' . substr($schedule['godzina_do'], 0, 5)) ?></td><td><?= $escape($schedule['clinic_name']) ?></td><td><?= $escape(($schedule['obowiazuje_od'] ?: 'bez początku') . ' – ' . ($schedule['obowiazuje_do'] ?: 'bez końca')) ?></td><td><form method="post" action="admin.php#availability" onsubmit="return confirm('Usunąć ten wpis dostępności?');"><input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="availability_delete"><input type="hidden" name="availability_id" value="<?= (int)$schedule['id'] ?>"><button class="button button-outline button-small button-danger" type="submit">Usuń</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$availability): ?><tr><td colspan="6">Brak zdefiniowanej dostępności.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="appointments">
        <div class="admin-section-heading">
            <div><h2 class="form-section-title mt-0">Przegląd wizyt</h2><p class="admin-help">Znaleziono: <?= $appointmentCount ?> · strona <?= (int)$appointmentPage ?> z <?= $appointmentPages ?></p></div>
            <?php $exportQueryString = http_build_query(array_merge($_GET, ['export' => 'appointments'])); ?>
            <a class="button button-outline button-small" href="admin.php?<?= $escape($exportQueryString) ?>">Eksportuj CSV</a>
        </div>
        <form method="get" action="admin.php#appointments" class="admin-filter-grid">
            <div><label class="form-label" for="filter-from">Data od</label><input class="form-control" id="filter-from" type="date" name="date_from" value="<?= $escape($filterFrom) ?>"></div>
            <div><label class="form-label" for="filter-to">Data do</label><input class="form-control" id="filter-to" type="date" name="date_to" value="<?= $escape($filterTo) ?>"></div>
            <div><label class="form-label" for="filter-status">Status</label><select class="form-select form-control" id="filter-status" name="status"><option value="">Wszystkie</option><?php foreach ($appointmentStatuses as $statusValue => $statusLabel): ?><option value="<?= $escape($statusValue) ?>" <?= $filterStatus === $statusValue ? 'selected' : '' ?>><?= $escape($statusLabel) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="filter-employee">Pracownik</label><select class="form-select form-control" id="filter-employee" name="employee"><option value="">Wszyscy</option><?php foreach ($employees as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= (int)$filterEmployee === (int)$employee['id'] ? 'selected' : '' ?>><?= $escape($employee['imie'] . ' ' . $employee['nazwisko']) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="filter-search">Szukaj pacjenta, pracownika lub usługi</label><input class="form-control" id="filter-search" name="q" type="search" maxlength="120" value="<?= $escape($search) ?>" placeholder="Wpisz nazwisko lub nazwę"></div>
            <div class="admin-filter-actions"><button class="button button-primary button-small" type="submit">Filtruj</button><a class="button button-outline button-small" href="admin.php#appointments">Wyczyść</a></div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Termin</th><th>Pacjent</th><th>Pracownik</th><th>Usługa</th><th>Placówka</th><th>Cena</th><th>Status i zmiana</th></tr></thead><tbody>
            <?php foreach ($appointments as $appointment): ?>
                <?php $nextStatuses = $allowedAppointmentTransitions[$appointment['status']] ?? []; ?>
                <tr><td><?= $escape($appointment['data_wizyty']) ?><br><?= $escape(substr($appointment['godzina_wizyty'], 0, 5)) ?></td><td><?= $escape($appointment['patient_name']) ?></td><td><?= $escape($appointment['doctor_name']) ?></td><td><?= $escape($appointment['service_name']) ?><br><span class="text-muted"><?= (int)$appointment['czas_trwania_minut'] ?> min</span></td><td><?= $escape($appointment['clinic_name']) ?></td><td><?= $escape(number_format((float)$appointment['cena'], 2, ',', ' ')) ?> zł</td><td><strong><?= $escape($appointmentStatuses[$appointment['status']] ?? $appointment['status']) ?></strong>
                    <?php if ($nextStatuses): ?>
                        <form method="post" action="admin.php#appointments" class="admin-status-form" onsubmit="return confirm('Zmienić status tej wizyty? Zmiana zostanie zapisana w historii.');">
                            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="action" value="appointment_status"><input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>">
                            <select class="form-select form-control" name="status" aria-label="Nowy status wizyty <?= (int)$appointment['id'] ?>"><?php foreach ($nextStatuses as $nextStatus): ?><option value="<?= $escape($nextStatus) ?>"><?= $escape($appointmentStatuses[$nextStatus]) ?></option><?php endforeach; ?></select>
                            <button class="button button-outline button-small" type="submit">Zapisz</button>
                        </form>
                    <?php endif; ?>
                </td></tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><tr><td colspan="7">Brak wizyt.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <?php if ($appointmentPages > 1): ?>
            <?php $paginationParameters = $_GET; unset($paginationParameters['page'], $paginationParameters['export']); ?>
            <nav class="admin-pagination" aria-label="Strony listy wizyt">
                <?php if ($appointmentPage > 1): ?><a class="button button-outline button-small" href="admin.php?<?= $escape(http_build_query(array_merge($paginationParameters, ['page' => $appointmentPage - 1]))) ?>#appointments">Poprzednia</a><?php endif; ?>
                <span>Strona <?= (int)$appointmentPage ?> z <?= $appointmentPages ?></span>
                <?php if ($appointmentPage < $appointmentPages): ?><a class="button button-outline button-small" href="admin.php?<?= $escape(http_build_query(array_merge($paginationParameters, ['page' => $appointmentPage + 1]))) ?>#appointments">Następna</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

    <section class="auth-card admin-section" id="history">
        <h2 class="form-section-title mt-0">Historia zmian statusów wizyt</h2>
        <p class="admin-help">Ostatnie 30 wpisów zapisanych automatycznie przy tworzeniu wizyty lub zmianie jej statusu.</p>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Data zmiany</th><th>Wizyta</th><th>Zmiana statusu</th><th>Administrator / użytkownik</th><th>Opis</th></tr></thead><tbody>
            <?php foreach ($appointmentHistory as $history): ?>
                <tr><td><?= $escape(date('Y-m-d H:i', strtotime($history['utworzono_at']))) ?></td><td>#<?= (int)$history['wizyta_id'] ?></td><td><?= $escape($appointmentStatuses[$history['poprzedni_status']] ?? '—') ?> → <strong><?= $escape($appointmentStatuses[$history['nowy_status']] ?? $history['nowy_status']) ?></strong></td><td><?= $escape($history['actor_name'] ?? 'System') ?></td><td><?= $escape($history['notatka'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$appointmentHistory): ?><tr><td colspan="5">Brak zapisanej historii.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>

    <section class="auth-card admin-section" id="accounts">
        <h2 class="form-section-title mt-0">Konta użytkowników</h2>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Użytkownik</th><th>E-mail</th><th>Rola</th><th>Status</th><th>Utworzono</th></tr></thead><tbody>
            <?php foreach ($users as $user): ?>
                <tr><td><?= $escape($user['imie'] . ' ' . $user['nazwisko']) ?></td><td><?= $escape($user['email']) ?></td><td><?= $escape(userRoleLabel($user['rola'])) ?></td><td><?= (bool)$user['czy_aktywny'] ? 'Aktywne' : 'Nieaktywne' ?></td><td><?= $escape(date('Y-m-d', strtotime($user['utworzono_at']))) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="5">Brak kont.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <?php if (count($users) === 100): ?><p class="admin-help">Wyświetlono 100 ostatnio utworzonych kont.</p><?php endif; ?>
    </section>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
