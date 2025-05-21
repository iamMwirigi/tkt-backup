# API Endpoints



## Production
```
POST http://149.56.195.219:8080/auth/login.php
POST http://149.56.195.219:8080/bookings/create.php
POST http://149.56.195.219:8080/trips/create.php
```

## Example Requests

### Production
```bash
curl -X POST http://149.56.195.219:8080/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "admin123", "device_id": "test123"}'
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
POST https://tkt.ke/devices/register.php
POST https://tkt.ke/auth/login.php
POST https://tkt.ke/bookings/create.php
POST https://tkt.ke/trips/create.php

