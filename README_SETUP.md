# Backend Setup Instructions

## Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite (or MySQL/PostgreSQL)

## Installation Steps

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure Database**
   - For SQLite (default): Ensure `database/database.sqlite` exists
   - For MySQL/PostgreSQL: Update `.env` with your database credentials

4. **Run Migrations**
   ```bash
   php artisan migrate
   ```

5. **Seed Database**
   ```bash
   php artisan db:seed
   ```
   This will create:
   - Super Admin role with all permissions
   - Teacher role with limited permissions
   - Accountant role with payment/report permissions
   - A super admin user (email: `admin@techna.edu`, password: `password`)

6. **Start Server**
   ```bash
   php artisan serve
   ```
   The API will be available at `http://localhost:8000`

## API Endpoints

### Authentication
- `POST /api/v1/auth/login` - Login
- `GET /api/v1/auth/me` - Get current user (requires auth)
- `POST /api/v1/auth/logout` - Logout (requires auth)

### Roles (Super Admin only)
- `GET /api/v1/roles` - List all roles
- `POST /api/v1/roles` - Create role
- `GET /api/v1/roles/{id}` - Get role
- `PUT /api/v1/roles/{id}` - Update role
- `DELETE /api/v1/roles/{id}` - Delete role

## CORS Configuration

If you need to configure CORS for the frontend, update `config/cors.php` or add CORS middleware.

## Default Super Admin Credentials
- Email: `admin@techna.edu`
- Password: `password`

**⚠️ Change these credentials in production!**
