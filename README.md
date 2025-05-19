# API Endpoints

```
POST http://localhost/tkt_dev/devices/register.php
POST http://localhost/tkt_dev/auth/login.php
POST http://localhost/tkt_dev/bookings/create.php
POST http://localhost/tkt_dev/trips/create.php
```

Example request:
```bash
curl -X POST http://localhost/tkt_dev/devices/register.php \
  -H "Content-Type: application/json" \
  -d '{"device_uuid": "test123", "device_name": "Test Device"}'
```

## Setup Instructions

1. Clone the repository
2. Copy `.env.example` to `.env` and update the database credentials
3. Run the database setup script:
   ```bash
   php setup/database.php
   ```
4. Access the API through your web server

## Default Admin Credentials
- Email: admin@example.com
- Password: admin123 