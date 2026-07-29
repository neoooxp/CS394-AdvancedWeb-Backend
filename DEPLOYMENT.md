# Laravel Backend API - DigitalOcean App Platform Deployment

This project is configured for containerized Web Service deployment on DigitalOcean App Platform.

## Quick Deploy Steps
1. Push this repository to GitHub/GitLab.
2. In DigitalOcean Control Panel, go to **Apps** -> **Create App**.
3. Select this repository. DigitalOcean will auto-detect `Dockerfile` and `.do/app.yaml`.
4. Configure your production environment secrets:
   - `APP_KEY` (Generate with `php artisan key:generate --show`)
   - `DB_URI` (Your MongoDB Atlas or DO Managed MongoDB URI)
5. Click **Deploy**.
