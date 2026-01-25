# Reading Plans

This directory contains documentation and resources for the Reading Plans feature.

## Overview

Reading plans provide structured daily Bible reading schedules. Users can subscribe to a plan and track their progress day-by-day.

### Current Architecture

- **Multiple concurrent plans**: Users can subscribe to multiple reading plans simultaneously and switch between them at any time
- **Day-based progression**: Users complete all chapters in Day N before advancing to Day N+1
- **Flexible pacing**: Users can read ahead, but the "current day" only advances when completed
- **Independent progress**: Each plan tracks its own progress separately

### Routes

| Route                               | Description                           |
| ----------------------------------- | ------------------------------------- |
| `GET /plans`                        | List all available plans              |
| `GET /plans/{slug}/today`           | Today's reading for a specific plan   |
| `POST /plans/{slug}/subscribe`      | Subscribe to a plan (sets as active)  |
| `DELETE /plans/{slug}/unsubscribe`  | Unsubscribe from a plan               |
| `POST /plans/{slug}/activate`       | Resume/activate a paused subscription |
| `POST /plans/{slug}/log-chapter`    | Log a single chapter                  |
| `POST /plans/{slug}/log-all`        | Mark entire day as complete           |
| `POST /plans/{slug}/apply-readings` | Apply unlinked readings to plan       |

---

## Adding a New Reading Plan

### Step 1: Create the CSV File

Create a new CSV file in `database/data/reading-plans/`:

```csv
"Date","Passage"
"2026-01-01","Gen 1-3"
"2026-01-02","Gen 4-7"
"2026-01-03","Gen 8-11"
...
```

**Format rules:**

- Header row: `"Date","Passage"`
- Date column: Any date format (only used for row ordering, not stored)
- Passage column: Book abbreviation + chapter range(s)

**Supported book abbreviations:**

| Abbr  | Book        | Abbr  | Book            | Abbr | Book      |
| ----- | ----------- | ----- | --------------- | ---- | --------- |
| Gen   | Genesis     | 1 Chr | 1 Chronicles    | Hos  | Hosea     |
| Ex    | Exodus      | 2 Chr | 2 Chronicles    | Joe  | Joel      |
| Lev   | Leviticus   | Ezr   | Ezra            | Amo  | Amos      |
| Num   | Numbers     | Neh   | Nehemiah        | Oba  | Obadiah   |
| Deut  | Deuteronomy | Est   | Esther          | Jon  | Jonah     |
| Josh  | Joshua      | Job   | Job             | Mic  | Micah     |
| Jdg   | Judges      | Ps    | Psalms          | Nah  | Nahum     |
| Rut   | Ruth        | Pro   | Proverbs        | Hab  | Habakkuk  |
| 1 Sa  | 1 Samuel    | Ecc   | Ecclesiastes    | Zep  | Zephaniah |
| 2 Sa  | 2 Samuel    | Sos   | Song of Solomon | Hag  | Haggai    |
| 1 Kgs | 1 Kings     | Isa   | Isaiah          | Zec  | Zechariah |
| 2 Kgs | 2 Kings     | Jer   | Jeremiah        | Mal  | Malachi   |
|       |             | Lam   | Lamentations    |      |           |
|       |             | Eze   | Ezekiel         |      |           |
|       |             | Dan   | Daniel          |      |           |

| Abbr | Book            | Abbr | Book       |
| ---- | --------------- | ---- | ---------- |
| Mat  | Matthew         | 1 Ti | 1 Timothy  |
| Mk   | Mark            | 2 Ti | 2 Timothy  |
| Luk  | Luke            | Tit  | Titus      |
| John | John            | Phlm | Philemon   |
| Acts | Acts            | Heb  | Hebrews    |
| Rom  | Romans          | Jam  | James      |
| 1 Co | 1 Corinthians   | 1 Pe | 1 Peter    |
| 2 Co | 2 Corinthians   | 2 Pe | 2 Peter    |
| Gal  | Galatians       | 1 Jn | 1 John     |
| Eph  | Ephesians       | 2 Jn | 2 John     |
| Phil | Philippians     | 3 Jn | 3 John     |
| Col  | Colossians      | Jude | Jude       |
| 1 Th | 1 Thessalonians | Rev  | Revelation |
| 2 Th | 2 Thessalonians |      |            |

**Passage format examples:**

```csv
"Gen 1-3"           # Single book, chapter range
"Gen 50"            # Single book, single chapter
"Gen 50; Ex 1-3"    # Multiple books (semicolon-separated)
"Ps 1, 3-5"         # Non-consecutive chapters (comma-separated)
```

### Step 2: Update the Seeder

Edit `database/seeders/ReadingPlanSeeder.php` to add your new plan.

**Option A: Add to existing seeder**

```php
public function run(): void
{
    // Existing plan
    $this->seedPlan(
        'standard-canonical',
        'Read the Bible in a Year',
        'A 365-day journey through the entire Bible in canonical order.',
        'standard-canonical.csv'
    );

    // Your new plan
    $this->seedPlan(
        'chronological',
        'Chronological Bible',
        'Read the Bible in the order events occurred historically.',
        'chronological.csv'
    );
}

private function seedPlan(string $slug, string $name, string $description, string $csvFile): void
{
    $csvPath = database_path("data/reading-plans/{$csvFile}");

    if (! file_exists($csvPath)) {
        $this->command->error("CSV file not found: {$csvPath}");
        return;
    }

    $days = $this->parseCsv($csvPath);

    ReadingPlan::updateOrCreate(
        ['slug' => $slug],
        [
            'name' => $name,
            'description' => $description,
            'days' => $days,
            'is_active' => true,
        ]
    );

    $this->command->info("Seeded {$name} with " . count($days) . ' days.');
}
```

**Option B: Create a separate seeder**

```bash
php artisan make:seeder ChronologicalPlanSeeder
```

### Step 3: Run the Seeder

```bash
# Run all seeders (will update existing plans)
php artisan db:seed --class=ReadingPlanSeeder

# Or run just your new seeder
php artisan db:seed --class=ChronologicalPlanSeeder
```

### Step 4: Verify

1. Visit `/plans` and confirm your new plan appears
2. Subscribe to the plan
3. Verify `/plans/{your-slug}/today` shows the correct Day 1 reading
4. Test chapter logging and day progression
5. Verify pausing/resuming works if you have multiple subscriptions

---

## Data Model

### `reading_plans` table

| Column      | Type    | Description                        |
| ----------- | ------- | ---------------------------------- |
| id          | bigint  | Primary key                        |
| slug        | string  | URL-friendly identifier (unique)   |
| name        | string  | Display name                       |
| description | text    | Plan description                   |
| days        | json    | Array of day objects (see below)   |
| is_active   | boolean | Whether plan is available to users |
| timestamps  |         | Created/updated timestamps         |

### Days JSON Structure

```json
[
  {
    "day": 1,
    "label": "Genesis 1-3",
    "chapters": [
      { "book_id": 1, "book_name": "Genesis", "chapter": 1 },
      { "book_id": 1, "book_name": "Genesis", "chapter": 2 },
      { "book_id": 1, "book_name": "Genesis", "chapter": 3 }
    ]
  },
  {
    "day": 2,
    "label": "Genesis 4-7",
    "chapters": [...]
  }
]
```

### `reading_plan_subscriptions` table

| Column          | Type      | Description                            |
| --------------- | --------- | -------------------------------------- |
| id              | bigint    | Primary key                            |
| user_id         | foreignId | User who subscribed                    |
| reading_plan_id | foreignId | The plan                               |
| started_at      | date      | When user started                      |
| is_active       | boolean   | Whether this is the user's active plan |
| timestamps      |           | Created/updated timestamps             |

> **Note:** Only one subscription per user can have `is_active = true` at a time. Subscribing to a new plan automatically deactivates other subscriptions. Progress is calculated dynamically from `reading_plan_day_completions` entries.

### `reading_plan_day_completions` table (Junction)

This junction table links reading logs to plan subscriptions, enabling the same chapter to count toward multiple plans.

| Column                       | Type      | Description                         |
| ---------------------------- | --------- | ----------------------------------- |
| id                           | bigint    | Primary key                         |
| reading_log_id               | foreignId | The reading log entry               |
| reading_plan_subscription_id | foreignId | The subscription this counts toward |
| reading_plan_day             | smallint  | Which day of the plan (1-365)       |
| timestamps                   |           | Created/updated timestamps          |

**Key constraints:**

- Unique index on `(reading_log_id, reading_plan_subscription_id)` — a log can only link to a subscription once
- Cascading deletes — removing a subscription removes all completion links

---

## Future Considerations

### Plan Management

Current enhancements to consider:

1. **"My Plans" dashboard** - Dedicated page showing all subscribed plans with progress overview
2. **Plan completion celebration** - Special screen/animation when a plan is completed
3. **Plan statistics** - Show completion rate, average daily reading time, etc.
4. **Plan reminders** - Optional notifications for uncompleted daily readings

### Plan Variants

For plans with different pacing (90-day, 180-day, 365-day versions of the same content):

- Create separate CSV files for each variant
- Use descriptive slugs: `chronological-90`, `chronological-365`
- Consider a `plan_type` or `category` field for grouping

---

## Existing Plans

| Slug                 | Name                       | Days | Description                                               |
| -------------------- | -------------------------- | ---- | --------------------------------------------------------- |
| `standard-canonical` | Canonical Reading Plan     | 365  | Traditional order, Genesis to Revelation                  |
| `chronological`      | Chronological Reading Plan | 366  | Events in historical order, from Creation to Early Church |

---

## Troubleshooting

### CSV parsing errors

- Check book abbreviations match the supported list exactly (case-sensitive)
- Ensure proper quoting: `"Date","Passage"`
- Verify chapter numbers are valid for each book

### Plan not appearing

- Confirm `is_active` is `true`
- Check seeder ran without errors: `php artisan db:seed --class=ReadingPlanSeeder`
- Clear any caches: `php artisan cache:clear`

### Day progression issues

- Users must complete ALL chapters in a day before advancing
- Progress is calculated from `reading_plan_day_completions` junction table entries
- Verify `reading_logs` table has entries and they're linked via `reading_plan_day_completions`

### Active/Paused subscription issues

- Only one subscription can be active at a time (`is_active = true`)
- Subscribing to a new plan automatically pauses other subscriptions
- Users can resume a paused plan via the "Resume Plan" button
- If a user unsubscribes from their active plan and only one paused subscription remains, it auto-activates
