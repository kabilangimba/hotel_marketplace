# Hotel Marketplace Project — Complete Study Guide

**For:** Judith Antoni Obedi (BCS-01-0019-2023)
**Supervisor:** Mr. Clarence Kayange
**Presentation:** Monday, 4:00 PM

---

## 1. THE BIG PICTURE — What you actually built

You built a **web-based marketplace** where:

- **Customers** can search hotels, compare prices, and book rooms online.
- **Hotel managers** can list their hotels, add rooms, set prices, and see who has booked.
- **Administrators** can monitor the whole system, manage users, and run reports.

Think of it as a smaller, Tanzania-focused version of Booking.com, but built for small/medium hotels in places like Arusha that can't afford international platform fees.

**The 4 things that prove it's a real project (not just a website):**

1. **A real database** — 4 connected tables (users, hotels, rooms, bookings)
2. **Real business logic** — preventing double bookings, calculating prices, role-based access
3. **Security** — passwords hashed with bcrypt, SQL injection blocked, sessions managed
4. **Three different user dashboards** — each role sees only what they should

---

## 2. EVERY FILE EXPLAINED

You have a folder of PHP files. Here is what each one does and why it matters.

### `config.php` — The Foundation
Every single page starts by including this file. It:
- Connects to MySQL using PDO (the modern PHP database extension)
- Starts the session (so logins are remembered)
- Provides helper functions: `is_logged_in()`, `require_role()`, `safe()`

> **If the lecturer asks "Why PDO and not mysqli?"** → "PDO supports multiple database engines and has a cleaner prepared-statement syntax which prevents SQL injection."

### `register.php` — Creating accounts
The form sends name, email, password. Then:
1. Validates the input (not empty, valid email, password ≥ 6 chars).
2. Checks if the email is already used.
3. Hashes the password with `password_hash()` — uses bcrypt by default.
4. Inserts the user into the database with role `customer`.

> **If asked "Why hash passwords?"** → "If our database is ever leaked, attackers cannot see the original passwords. Bcrypt is slow on purpose (about 100 ms per hash), which makes brute-forcing impractical."

### `login.php` — Authenticating users
1. Looks up the user by email.
2. Calls `password_verify()` which compares the entered password to the stored hash.
3. If valid, stores `user_id`, `name`, and `role` in `$_SESSION`.
4. Redirects to the right dashboard depending on role.

> **If asked "Why the same error message for wrong email and wrong password?"** → "User enumeration prevention — attackers can't probe which emails exist in our system."

### `index.php` — The customer home page
- Shows a search form (location, dates).
- Lists all hotels matching the search, with their starting price.
- Has a "View Rooms" button per hotel.

### `hotel.php` — Hotel detail page
- Shows all active rooms in a hotel with prices and capacity.
- "Book Now" button on each room.

### `book.php` — **THE MOST IMPORTANT FILE**

This is the file your lecturer will probe hardest. Here's how it works step by step:

1. Customer chooses check-in and check-out dates.
2. Server validates: dates not in past, check-out after check-in.
3. **Starts a database transaction** with `$pdo->beginTransaction()`.
4. Runs a SELECT query with `FOR UPDATE` — this **locks** the relevant booking rows so no other transaction can touch them.
5. The query checks if ANY existing booking overlaps the new dates.
6. **If overlap found** → `rollBack()` and show error "Room not available."
7. **If no overlap** → INSERT the booking and `commit()`.

#### The overlap rule (memorize this!)

Two date ranges overlap if and only if:
```
existing.check_in  <  new.check_out
AND
existing.check_out >  new.check_in
```

Imagine a number line from May 1 → May 31. If you draw existing booking May 10–May 15 and new booking May 14–May 18, they overlap. The formula catches it because:
- Existing check-in (May 10) < new check-out (May 18) ✓
- Existing check-out (May 15) > new check-in (May 14) ✓

If both conditions are true → conflict. If either is false → safe.

> **If asked "What if two people book at the EXACT same second?"** → "I use `FOR UPDATE` inside a transaction. MySQL locks the rows in the bookings table during the read, so the second request waits until the first transaction commits or rolls back. Only one booking will be allowed through."

### `my_bookings.php` — Customer history
Shows all bookings for the logged-in user with status badges (confirmed, cancelled, etc.) and a Cancel button for active bookings.

### `manager_dashboard.php` — Hotel manager view
- Shows manager's hotels, total bookings, and revenue.
- Lists recent bookings from customers.
- Links to manage rooms per hotel.

### `manage_rooms.php` — Add/remove rooms
A form to add new rooms (type, price, capacity, description) and a table of existing rooms with a Delete button.

> **Important security check:** the file verifies that the hotel belongs to the logged-in manager before showing anything. You CAN'T edit someone else's hotel by guessing the URL.

### `admin_dashboard.php` — System overview
Shows everything: total users, total hotels, total bookings, total revenue, recent activity, and a list of all users.

### `logout.php` — Ends the session
`session_destroy()` clears the session and redirects to login.

---

## 3. THE DATABASE EXPLAINED

### Table: `users`
Stores everyone — customers, managers, admins. The `role` column distinguishes them.

| Column | Type | Purpose |
|--------|------|---------|
| id | INT (PK) | Unique identifier |
| name | VARCHAR(100) | Display name |
| email | VARCHAR(150) UNIQUE | Login email |
| password | VARCHAR(255) | Bcrypt hash (never plain text!) |
| phone | VARCHAR(20) | Contact (optional) |
| role | ENUM('customer','manager','admin') | Permission level |
| created_at | TIMESTAMP | When they joined |

### Table: `hotels`
Each hotel belongs to ONE manager. `manager_id` references `users(id)`.

### Table: `rooms`
Each room belongs to ONE hotel. `hotel_id` references `hotels(id)`. The `is_active` flag allows "soft delete" — a manager can hide a room without losing its booking history.

### Table: `bookings`
The heart of the system. Each booking links a user to a room with dates.
- `user_id` references `users(id)`
- `room_id` references `rooms(id)`
- `status` tracks the booking lifecycle

**Important CHECK constraint:** `CHECK (check_out > check_in)` — the database itself refuses to store impossible dates.

**Important INDEX:** `idx_bookings_room_dates ON bookings(room_id, check_in, check_out, status)` — speeds up availability lookups (the most common query).

### Why the design is "normalized"

We don't repeat data. The hotel name lives in `hotels` table only — not duplicated in every room or booking. If a hotel renames itself, we change one row, not thousands. This is called **Third Normal Form (3NF)**, and it's what good database design looks like.

---

## 4. KEY CONCEPTS YOU NEED TO EXPLAIN

These are concepts the lecturer might test. Read them until you can explain each in your own words.

### 4.1 What is a "prepared statement"?

A prepared statement separates the SQL query from the data. Bad way:

```php
// DANGEROUS — never do this!
$query = "SELECT * FROM users WHERE email = '$email'";
```

If a hacker types `' OR '1'='1` as the email, the query becomes `SELECT * FROM users WHERE email = '' OR '1'='1'` which returns ALL users. This is **SQL injection**.

Good way (prepared statement):

```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

Here `?` is a placeholder. The database treats the user's input as data, not code. **Even if they type SQL keywords, nothing executes.** Every database query in your project uses prepared statements.

### 4.2 What is `password_hash()` and `password_verify()`?

`password_hash('mypass', PASSWORD_DEFAULT)` produces something like:
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

That's a bcrypt hash with a random "salt" baked in. Even if two users have the same password, their hashes look different.

To check a password later: `password_verify('mypass', $stored_hash)` returns `true` or `false`. You **cannot** reverse a hash to get the original password — that's the whole point.

### 4.3 What is a transaction?

A transaction groups multiple SQL operations into one atomic unit. Either ALL of them succeed or NONE of them happen.

**Example without transaction (bad):**
1. Subtract 100,000 TZS from account A → succeeds
2. Server crashes
3. Add 100,000 TZS to account B → never happens
4. **Result: 100,000 TZS vanished**

**With transaction:**
1. `BEGIN TRANSACTION`
2. Subtract from A
3. Server crashes
4. Transaction never committed → all changes reverted
5. **Result: nothing changed — safe**

In our system, the booking process is one transaction: check availability + insert booking either both succeed or both fail.

### 4.4 What is `FOR UPDATE` (row locking)?

`SELECT ... FOR UPDATE` tells MySQL: "I'm about to update something based on what I read. Lock these rows so nobody else can change them until I'm done."

**Without it:** Two requests could both read "room is free", then both insert bookings. Double booking!

**With it:** Request 1 reads + locks rows. Request 2 has to wait. After Request 1 commits the booking, Request 2 reads again and sees the new booking → returns "not available."

### 4.5 What is a session?

HTTP is "stateless" — the server doesn't remember you between page loads. Sessions fix that:

1. User logs in → server creates a session and gives the browser a cookie with a session ID.
2. On every later request, the browser sends that cookie.
3. The server uses the ID to look up the session data (user_id, role, etc.).

In PHP: `$_SESSION['user_id'] = 4;` saves it; on the next page, `$_SESSION['user_id']` still equals 4.

### 4.6 What is XSS and how do you prevent it?

**Cross-Site Scripting (XSS):** A hacker submits a hotel description like `<script>steal_cookies()</script>`. If you display it raw, every visitor runs that script.

**Prevention:** Escape all output with `htmlspecialchars()`. In your code, the `safe()` helper function does this. Every `<?= safe($variable) ?>` in templates is protecting against XSS.

### 4.7 What is "role-based access control" (RBAC)?

Different users have different permissions:
- A customer **cannot** access `manage_rooms.php`.
- A manager **cannot** access `admin_dashboard.php`.
- Even with the URL, the `require_role()` helper kicks them back to login.

This is RBAC — controlling access based on the user's role.

---

## 5. ANTICIPATED LECTURER QUESTIONS & ANSWERS

### Q: "Why did you choose the Waterfall model?"
**A:** "Because the requirements are well-defined and stable — I know exactly what the system needs to do from the start. As a solo developer, Waterfall gives me clear sequential phases I can plan around. Agile is better when requirements change frequently or when working in a team where iterative feedback is needed."

### Q: "What happens if the customer enters check-out before check-in?"
**A:** "I have validation at three levels: (1) HTML5 sets the `min` attribute on the date field; (2) PHP code rejects it before the database call; (3) the database itself has a CHECK constraint `check_out > check_in`. Defense in depth."

### Q: "What if the database goes down during a booking?"
**A:** "The transaction will fail and `rollBack()` is called in my `catch` block. No partial data is saved — either the booking is fully recorded or nothing changes. The customer sees an error message and can try again."

### Q: "How is your system different from Booking.com?"
**A:** "Three differences: First, scale — Booking.com serves the world; mine focuses on small/medium hotels in Tanzania that can't afford international fees. Second, integration — Phase 2 will add M-Pesa, Tigo Pesa, and Airtel Money, which are dominant payment methods in Tanzania but absent from international platforms. Third, control — local hotels keep their own customer relationships, no commission to a foreign company."

### Q: "Why MySQL and not MongoDB or PostgreSQL?"
**A:** "MySQL is free, mature, and supported by every shared web host in Tanzania. My data is highly relational — users, hotels, rooms, bookings all connected by foreign keys — so a relational database is ideal. MongoDB is for unstructured data, which I don't have."

### Q: "What is the most challenging part of the project?"
**A:** "Handling concurrent bookings. If two customers click 'Book' at the same second for the same room, naive code would create a double booking. I solved this with a database transaction using `SELECT ... FOR UPDATE` which locks the relevant rows until my booking either commits or rolls back. It guarantees correctness even under high load."

### Q: "How would you scale this to 1 million users?"
**A:** "Three changes: (1) Move from shared hosting to a dedicated server or cloud (AWS, DigitalOcean). (2) Add caching with Redis for hotel listings since they change rarely but are read often. (3) Use a load balancer to distribute traffic across multiple PHP servers. The database might need read replicas for search queries."

### Q: "What are the limitations of your system?"
**A:** "Three honest limitations: (1) No online payment yet — customers pay on arrival, which is a known scope decision and Phase 2 deliverable. (2) Single language (English) — Swahili support would help local market adoption. (3) No automated email/SMS confirmations — currently the customer just sees a confirmation page. All three are in my Phase 2 roadmap."

### Q: "How did you test the system?"
**A:** "Four levels: (1) Unit testing of individual functions like price calculation. (2) Integration testing — booking flow end-to-end. (3) User acceptance testing — I had 3 classmates use it and gave feedback. (4) Security testing — I tried SQL injection attempts like `' OR '1'='1` in the login form, and they were all blocked by my prepared statements."

### Q: "Why didn't you use a framework like Laravel?"
**A:** "Two reasons. First, learning value — building from scratch made me understand what frameworks do internally, which is more educational for an academic project. Second, simplicity — Laravel adds many files and concepts (Eloquent ORM, Blade templates, Composer dependencies) which aren't necessary for a system this size. Pure PHP keeps the code transparent and easy to deploy."

### Q: "What if I delete a hotel? What happens to its rooms and bookings?"
**A:** "Because of `ON DELETE CASCADE` in my foreign keys, deleting a hotel automatically deletes its rooms, and deleting a room deletes its bookings. This is intentional — orphan rooms make no sense. However, in production I'd recommend soft-delete (an `is_deleted` flag) so we keep records for accounting."

---

## 6. THE STEP-BY-STEP DEMO SCRIPT

Practice this exactly before Monday. Total time: 8–10 minutes.

**Setup (do BEFORE the lecturer arrives):**
1. Start XAMPP (Apache + MySQL).
2. Open phpMyAdmin → import `database.sql`.
3. Copy all `.php` files to `htdocs/hotel/`.
4. Open browser to `http://localhost/hotel/index.php`.
5. Open 3 browser windows: one for customer, one for manager, one for admin.

**Demo flow:**

**Step 1 — "Let me show you the customer experience."**
- Show home page with 3 hotels.
- Type "Arusha" in the search box → 2 hotels appear.
- Click "View Rooms" on Mount Meru Hotel → show rooms with prices.

**Step 2 — "Now I'll register and book a room."**
- Click Register → fill form → submit.
- Login as the new user.
- Click Book Now on Double Deluxe.
- Pick May 25 to May 28 → submit.
- Show the success message and Booking ID.

**Step 3 — "Here's the critical demo — booking conflict prevention."**
- Without logging out, try to book the SAME room for the SAME dates.
- The system shows: "Sorry, this room is already booked for those dates."
- **Say:** "This is enforced by a database transaction with row-level locking. Even if two customers click 'Book' at the exact same millisecond, only one will succeed."

**Step 4 — "Now the manager's view."**
- Log out → log in as `mgr1@hotel.com`.
- Show the manager dashboard with stats and recent bookings.
- Show that the new booking appears in the list.
- Click "Manage Rooms" → add a new room → show it appears.

**Step 5 — "Finally, the admin view."**
- Log out → log in as `admin@hotel.com`.
- Show all users, all hotels, system-wide stats, and revenue.

**Step 6 — Open phpMyAdmin to show the database.**
- Show the `users` table → note the bcrypt hashes (passwords are NOT plain text).
- Show the `bookings` table with the new entry.
- **Say:** "All passwords are hashed with bcrypt. Even if someone steals my database, they cannot read the original passwords."

---

## 7. WHAT TO FIX BEFORE MONDAY

Go through this checklist:

- [ ] **Database imported and working** — test login with sample users.
- [ ] **All 5 sample users can log in** (admin@hotel.com, mgr1@hotel.com, mgr2@hotel.com, judith@gmail.com, john@gmail.com — password for all: `password123`).
- [ ] **At least 3 hotels and 5 rooms** in the database (already seeded).
- [ ] **One existing booking** so the conflict demo works (already seeded for room_id=2, dates 2026-06-01 to 2026-06-03).
- [ ] **All PHP files in `htdocs/hotel/`** and accessible via `localhost/hotel/`.
- [ ] **Mockups printed** or on screen as backup if XAMPP fails.
- [ ] **Backup zip on flash drive AND email/Google Drive.**
- [ ] **Practice the demo at least twice** with a timer.
- [ ] **Updated DFD diagram** drawn cleanly in Draw.io (your current proposal has a messy one).
- [ ] **Print 1 hard copy of the proposal** and the diagrams.

---

## 8. DAY-OF-PRESENTATION TIPS

1. **Arrive 20 minutes early.** Test the projector. Check Wi-Fi (or use mobile hotspot).
2. **Dress smartly.** First impressions matter.
3. **Speak slowly.** Nervous students speak fast and lose marks.
4. **Make eye contact** with the lecturer, not the screen.
5. **When you don't know an answer:** Say "That's an excellent point, I would investigate further by [reasonable approach]" — don't bluff, don't apologize excessively.
6. **End strong.** After Q&A, thank the lecturer and offer to share the code.
7. **If something crashes during the demo:** Stay calm. Say "Let me show you the mockups instead while I restart that" — this is why you have backups.

---

## 9. SECURITY FEATURES (Lecturer LOVES these)

If you have time, mention these explicitly:

| Threat | Defense |
|--------|---------|
| SQL Injection | Prepared statements (PDO with `?` placeholders) |
| XSS attacks | `htmlspecialchars()` on all output via `safe()` |
| Password theft | bcrypt hashing with random salt |
| Session hijacking | `session_regenerate_id()` after login (could add) |
| Unauthorized access | `require_role()` on protected pages |
| Race conditions / double booking | Transactions + `FOR UPDATE` row locking |
| Brute force login | Same error message for "wrong email" or "wrong password" |
| Bad data | Server-side validation + database CHECK constraints |

---

## 10. ONE-LINE SUMMARY

If you forget everything else, remember this:

> "I built a multi-tier PHP/MySQL web system with role-based access for customers, hotel managers, and admins, where the core business rule — preventing double bookings — is enforced by a database transaction with row-level locking, ensuring data integrity even under concurrent load."

That single sentence shows the lecturer you understand: architecture, business logic, security, and concurrency. Memorize it.

---

**Good luck on Monday. You've got this.**
