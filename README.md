# Mailcow Password Sync — Nextcloud App

Minimalna aplikacja Nextcloud, która automatycznie synchronizuje hasło
użytkownika do Mailcow po każdej zmianie hasła w Nextcloud.

**Mapowanie:** `login_nc` → `login_nc@najmuje.eu` (mailbox w Mailcow)

---

## Wymagania

- Nextcloud 25+ (AIO lub klasyczna instalacja)
- Mailcow z włączonym API
- PHP z rozszerzeniem `curl` (standardowo dostępne)

---

## Instalacja

### 1. Sklonuj repozytorium i skopiuj do Nextcloud

```bash
# Sklonuj repo na serwer
git clone https://github.com/machayka/mailcow-password-sync.git

# Skopiuj do kontenera Nextcloud AIO jako custom_apps
docker cp mailcow-password-sync nextcloud-aio-nextcloud:/var/www/html/custom_apps/mailcow_password_sync
```

> **Uwaga:** W Nextcloud AIO katalog `custom_apps` jest persystentny
> (zamontowany jako volume), więc app przetrwa restart kontenera.

### 2. Ustaw uprawnienia

```bash
docker exec nextcloud-aio-nextcloud chown -R www-data:www-data /var/www/html/custom_apps/mailcow_password_sync
```

### 3. Włącz aplikację

```bash
docker exec -u www-data nextcloud-aio-nextcloud php occ app:enable mailcow_password_sync
```

### 4. Skonfiguruj połączenie z Mailcow

```bash
# URL Mailcow (bez trailing slash)
docker exec -u www-data nextcloud-aio-nextcloud php occ config:app:set \
  mailcow_password_sync mailcow_url --value="https://mail.najmuje.eu"

# API key z Mailcow (System → Configuration → API → Read-Write Access)
docker exec -u www-data nextcloud-aio-nextcloud php occ config:app:set \
  mailcow_password_sync mailcow_api_key --value="TWOJ-MAILCOW-API-KEY"

# Domena email (domyślnie: najmuje.eu)
docker exec -u www-data nextcloud-aio-nextcloud php occ config:app:set \
  mailcow_password_sync mail_domain --value="najmuje.eu"
```

---

## Aktualizacja

```bash
# Na serwerze, w katalogu gdzie sklonowałeś repo
cd mailcow-password-sync
git pull

# Skopiuj zaktualizowane pliki do kontenera
docker cp . nextcloud-aio-nextcloud:/var/www/html/custom_apps/mailcow_password_sync
docker exec nextcloud-aio-nextcloud chown -R www-data:www-data /var/www/html/custom_apps/mailcow_password_sync
```

---

## Skąd wziąć Mailcow API Key

1. Zaloguj się do **Mailcow Admin UI** → `https://mail.najmuje.eu`
2. Idź do **System** → **Configuration** → zakładka **Access**
3. Sekcja **API** → wygeneruj klucz z uprawnieniami **Read-Write**
4. Skopiuj klucz i użyj go w komendzie powyżej

---

## Weryfikacja

Po instalacji zmień hasło dowolnego użytkownika testowego:

```bash
docker exec -u www-data nextcloud-aio-nextcloud php occ user:resetpassword testuser
```

Sprawdź logi Nextcloud:

```bash
docker exec nextcloud-aio-nextcloud cat /var/www/html/data/nextcloud.log | grep MailcowPasswordSync
```

Poprawny wynik:
```
MailcowPasswordSync: Password synced successfully for testuser@najmuje.eu.
```

---

## Jak to działa

1. Użytkownik zmienia hasło w Nextcloud (UI lub occ)
2. Nextcloud emituje event `PasswordUpdatedEvent` z hasłem w plaintext
3. Nasz listener łapie event i wysyła `POST /api/v1/edit/mailbox` do Mailcow
4. Mailcow aktualizuje hasło skrzynki `user@najmuje.eu`

---

## Troubleshooting

| Problem | Rozwiązanie |
|---------|------------|
| `mailcow_url or mailcow_api_key not configured` | Uruchom komendy `occ config:app:set` z kroku 4 |
| `cURL error` | Sprawdź czy Nextcloud ma dostęp sieciowy do Mailcow |
| `HTTP 401` | Zły API key — wygeneruj nowy w Mailcow |
| `HTTP 404` | Mailbox nie istnieje w Mailcow — utwórz go najpierw |
| Hasło nie zawiera plaintext | Event bez hasła — np. LDAP/SSO backend. Dotyczy tylko natywnych kont NC |

---

## Struktura plików

```
mailcow-password-sync/
├── appinfo/
│   └── info.xml              # Rejestracja aplikacji
├── composer.json              # Autoloading PSR-4
├── lib/
│   ├── AppInfo/
│   │   └── Application.php   # Bootstrap — rejestruje listener
│   └── Listener/
│       └── PasswordChangeListener.php  # Główna logika
└── README.md
```
