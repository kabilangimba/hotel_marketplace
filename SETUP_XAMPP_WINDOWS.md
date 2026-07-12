# Running the Hotel Marketplace on XAMPP (Windows)

These files are plain PHP + MySQL, so they run on XAMPP with **no code changes** —
you only set the database login and import the database. ~5 minutes.

## 1. Install & start XAMPP
1. Install XAMPP (PHP 8.x) from https://www.apachefriends.org/
2. Open **XAMPP Control Panel** and click **Start** on **Apache** and **MySQL**.

## 2. Copy the project into htdocs
Put the whole `hotel_marketplace` folder here:

```
C:\xampp\htdocs\hotel_marketplace
```

So your files end up at `C:\xampp\htdocs\hotel_marketplace\index.php`, etc.

## 3. Set the database login (one edit)
XAMPP's MySQL uses user **root** with an **empty password**. Open
`config.php` and change the two lines as the comments there show:

```php
$DB_USER = 'root';
$DB_PASS = '';      // empty — XAMPP default
```

(Leave `$DB_HOST = 'localhost'` and `$DB_NAME = 'hotel_marketplace'`.)

## 4. Create the database (import ONE file)
1. Go to **http://localhost/phpmyadmin**
2. Click the **Import** tab (don't pick a database first — the file creates it).
3. Choose **`install_xampp.sql`** from the project folder and click **Go**.

That single file builds everything: tables, the real Moshi/Arusha hotels with
rooms, plus reviews, wishlist, amenities and trip add-ons.

> Prefer the command line? From the project folder:
> `C:\xampp\mysql\bin\mysql -u root hotel_marketplace < install_xampp.sql`
> (run it without a DB name; the file creates `hotel_marketplace` itself.)

## 5. Open the app
Go to **http://localhost/hotel_marketplace/**

## Login accounts (password for all: `password123`)
| Role     | Email             | Lands on            |
|----------|-------------------|---------------------|
| Admin    | admin@hotel.com   | Admin dashboard     |
| Manager  | mgr1@hotel.com    | Manager dashboard   |
| Manager  | mgr2@hotel.com    | Manager dashboard   |
| Customer | judith@gmail.com  | Home / search       |
| Customer | john@gmail.com    | Home / search       |

## Notes
- **Hotel photos load from the internet** (Unsplash URLs), so keep the PC online,
  or the cards show broken images. Everything else works offline.
- Requires **PHP 7.4+** (XAMPP's PHP 8.x is fine). MySQL/MariaDB that ships with
  XAMPP supports the `IF NOT EXISTS` / `CHECK` syntax used here.
- If you see *"Database connection failed"*, re-check step 3 (the root/empty login)
  and that MySQL is running in the XAMPP panel.
- If port 80 is busy (Skype/IIS), change Apache's port in XAMPP or stop the other
  app, then use the new port, e.g. http://localhost:8080/hotel_marketplace/
```
