# ORS Student Incident Tracking System — Backend

This repository contains the PHP backend API for the ORS Student Incident Tracking System.

## Prerequisites

- XAMPP with Apache and MySQL, or another PHP + MySQL environment
- PHP 8.1+ with `ext-pdo` enabled
- Composer

## Setup instructions

### 1. Place the backend files in your web server root

Example for XAMPP:

- `D:\XAMPP\htdocs\ors-backend`

### 2. Copy and configure env

Copy:

```bash
cp .env.example .env
```

Then edit `.env` with your local settings.

Example:

```env
APP_URL=http://localhost/ors-backend

DB_HOST=localhost
DB_PORT=3306
DB_NAME=ors_db
DB_USER=root
DB_PASS=

JWT_SECRET=your_jwt_secret_here

ALLOWED_ORIGINS=http://localhost:5173
```

### 3. Install dependencies

From the backend folder:

```bash
composer install
```

### 4. Import the database schema

Create the database and import `schema.sql`:

```sql
CREATE DATABASE ors_db;
USE ors_db;
```

Then import the SQL schema file via phpMyAdmin or MySQL CLI.

### 5. Verify the backend

Open the login endpoint in your browser or API client:

- `http://localhost/ors-backend/api/auth/login`

It should return a valid JSON response.

### 6. Protect `.env`

Do not commit `.env`.
This repo ignores `.env` via `.gitignore`.

## Local test accounts

- OSAS: `maria.santos@school.edu` / `Passw0rd!23`
- Guidance Office: `noah.delgado@school.edu` / `Guidance123!`
- Chaplain: `peter.cruz@school.edu` / `Chaplain123!`
- Department Head: `elena.cruz@school.edu` / `Head123!`
- Teacher: `christian.reyes@school.edu` / `Teacher123!`

## Notes

- If you use this backend repo with the frontend, make sure the frontend `VITE_API_URL` matches the backend `APP_URL` plus `/api`.
- Example frontend API URL:

```env
VITE_API_URL=http://localhost/ors-backend/api
```
