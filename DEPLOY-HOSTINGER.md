# Pag-deploy sa Hostinger

Gabay ito para mailagay ang San Agustin MSWDO portal (Laravel 10) sa Hostinger shared hosting.

---

## 1. Paano gumagana ang Hostinger

| Bagay | Ibig sabihin |
|---|---|
| **hPanel** | Ang control panel ng Hostinger. Dito ang domain, database, PHP version, SSL, File Manager, at SSH. |
| **Shared hosting** | Kahati mo ang server ng ibang customer. Sapat ito para sa system na ito. |
| **`public_html`** | Ang folder na nakikita ng mundo. Kapag binuksan ang `yourdomain.com`, dito kumukuha ang server. |
| **SSH** | Remote terminal papunta sa server. Dito tatakbo ang `composer`, `php artisan`, at `git`. |
| **MySQL + phpMyAdmin** | Pareho ng sa XAMPP, pero ginagawa ang database sa hPanel. May prefix ang pangalan, hal. `u123456789_mswdo`. |
| **LiteSpeed** | Web server nila. Binabasa nito ang `.htaccess` gaya ng Apache, kaya gagana ang `public/.htaccess` ng Laravel. |

**Pagpili ng plan:** Kunin ang **Premium** (o mas mataas, hal. Unlimited), hindi ang Single. Ayon sa Hostinger, may libreng domain ang lahat ng plan maliban sa Single kapag isang taon o higit pa ang binili, at may SSH at Composer ang Premium pataas. Libre lang ang domain sa unang taon; may bayad na ang renewal.

**Walang Node.js sa shared hosting.** Kaya ang `npm run build` ay gagawin sa sarili mong computer, at ia-upload ang `public/build`.

---

## 2. Bago mag-deploy (sa sarili mong computer)

1. Siguraduhing pasado ang tests: `php artisan test`
2. I-build ang CSS/JS: `npm run build`. Gagawa ito ng `public/build/`.
3. I-push ang code sa GitHub (`git push`).
4. **Kung may data na sa XAMPP na gusto mong dalhin:** sa phpMyAdmin (localhost), piliin ang `san_agustin_mswdo` → **Export** → Quick → SQL. I-save ang `.sql` file.

> Naka-`.gitignore` ang `public/build`, kaya **hindi** ito sasama sa `git clone`. Kailangan itong i-upload nang hiwalay (step 5.6).

---

## 3. Setup sa hPanel (isang beses lang)

1. **Domain:** I-claim ang libreng domain. Hintayin itong maging *Active* (minsan ilang minuto, minsan ilang oras).
2. **PHP version:** Websites → Manage → **Advanced → PHP Configuration**. Piliin ang **PHP 8.2** (o 8.1 pataas).
3. **Database:** **Databases → Management**. Gumawa ng database at user, at **isulat** ang tatlong ito:
   - Database name (hal. `u123456789_mswdo`)
   - Username (hal. `u123456789_mswdo`)
   - Password
4. **SSH:** **Advanced → SSH Access** → Enable. Isulat ang IP, port (karaniwang `65002`), at username.
5. **SSL:** **Security → SSL**. I-install ang libreng SSL at i-on ang **Force HTTPS**.

---

## 4. Ang folder layout na gagawin natin

```
/home/u123456789/domains/yourdomain.com/
├── laravel/            ← ang buong project (HINDI public)
│   ├── app/  config/  .env  vendor/ ...
│   └── public/         ← ito lang dapat ang makita ng mundo
└── public_html  →  laravel/public   (symlink)
```

Bakit ganito? Kung buong project ang ilalagay sa `public_html`, puwedeng mabuksan ng kahit sino ang `.env` (nandoon ang password ng database). Sa ganitong ayos, ang `public/` lang ang nakalantad.

---

## 5. Pag-deploy (unang beses)

### 5.1 Pumasok sa server
Sa terminal ng computer mo (PowerShell o Git Bash):
```bash
ssh -p 65002 u123456789@YOUR_SERVER_IP
```

### 5.2 Kunin ang code
```bash
cd ~/domains/yourdomain.com
git clone https://github.com/pridoo/finance-management-system.git laravel
cd laravel
```
> Kung **private** ang repo, hihingan ka ng username at password. Ang password ay isang GitHub **Personal Access Token** (GitHub → Settings → Developer settings → Tokens), hindi ang password mo sa GitHub.

### 5.3 I-install ang PHP packages
```bash
php -v                     # dapat 8.1 pataas
composer install --no-dev --optimize-autoloader
```

### 5.4 Gumawa ng `.env`
```bash
cp .env.example .env
nano .env
```
Palitan ang mga ito:
```env
APP_NAME="San Agustin Portal"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_mswdo
DB_USERNAME=u123456789_mswdo
DB_PASSWORD=yung_password_mo

SESSION_SECURE_COOKIE=true
LOG_LEVEL=error
```
I-save sa nano: `Ctrl+O`, Enter, tapos `Ctrl+X`.

> **`APP_DEBUG=false` ay sapilitan.** Kapag `true`, makikita ng kahit sinong makaka-trigger ng error ang mga password at code mo.

Pagkatapos, gumawa ng app key:
```bash
php artisan key:generate
```

### 5.5 Database (piliin ang isa)

**A. Bagong database (walang lumang data):**
```bash
php artisan migrate --force
php artisan db:seed --force
```

**B. Dalhin ang data mula sa XAMPP:** sa hPanel → Databases → **phpMyAdmin** → piliin ang bagong database → **Import** → piliin ang `.sql` file mula sa step 2.4. Pagkatapos, patakbuhin pa rin ito para sa mga migration na kulang:
```bash
php artisan migrate --force
```

> ⚠️ May default na password ang mga seeded account (hal. `admin@sanagustin.gov.ph` / `12345678`). **Palitan agad** pagkatapos mag-login. Public na ang site.

### 5.6 I-upload ang `public/build`
Sa computer mo, i-zip ang folder na `public/build`. Sa hPanel → **File Manager**, pumunta sa `domains/yourdomain.com/laravel/public/`, i-upload ang zip, at i-**Extract** doon. Ang dapat na kalabasan ay `laravel/public/build/manifest.json`.

### 5.7 Permissions at cache
```bash
chmod -R 775 storage bootstrap/cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5.8 Ituro ang domain sa `public/`
```bash
cd ~/domains/yourdomain.com
mv public_html public_html_old      # backup ng default page ng Hostinger
ln -s laravel/public public_html
```
Buksan ang `https://yourdomain.com`. Dapat lumabas ang login page.

---

## 6. Pag-update pagkatapos (tuwing may bagong code)

Sa computer mo: `npm run build` (kung may binago sa CSS/JS), tapos `git push`. Sa server:

```bash
cd ~/domains/yourdomain.com/laravel
php artisan down --secret="silip-lang-ako"     # maintenance mode ON
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up                                  # maintenance mode OFF
```
Kung may binago sa CSS/JS, i-upload ulit ang `public/build` (step 5.6) bago mag-`php artisan up`.

---

## 7. Maintenance mode

| Command | Ginagawa |
|---|---|
| `php artisan down` | Maintenance modal ang makikita ng lahat ng magbubukas ng site (HTTP 503). |
| `php artisan down --secret="silip-lang-ako"` | Ganoon din, pero kapag binuksan mo ang `https://yourdomain.com/silip-lang-ako`, makakapasok ka pa rin para ma-test mo bago buksan sa lahat. |
| `php artisan up` | Bukas na ulit sa lahat. |

**Ano ang makikita ng mga user:**
- **Bagong bukas na site:** maintenance screen na may logo, "System Under Maintenance", at **Try again now** button. Kusa itong nagre-reload kapag tapos na ang maintenance (tsine-check bawat 15 segundo).
- **Nasa loob na ng system:** sa loob ng isang minuto, may lalabas na modal sa ibabaw ng page. Kapag nag-Submit sila habang maintenance, **hindi** ito ipapadala, kaya hindi mawawala ang na-type nila. Pagkatapos ng `php artisan up`, magiging "The Portal Is Back" ang modal at puwede na silang mag-Continue at mag-submit ulit.

---

## 8. Kapag may problema

| Sintomas | Tingnan |
|---|---|
| **500 Server Error** | `storage/logs/laravel.log`. Kadalasan mali ang `.env` (database) o hindi pa nagagawa ang `chmod` sa step 5.7. |
| **Walang design ang page** | Hindi na-upload ang `public/build`, o mali ang pinaglagyan (step 5.6). |
| **Binago ang `.env` pero walang epekto** | Patakbuhin ulit ang `php artisan config:cache`. |
| **"Access denied for user"** | Mali ang `DB_DATABASE`, `DB_USERNAME`, o `DB_PASSWORD`. Kasama dapat ang prefix na `u123456789_`. |
| **Lumalabas pa ang default page ng Hostinger** | Hindi nagawa ang symlink sa step 5.8. Tingnan gamit ang `ls -la ~/domains/yourdomain.com`. |
