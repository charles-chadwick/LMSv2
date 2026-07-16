# LMS v2

A Learning Management System built with Laravel 13, Inertia.js v3, and Vue 3. Instructors author courses made of modules and lessons, students enroll and work through the material, submit assignments and tests, and earn certificates on completion. It includes discussions, direct messaging, and real-time notifications.

## Features

- **Roles** — Admin, Instructor, and Student, backed by [spatie/laravel-permission](https://spatie.be/docs/laravel-permission).
- **Courses** — organized into modules and lessons, with levels (Beginner / Intermediate / Advanced) and a publish/archive lifecycle.
- **Enrollment & progress** — students enroll in courses, complete lessons, and track progress toward completion.
- **Assessments** — assignments with submissions and grading, plus tests with questions, options, timed attempts, and automated scoring.
- **Certificates** — issued automatically when a student completes a course.
- **Communication** — course discussions with replies, private conversations/messaging, and real-time notifications over WebSockets.
- **Media** — file uploads and attachments via [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary).
- **Activity logging** — auditing via [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog).

## Tech Stack

| Layer          | Technology                                              |
| -------------- | ------------------------------------------------------- |
| Backend        | Laravel 13, PHP 8.4                                      |
| Frontend       | Inertia.js v3, Vue 3, Tailwind CSS v4, Vite             |
| Database       | MariaDB / MySQL                                          |
| Real-time      | Laravel Reverb (WebSockets)                              |
| Domain logic   | [lorisleiva/laravel-actions](https://laravelactions.com) (Action classes in `app/Actions`) |
| Testing        | Pest v4                                                  |

## Architecture Notes

- **Actions** — business logic lives in single-purpose Action classes under `app/Actions/` (e.g. `EnrollStudent`, `CompleteCourse`, `ScoreTestAttempt`), keeping controllers and models thin.
- **Enums** — domain values are enums under `app/Enums/` (e.g. `UserRole`, `CourseStatus`, `QuestionType`) — no magic strings.
- **Soft deletes** — models use soft deletes throughout.

## Requirements

- PHP **8.4+** with the usual Laravel extensions
- Composer 2
- Node.js **20+** and npm
- MariaDB or MySQL

## Local Installation


Log in with any of the users that get seeded.

1. **Clone and install dependencies**

   ```bash
   git clone git@github.com:charles-chadwick/LMSv2.git LMSv2
   cd LMSv2
   composer install
   npm install
   ```

2. **Set up the environment file**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Create the database and configure `.env`**

   Create a MariaDB/MySQL database, then set the connection values in `.env`:

   ```dotenv
   DB_CONNECTION=mariadb
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=_lms_v2
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Configure Reverb (WebSockets)**

   `BROADCAST_CONNECTION=reverb` is already set. Generate credentials and fill in the empty `REVERB_APP_*` values:

   ```bash
   php artisan reverb:install
   ```

   This populates `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET` (the `VITE_REVERB_*` values mirror these automatically).

5. **Run migrations and seeders**

   ```bash
   php artisan migrate --seed
   ```

6. **Start the development environment**

   ```bash
   composer run dev
   ```

   This runs the app server, queue worker, Reverb WebSocket server, log tailer (Pail), and Vite dev server together. Visit **http://localhost:8000**.

   Prefer separate terminals? Run them individually:

   ```bash
   php artisan serve
   php artisan queue:listen
   php artisan reverb:start
   npm run dev
   ```

## Testing

```bash
php artisan test
```

Run a subset with `--compact` and a filter:

```bash
php artisan test --compact --filter=EnrollStudent
```

## Code Style

Format PHP with Pint before committing:

```bash
vendor/bin/pint
```
