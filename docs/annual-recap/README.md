# Annual Recap Roll-Forward

Use this checklist each year when adding the next recap (example: 2026).

1) Create the view
- Copy the previous year's Blade view into a new year folder.
- Example: copy `resources/views/annual-recap/2025/show.blade.php` to
  `resources/views/annual-recap/2026/show.blade.php`.

2) Update year-specific copy and visuals
- Adjust titles, headings, and any date-specific language in the new view.
- Update any year-specific art, themes, or seasonal references as needed.

3) Verify auto-discovery
- `AnnualRecapController` auto-discovers recap years by numeric folder name
  and `show.blade.php`, so no route/controller changes are needed.

4) Seed or create data for local testing
- Create reading logs dated in the new year so the recap has data to render.
- Example: use a factory or seeder to add logs in 2026.

5) Confirm dashboard card visibility window
- The recap card shows from Dec 1 through Jan 7 for the corresponding year.
- During early January, the card should still point to the prior year's recap.
