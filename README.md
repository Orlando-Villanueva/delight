# Delight

[![Laravel Forge Site Deployment Status](https://img.shields.io/endpoint?url=https%3A%2F%2Fforge.laravel.com%2Fsite-badges%2Fc25df3da-e1dc-4bd9-9f2c-2a0596933e0c%3Fdate%3D1%26label%3D1&style=plastic)](https://forge.laravel.com/servers/940835/sites/2789193)

A web application designed to help users build and maintain a consistent Bible reading habit through tracking, streaks, and visual progress indicators.

## Features

### MVP (Phase 1) Features
- **Daily Reading Log**: Track your daily Bible reading with a structured book/chapter selector
- **Streak Tracking**: Maintain reading streaks with a 1-day grace period
- **Book Completion Grid**: Visual representation of reading progress across all Bible books
- **Basic Statistics**: 
  - Current streak counter
  - All-time longest streak
  - Total chapters read
  - Books started vs. completed count
  - Calendar view of reading activity
- **Multilingual Support**: English and French language options
- **Caching**: Redis-based caching for frequently accessed data

### Post-MVP (Phase 2) Features
- Advanced statistics with weekly analysis
- Reading pattern insights
- Expanded visualizations
- Additional language support

## Technical Overview

- **Framework**: Laravel (PHP)
- **Architecture**: Service Layer Pattern for clean separation of concerns and high testability
- **Database**: 
  - **Local Development**: SQLite for simplicity and ease of setup
  - **Production**: Laravel Cloud's Serverless Postgres (PostgreSQL 17) with denormalized BookProgress table for efficient tracking
- **Frontend**: HTMX + Alpine.js for server-driven interactivity
- **Caching**: Redis (in production)
- **Bible Reference System**: Static configuration approach via config files
- **Internationalization**: Laravel's built-in localization system
- **Testing**: Comprehensive test suite covering critical components

## Setup Instructions

### Prerequisites
- PHP 8.1+
- Composer
- Node.js and npm
- SQLite (for local development)
- [Optional] Redis server (for production caching)

### Local Development with Laravel Herd

Laravel Herd is a fast, native Laravel development environment for macOS and Windows. It simplifies running Laravel projects locally without manual server configuration.

#### Installation

- **macOS:**  
  Download and install from [herd.laravel.com](https://herd.laravel.com/).
- **Windows:**  
  Download and install from [herd.laravel.com](https://herd.laravel.com/).

#### Usage

1. **Open Herd and add your project directory**  
   Click "Add Project" and select your cloned `biblehabit` directory.

2. **Set up your local domain (optional but recommended):**  
   Herd can automatically configure a `.test` domain (e.g., http://biblehabit.test).
   Update your `.env`:
   ```
   APP_URL=http://biblehabit.test
   ```

3. **Database Configuration:**  
   By default, local development uses SQLite. Ensure your `.env` has:
   ```
   DB_CONNECTION=sqlite
   DB_DATABASE=absolute_path_to_your_project/database/database.sqlite
   ```
   On Windows, use:
   ```
   DB_DATABASE=W:/Projects/Herd/biblehabit/database/database.sqlite
   ```

4. **Run migrations and seeders:**  
   ```bash
   php artisan migrate --seed
   ```

5. **Access your app:**  
   Open http://biblehabit.test in your browser.

#### Notes

- Herd automatically handles PHP versions and web server configuration.
- For more details, see the [Laravel Herd documentation](https://herd.laravel.com/docs).

### Installation

1. **Clone the repository**
   ```bash
   git clone [repository-url]
   cd biblehabit
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**
   ```bash
   npm install
   ```

4. **Set up SQLite database**
   ```bash
   # Create SQLite database file
   touch database/database.sqlite
   
   # Update .env for SQLite
   # DB_CONNECTION=sqlite
   # DB_DATABASE=/absolute/path/to/your/project/database/database.sqlite
   ```
   
   On Windows, use the full path in .env:
   ```
   DB_DATABASE=W:/Projects/Herd/biblehabit/database/database.sqlite
   ```

5. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

6. **Run migrations and seeders**
   ```bash
   php artisan migrate --seed
   ```

7. **Build frontend assets**
   ```bash
   npm run dev
   ```

8. **Access the application**
   ```bash
   php artisan serve
   ```
   - Access at http://localhost:8000
   - Or use Laravel Herd: open http://biblehabit.test (if configured)

## Production Setup (Laravel Cloud)

1. **Database**: Laravel Cloud automatically provisions a Serverless Postgres database (PostgreSQL 17)
2. **Environment Variables**: Database credentials are automatically injected
3. **Migrations**: Run migrations via Laravel Cloud's CLI or deployment pipeline
   ```bash
   php artisan migrate --force
   ```

## Local Development Tips

- **SQLite**: Default for local development (fast, file-based, no server required)
- **PostgreSQL**: Optional for local development (matches production environment)
  - Install PostgreSQL and update `.env` with PostgreSQL credentials
  - Run migrations after switching database types
- **Environment Files**: Keep sensitive credentials in `.env` (never commit this file)
- **Debugging**: Set `APP_DEBUG=true` in development for detailed error messages

### Additional Configuration

- **Redis Setup**: Ensure Redis server is running for caching functionality
- **Language Settings**: Default language can be configured in `config/app.php`
- **Bible Reference Data**: 
  - Configuration: `config/bible.php` (book structure, chapter counts)
  - Translations: `lang/en/bible.php`, `lang/fr/bible.php` (localized book names)

## Testing

Run the test suite with:
```bash
php artisan test
```

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.
