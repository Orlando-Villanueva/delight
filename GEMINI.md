# GEMINI.md

## Project Overview

"Delight" is a Laravel-based web application designed to help users build and maintain a consistent daily Bible reading habit. It focuses on visual progress tracking, daily reading logs, milestone streaks, and personal statistics.

_Note: For detailed development commands, UI design tokens, and git conventions, see `AGENTS.md`._

The project follows a **Service Layer Architecture**, ensuring controllers remain lean while business logic is centralized. The frontend uses a **Server-Driven Interactivity** model via HTMX, Alpine.js, and Blade Fragments, styled with Tailwind CSS and Flowbite.

**Key Technologies:**

-   **Backend:** Laravel 11.x (PHP 8.2+)
-   **Authentication:** Laravel Fortify (Passwords) & Laravel Socialite (Google/X OAuth)
-   **Frontend:** HTMX, Alpine.js, Tailwind CSS v4 (Vite), Vite
-   **UI Components:** Flowbite
-   **Database:** SQLite (local), PostgreSQL (production)
-   **Features:** PWA support, Announcement System, User Feedback, Weekly Journey tracking.
-   **Testing:** Pest

## Building and Running

### Prerequisites

-   PHP 8.2+
-   Composer
-   Node.js and npm
-   SQLite
-   [Mailpit](https://github.com/axllent/mailpit) (for local email testing)

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

3.  **Database & Data:**

    ```bash
    touch database/database.sqlite
    php artisan migrate --seed
    ```

    _Note: The seeder populates the base Bible structure and a rich set of dummy data for development._

4.  **Frontend Assets:**

    ```bash
    npm run dev
    ```

5.  **Run Tools:**
    -   App: `http://delight.test` (via Laravel Herd)
    -   Mail: `mailpit` (interface at `http://localhost:8025`)
    -   DB: `http://delight.test/telescope` (if enabled)

### Testing

Run the test suite with:

```bash
php artisan test
```

## Development Conventions

### 1. Service Layer

Business logic MUST live in `app/Services`. High-level workflows should be delegated to these services from controllers. Services should handle database interactions, calculations (e.g., streak ranges), and external integrations.

### 2. HTMX & UI Interactivity

The application uses HTMX for seamless, partial page updates.

-   **Macro:** Use `return response()->htmx('view.name', 'fragment-name', $data)` for partial refreshes.
-   **Fragments:** Define `@fragment('name') ... @endfragment` inside Blade files to allow targeted updates.
-   **Alpine.js:** Use for local UI state (modals, toggles, client-side filtering).

### 3. Styling & Components

-   **Tailwind CSS:** Primary styling engine.
-   **Flowbite:** Preferred library for UI components (Drawers, Modals, Navbars).
-   **Responsive:** Mobile-first design is critical as many users log readings on their phones.

### 4. Announcements & Notifications

The system features a native announcement engine.

-   **Admins:** Manage news via `admin/announcements`.
-   **Users:** Receive updates via the notification bell (mapped in `AppServiceProvider`).
-   **Persistence:** Read/Unread states are tracked per-user in the `announcement_user` table.

### 5. Testing

We use **Pest**.

-   Feature tests should cover high-level HTMX flows.
-   Unit tests should cover complex logic in services (especially Statistics and Streaks).

### 6. Code Interaction

-   **Respect Manual Changes:** NEVER remove code manually added by the user during a chat session unless explicitly instructed. If a manual change seems incorrect or conflicting, PROMPT the user for clarification before modifying or removing it.
