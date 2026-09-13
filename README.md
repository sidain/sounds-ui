# Sounds UI

A small Laravel + Vue tool for browsing a local library of audio files, previewing and labeling them, and exporting a selected batch as OGG files ready to drop into the **MySharedMediaSounds** World of Warcraft addon.

## What it does

1. **Browse** — scans a configured `FILES/INPUT` directory (and its subfolders) and lists all audio files in a grid, with folder filters and lazy-loading as you scroll.
2. **Preview** — click to play/stop any file directly in the browser before deciding whether to keep it.
3. **Select & label** — check the files you want, optionally give each one a custom label.
4. **Submit** — selected files are sent to the backend, which:
   - copies them into a timestamped folder under `FILES/OUTPUT`
   - converts each to `.ogg` via `ffmpeg`, stripping metadata
   - writes a `files.txt` manifest of the resulting filenames
   - generates a `files.lua` snippet formatted as a Lua sound table, ready to paste into `MySharedMediaSounds`
   - zips the whole output folder for easy transfer

## Tech Stack

- **Backend:** PHP 8.1+, Laravel 10
- **Frontend:** Vue 3 (single component, `SoundManager.vue`), Vite
- **Conversion:** `ffmpeg` (external binary, called via `exec()`)

## Requirements

- PHP >= 8.1 and Composer
- Node.js & npm
- `ffmpeg` installed and available on the system (or point `FFMPEG_PATH` at it)
- A `FILES/INPUT` directory (at the project root) containing your source audio, organized into subfolders

## Setup

1. **Clone and install dependencies**
   ```bash
   git clone https://github.com/sidain/sounds-ui.git
   cd sounds-ui
   composer install
   npm install
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Add to `.env` if `ffmpeg` isn't on your PATH:
   ```
   FFMPEG_PATH=/path/to/ffmpeg
   ```

3. **Set up input/output folders**
   Create `FILES/INPUT` at the project root and populate it with subfolders of source audio (`.wav`, etc.). `FILES/OUTPUT` will be created automatically as you submit batches.

4. **Run the app**
   ```bash
   php artisan serve
   npm run dev
   ```
   Visit `http://localhost:8000`.

## API Routes

| Method | Route | Description |
|---|---|---|
| `GET` | `/api/input-files` | Lists directories/files under `FILES/INPUT` (initial load, capped batch) |
| `GET` | `/api/get-files?directory=...` | Lists files in a specific directory (for filtering/lazy load) |
| `GET` | `/api/play-audio?path=...` | Streams a single input file for in-browser preview |
| `POST` | `/api/submit-sounds` | Converts + packages the selected files (see "What it does" above) |

## Output Format

Each submission produces, inside `FILES/OUTPUT/<timestamp>/`:
- `*.ogg` — converted audio files
- `files.txt` — plain list of filenames
- `files.lua` — a Lua table matching the format `MySharedMediaSounds` expects
- `<timestamp>.zip` — everything above, zipped

## Notes

- This is a personal tool built around a specific `MySharedMediaSounds` workflow rather than a general-purpose audio converter — labels, categories, and paths in the generated Lua currently assume that addon's folder structure.
- No auth is applied to the file-management routes; run this locally rather than exposing it publicly as-is.