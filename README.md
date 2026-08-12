# MovieMine

Track what you watch - a movie library app with watchlists, watched status, and 10-point ratings.

**Repos**

- Backend (this repo): `malchyrus/moviemine-be`
- Frontend: `malchyrus/moviemine`

**Stack**

| Layer    | Tech                                       |
| -------- | ------------------------------------------ |
| Frontend | React + Vite + Tailwind CSS v4 + Framer Motion |
| Backend  | Laravel 12 (API only)                      |
| Database | Neon (PostgreSQL)                          |
| Hosting  | Railway (backend) + Vercel (frontend)      |

## Architecture

- The frontend calls this backend at `/api/*`. TMDB is never called from the browser - the backend proxies it (`app/Http/Controllers/Api/TmdbController.php`) so the API key stays server-side.
- Movies are cached in a `movies` table. Users search or add a movie -> it is upserted into the cache once, then referenced by `movie_id` everywhere else (no repeated `tmdb_id` columns).
- Multi-user auth via Laravel Sanctum: register/login/logout, token returned to the frontend and sent as `Authorization: Bearer <token>`. Every library/review/list endpoint resolves the user from the token. The `DatabaseSeeder` still creates a demo user (`demo@cinetrack.app` / `password`) for quick local testing.

## Database schema

All tables are created by Laravel migrations in `database/migrations/`:

- `users` - id, name, username, email, password, avatar, bio
- `movies` - movie cache (tmdb_id unique, title, poster_path, backdrop_path, release_date, vote_average, media_type)
- `watchlists` - user_id, movie_id, status (planning/watching/completed/dropped/on_hold), progress, rating (0-10), favorite, rewatch_count, watched_at
- `reviews` - user_id, movie_id, review, contains_spoilers
- `custom_lists` - user_id, name, description, is_public
- `custom_list_movies` - list_id, movie_id

Unique constraints prevent duplicates (`user_id + movie_id` in watchlists and reviews, `list_id + movie_id` in custom_list_movies).

## Prerequisites

- PHP >= 8.2 with `pdo_pgsql` + `pgsql` extensions enabled
- Composer 2
- Node 18+
- A free TMDB API key: https://www.themoviedb.org -> Settings -> API

## Run locally

### Backend (this repo)

```bash
cp .env.example .env          # fill DB_URL (Neon) + TMDB_API_KEY
composer install
php artisan key:generate
php artisan migrate --seed    # creates tables + demo user (demo@cinetrack.app / password)
php artisan serve             # http://localhost:8000
```

On Windows/Laragon, enable the PostgreSQL drivers in `php.ini` (uncomment `extension=pgsql` and `extension=pdo_pgsql`).

> Local SSL note: if the TMDB proxy fails with `cURL error 60: unable to get local issuer certificate`, your machine's TLS is likely intercepted (antivirus/proxy). Set `TMDB_VERIFY_SSL=false` in `.env` for local dev only - keep it `true` in production.

### Frontend (separate repo)

```bash
git clone https://github.com/Malchyrus/moviemine.git
cd moviemine
npm install
npm run dev                   # http://localhost:5173
```

The Vite dev server proxies `/api` to `http://localhost:8000` automatically (see `vite.config.js`).

Health check: `curl http://localhost:8000/api/health`

## Deploy

### 1. Neon (database)

1. Create a free project at https://neon.tech
2. Copy the connection string from Connection Details -> Node.js
3. Example: `postgresql://user:pass@ep-xxx.region.aws.neon.tech/cinetrack?sslmode=require`
4. Tables are created by `php artisan migrate` on Railway (see below).

### 2. Railway (backend)

`railway.toml` in this repo configures the build (`composer install`) and start (`php artisan migrate --force && php artisan serve`).

1. Railway Dashboard -> New Project -> Deploy from GitHub -> `malchyrus/moviemine-be`
2. Add environment variables:
   - `APP_KEY` (generate with `php artisan key:generate`)
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `DB_URL` (Neon connection string)
   - `DB_SSLMODE=require`
   - `DB_ENDPOINT_ID` (first part of the Neon host, e.g. `ep-abc123`)
   - `TMDB_API_KEY`
   - `TMDB_VERIFY_SSL=true`
   - `LOG_CHANNEL=stderr`
3. Deploy. Note its URL, e.g. `https://moviemine-production.up.railway.app`

### 3. Vercel (frontend)

1. Import `malchyrus/moviemine` into Vercel (auto-detects Vite).
2. Add environment variable `VITE_API_URL=https://moviemine-production.up.railway.app`
3. Deploy. `vercel.json` provides the SPA rewrite so client-side routes work.

## Health check

`curl https://<railway-url>/api/health`

Railway services run continuously - no keep-alive is required. (Optional: `workflows/keep-alive.yml` in the frontend repo pings `/api/health` every 12 minutes if you set the `BACKEND_URL` repository variable to the Railway URL.)

## API endpoints

| Method | Path                        | Auth    | Description                 |
| ------ | --------------------------- | ------- | --------------------------- |
| POST   | `/api/auth/register`        |         | Register + get token        |
| POST   | `/api/auth/login`           |         | Login + get token           |
| POST   | `/api/auth/logout`          | Bearer  | Revoke current token        |
| GET    | `/api/auth/me`              | Bearer  | Current user                |
| GET    | `/api/health`               |         | Health check                |
| GET    | `/api/movies`               | Bearer  | List saved movies           |
| POST   | `/api/movies`               | Bearer  | Save a movie (caches it)    |
| PATCH  | `/api/movies/{tmdbId}`      | Bearer  | Update watched / rating     |
| DELETE | `/api/movies/{tmdbId}`      | Bearer  | Remove a movie              |
| GET    | `/api/reviews`              | Bearer  | List reviews                |
| POST   | `/api/reviews`              | Bearer  | Create / update a review    |
| DELETE | `/api/reviews/{tmdbId}`     | Bearer  | Delete a review             |
| GET    | `/api/lists`                | Bearer  | List custom lists           |
| POST   | `/api/lists`                | Bearer  | Create a list               |
| DELETE | `/api/lists/{id}`           | Bearer  | Delete a list               |
| POST   | `/api/lists/{id}/movies`    | Bearer  | Add a movie to a list       |
| DELETE | `/api/lists/{id}/movies/{t}`| Bearer  | Remove a movie from a list  |
| GET    | `/api/tmdb/trending`        |         | Trending movies             |
| GET    | `/api/tmdb/popular`         |         | Popular movies              |
| GET    | `/api/tmdb/upcoming`        |         | Upcoming movies             |
| GET    | `/api/tmdb/top-rated`       |         | Top rated movies            |
| GET    | `/api/tmdb/search?q=`       |         | Search movies               |
| GET    | `/api/tmdb/movie/{id}`      |         | Movie details + credits     |
| GET    | `/api/tmdb/genres`          |         | Genre list                  |

Unauthenticated requests to protected routes return `401 {"error":"Unauthenticated"}`. Invalid login returns `401 {"error":"Invalid credentials"}`. TMDB routes are public.

## Next steps

- Wire the frontend to the `reviews` and `custom_lists` endpoints (backend + models + routes are ready).
