# Project AI Documentation

## What this project does

Project AI is a student-facing web tool that helps build academic projects step by step.

It lets students:
- sign up or log in,
- enter a project title and domain,
- get guided project ideas and methodology options,
- generate a detailed technical chapter,
- create a final academic report and presentation package,
- save and reuse project drafts.

The app is designed to support academic research and project planning with AI-driven content generation.

## Main functions

The system is split between frontend and backend. Here are the core functions:

### 1. User authentication and accounts
- `api/auth.php` handles login and signup.
- The app stores users in a local JSON database.
- There is a built-in admin user for management tasks.

### 2. Project management
- `api/projects.php` saves and loads project drafts.
- Users can keep a history of their project progress.
- Project state includes title, domain, selected problem, methodology, results, and report settings.

### 3. AI-guided project planning
- `api/get_phase_data.php` generates phase-based guidance:
  - phase 1: problem or research gap ideas,
  - phase 2: methodology and technical approach options,
  - phase 3: simulated results, charts, and architecture diagram suggestions.
- This gives students a structured path through their project.

### 4. Outline generation
- `api/generate_outline.php` uses AI to create a full project outline.
- The endpoint is responsible for preparing the skeleton of the report or chapter structure.

### 5. Chapter writing
- `api/generate_chapter.php` writes a complete technical chapter.
- It returns clean JSON with `heading` and `content` for the selected chapter.

### 6. Final report and ZIP packaging
- `api/finalize.php` builds the final report, visuals, and package files.
- It can generate a downloadable ZIP package containing project deliverables.

### 7. Credits and usage tracking
- `api/extend_credits.php` allows credit top-up for student accounts.
- `api/usage_stats.php` provides usage logs for tokens and cost tracking.
- The system limits students by API credit and logs AI usage.

### 8. Data storage
- The backend uses `api/db.php` and a JSON file stored in `api/uploads/database.json`.
- All users, projects, and usage logs live in this file-based database.

## Technology stack used

### Frontend
- React
- Vite
- Axios
- Framer Motion
- Lucide React icons
- React Hot Toast
- PPTXGenJS for presentation handling

### Backend
- PHP
- cURL for API requests
- JSON file storage for project and user data
- ZipArchive for ZIP packaging

### AI and services
- Anthropic Claude API for content generation
- Local file uploads in `api/uploads` for templates and generated files

## What is still left to improve

This project is working, but there are good ways to make it better:

- Improve authentication and security:
  - move away from hardcoded admin credentials,
  - add real session management,
  - support password reset and stronger account controls.
- Replace the simple JSON database with a real database (MySQL, SQLite, etc.).
- Add more validation for user input and file uploads.
- Improve the frontend UI and mobile responsiveness.
- Add clearer error messages and retry handling in the app.
- Support multiple saved versions of the same project.
- Add an admin dashboard for credit and user management.
- Add a preview screen for generated outlines and chapters.
- Add better image/chart rendering and a real diagram editor.
- Add documentation for deploying the system to a server.

## Where to look next

- Frontend files: `frontend/src/App.jsx`, `frontend/index.html`
- Backend endpoints: files in `api/`
- Database helper: `api/db.php`
- Uploads and generated files: `api/uploads/`

This document is meant to give a simple, clear picture of what Project AI does, how it works, and what can be improved next.