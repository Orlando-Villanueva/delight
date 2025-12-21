# GEMINI.md

## Project Overview

This is a Laravel project called "Delight," a web application designed to help users build and maintain a consistent Bible reading habit. It features daily reading logs, streak tracking, visual progress indicators, and basic statistics.

The project follows a service layer architecture, with a clear separation of concerns between controllers and services. The frontend is built with HTMX and Alpine.js for server-driven interactivity, and styled with Tailwind CSS.

**Key Technologies:**

*   **Backend:** Laravel (PHP)
*   **Frontend:** HTMX, Alpine.js, Tailwind CSS, Vite
*   **Database:** SQLite (local), PostgreSQL (production)
*   **Authentication:** Laravel Fortify
*   **Testing:** Pest

## Building and Running

### Prerequisites

*   PHP 8.2+
*   Composer
*   Node.js and npm
*   SQLite

### Local Development

1.  **Install Dependencies:**
    ```bash
    composer install
    npm install
    ```

2.  **Set up Environment:**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3.  **Create SQLite Database:**
    ```bash
    touch database/database.sqlite
    ```
    Update your `.env` file with the absolute path to the `database.sqlite` file.

4.  **Run Migrations and Seeders:**
    ```bash
    php artisan migrate --seed
    ```

5.  **Build Frontend Assets:**
    ```bash
    npm run dev
    ```

6.  **Run the Application:**
    This project is set up to use Laravel Herd. Once you have placed the project in your Herd directory, it will be available at `http://delight.test`.

### Testing

Run the test suite with:

```bash
php artisan test
```

## Development Conventions

*   **Service Layer:** Business logic is encapsulated in service classes within the `app/Services` directory. Controllers should be lean and delegate to these services.
*   **HTMX:** The application uses HTMX for partial page updates. Controllers return full views for initial requests and partial views for HTMX requests, detected by the `HX-Request` header.
*   **Styling:** Tailwind CSS is used for styling.
*   **Testing:** The project uses Pest for testing. Tests are located in the `tests` directory.
