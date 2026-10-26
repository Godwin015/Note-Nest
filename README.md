# NoteNest

**NoteNest** is a modern, full-stack note management application built with Laravel, React, TypeScript, and MySQL.

It provides a clean, responsive workspace for capturing ideas, organizing information, and managing personal notes. Users can create, view, edit, search, categorize, pin, and delete notes through an intuitive interface.

NoteNest combines a Laravel REST API with a React frontend to deliver a smooth and interactive note-taking experience.

## Features

### Note Management
- Create new notes with titles and content.
- View existing notes.
- Edit note details and content.
- Delete notes with confirmation.
- Pin and unpin notes.
- Display pinned notes before other notes.
- Automatically track note creation and update timestamps.

### Organization and Productivity
- Organize notes using categories.
- Filter notes by category.
- Search notes by title or content.
- View pinned notes separately.
- Paginate notes with 10 notes per page.
- Maintain an organized personal workspace.

### Rich Text and Attachments
- Rich text editing with TipTap.
- Upload attachments to notes.
- Download attached files.
- Delete attachments.
- Preview supported image attachments.
- Display attachment upload progress.
- Validate attachment types and file sizes.

### User Interface
- Responsive dashboard layout.
- Sidebar navigation.
- Interactive note cards.
- Search and category filtering controls.
- Create and edit note forms.
- Loading, empty, success, and error states.
- Smooth hover animations and interface transitions.

## Technology Stack

| Component | Technology |
|---|---|
| Backend | Laravel 12 |
| Frontend | React |
| Programming Language | TypeScript |
| Database | MySQL |
| Frontend Build Tool | Vite |
| UI Components | Mantine |
| Rich Text Editor | TipTap |
| HTML Sanitization | DOMPurify |
| Backend Testing | PHPUnit |
| Frontend Testing | Vitest |
| Frontend Testing Environment | jsdom |
| API | Laravel REST API |

## Project Structure

```text
note_nest/
└── backend/
    ├── app/
    │   ├── Http/
    │   │   └── Controllers/
    │   │       └── Api/
    │   │           └── NoteController.php
    │   ├── database/
    │   │   ├── migrations/
    │   │   └── seeders/
    │   ├── resources/
    │   │   ├── js/
    │   │   │   ├── app.tsx
    │   │   │   ├── NoteNestApp.tsx
    │   │   │   ├── App.test.tsx
    │   │   │   ├── App.css
    │   │   │   └── index.css
    │   │   └── views/
    │   │       └── welcome.blade.php
    │   ├── routes/
    │   │   ├── api.php
    │   │   └── web.php
    │   ├── tests/
    │   ├── package.json
    │   ├── vite.config.js
    │   └── composer.json
```

## Requirements

Before installing NoteNest, ensure the following are installed:

- PHP 8.2 or a compatible version.
- Composer.
- Node.js and npm.
- MySQL.
- Git.

## Installation

### 1. Clone the Repository

```bash
git clone https://gitlab.com/Gaadwhin/note_nest.git
```

Navigate to the backend directory:

```bash
cd note_nest/backend
```

### 2. Install Backend Dependencies

```bash
composer install
```

### 3. Install Frontend Dependencies

```bash
npm install
```

### 4. Configure Environment Variables

Create your environment configuration:

```bash
cp .env.example .env
```

Generate the Laravel application key:

```bash
php artisan key:generate
```

### 5. Configure the Database

Create a MySQL database for NoteNest.

Update the database configuration in your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=note_nest
DB_USERNAME=root
DB_PASSWORD=
```

Use your actual MySQL database name, username, and password.

### 6. Run Database Migrations

```bash
php artisan migrate
```

This creates the database tables required by the application.

## Running the Application

NoteNest uses Laravel to serve the application and Vite to compile the frontend assets.

### Start the Laravel Development Server

From the backend directory:

```bash
php artisan serve
```

### Start the Frontend Development Server

Open another terminal in the backend directory:

```bash
npm run dev
```

Open the application in your browser:

[http://127.0.0.1:8000](http://127.0.0.1:8000)

Keep both development servers running while working on the application.

## Frontend Commands

Run the frontend development server:

```bash
npm run dev
```

Build the frontend assets:

```bash
npm run build
```

Run frontend tests:

```bash
npm run test -- --run
```

Run frontend linting:

```bash
npm run lint
```

## Backend Testing

Run Laravel tests:

```bash
php artisan test
```

## API Endpoints

### Notes

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/notes` | Retrieve notes with search, category filtering, pin filtering, and pagination |
| POST | `/api/notes` | Create a new note |
| GET | `/api/notes/{note}` | Retrieve a specific note |
| PATCH | `/api/notes/{note}` | Update a note |
| DELETE | `/api/notes/{note}` | Delete a note |
| PATCH | `/api/notes/{note}/pin` | Pin or unpin a note |

### Note Attachments

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/notes/{note}/attachments` | Retrieve note attachments |
| POST | `/api/notes/{note}/attachments` | Upload an attachment |
| DELETE | `/api/notes/{note}/attachments/{attachment}` | Delete an attachment |
| GET | Attachment download endpoint | Download an attachment |

The attachment download endpoint should be documented with its exact route from the Laravel application.

## Development

The frontend is integrated into Laravel through Laravel Vite.

React and TypeScript source files are located in:

```text
resources/js/
```

The main NoteNest interface is implemented in:

```text
resources/js/NoteNestApp.tsx
```

The application uses Laravel routes to serve the frontend and Laravel API endpoints to handle note management operations.

Frontend assets are compiled using Vite.

## Security and Validation

NoteNest uses backend validation to protect note and attachment operations.

Attachment validation includes:

- Maximum attachment size of 50 MB per file.
- Maximum of 5 attachments per note.
- Supported file formats: PDF, DOC, DOCX, TXT, JPG, JPEG, PNG, and WEBP.

Rich text content is sanitized using DOMPurify before rendering.

## License

This project is developed as part of the NoteNest notes management module assignment.
