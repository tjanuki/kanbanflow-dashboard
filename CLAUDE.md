# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12.28.1 application called KanbanFlow Dashboard with PHP 8.2+ backend and Vite + TailwindCSS frontend.

## Essential Commands

### Development
- `composer dev` - Start all development services (server, queue, logs, vite) concurrently
- `php artisan serve` - Start Laravel development server
- `npm run dev` - Start Vite development server with hot reload
- `php artisan queue:listen --tries=1` - Start queue worker
- `php artisan pail` - Watch application logs in real-time

### Build & Deployment
- `npm run build` - Build frontend assets for production
- `composer install` - Install PHP dependencies
- `npm install` - Install Node dependencies
- `php artisan migrate` - Run database migrations
- `php artisan db:seed` - Seed the database

### Testing & Quality
- `composer test` - Run all tests (clears config and runs Pest tests)
- `php artisan test` - Run tests directly
- `php artisan test --filter TestName` - Run specific test
- `./vendor/bin/pest` - Run Pest tests directly
- `./vendor/bin/pint` - Run Laravel Pint code formatter
- `./vendor/bin/pint --test` - Check code formatting without changes

### Database
- `php artisan migrate:fresh` - Drop all tables and re-run migrations
- `php artisan migrate:rollback` - Rollback the last migration batch
- `php artisan tinker` - Interactive PHP REPL with Laravel loaded

### Cache & Optimization
- `php artisan config:clear` - Clear configuration cache
- `php artisan cache:clear` - Clear application cache
- `php artisan route:clear` - Clear route cache
- `php artisan view:clear` - Clear compiled view files
- `php artisan optimize` - Cache the framework bootstrap files

## Architecture

### Backend Structure
- **app/Http/Controllers/** - HTTP controllers handling requests
- **app/Models/** - Eloquent ORM models
- **app/Providers/** - Service providers for bootstrapping services
- **routes/web.php** - Web routes definition
- **routes/console.php** - Console commands definition
- **database/migrations/** - Database schema migrations
- **database/factories/** - Model factories for testing
- **database/seeders/** - Database seeders

### Frontend Structure
- **resources/views/** - Blade templates
- **resources/css/app.css** - Main CSS entry point (TailwindCSS)
- **resources/js/app.js** - Main JavaScript entry point
- **public/** - Publicly accessible assets
- **vite.config.js** - Vite configuration with Laravel plugin and TailwindCSS

### Testing
- **tests/Unit/** - Unit tests for isolated components
- **tests/Feature/** - Feature tests for application behavior
- Tests use Pest PHP testing framework
- Database tests use SQLite in-memory database

### Key Technologies
- **Laravel 12** - PHP framework
- **Vite** - Frontend build tool
- **TailwindCSS v4** - Utility-first CSS framework (using @tailwindcss/vite)
- **Pest** - Testing framework
- **Laravel Pint** - Code style fixer
- **Composer** - PHP dependency manager
- **NPM** - JavaScript dependency manager

### Environment Configuration
- `.env` file contains environment-specific configuration
- `.env.example` serves as template for environment variables
- Database defaults to SQLite for local development