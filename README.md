# QUIZER CBT System

QUIZER is a PHP/MySQL computer-based test app for a Sunday School quiz competition. Candidates register with their name, zone, quiz category, phone number, and email, then sign in using email plus phone number as the password.

## Features

- Candidate registration and login
- Adult and YAYA quiz categories
- Timed quiz attempts with automatic submission on timeout
- Candidate result page
- Admin dashboard with summary metrics
- Manual question entry
- Bulk question upload with an Excel-friendly CSV template
- Auto-refreshing progress and results page
- Results export to CSV

## Setup

1. Create the database and tables by importing `database/schema.sql` into MySQL.
2. Confirm database credentials in `config/database.php`.
3. Start PHP from the project root:

```bash
php -S localhost:8000
```

4. Open `http://localhost:8000`.

## Default Admin

- Email: `admin@quizer.local`
- Password: `admin123`

Change this password before using the system in a real competition.

## Bulk Upload Format

Download `templates/questions_template.csv` from the admin question page, edit it in Excel, and save as CSV. Keep the header row unchanged:

```csv
quiz_type,question_text,option_a,option_b,option_c,option_d,correct_option,points
```

`quiz_type` must be `Adult` or `YAYA`. `correct_option` must be `A`, `B`, `C`, or `D`.
