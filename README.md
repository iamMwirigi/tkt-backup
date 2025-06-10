# TKT API Documentation

## 1. Device Management

### 1.1 Register Device (Required for Regular Users)
```http
POST /devices/register.php
Content-Type: application/json

{
    "device_id": "DEVICE123",
    "device_name": "POS Terminal 1",
    "location": "Nairobi Branch"
}
```

Response:
```json
{
    "success": true,
    "message": "Device registered successfully",
    "device": {
        "id": 1,
        "device_id": "DEVICE123",
        "name": "POS Terminal 1",
        "status": "active",
        "registered_by": "John Doe",
        "registered_at": "2024-03-20 10:00:00"
    }
}
```

### 1.2 List Devices (Admin Only)
```http
GET /devices/list.php
```

Response:
```json
{
    "devices": [
        {
            "id": 1,
            "device_id": "DEVICE123",
            "name": "POS Terminal 1",
            "status": "active",
            "registered_by": "John Doe",
            "registered_at": "2024-03-20 10:00:00"
        }
    ]
}
```

## 2. Authentication

### 2.1 Login as Admin
```http
POST /auth/login.php
Content-Type: application/json

{
    "email": "admin@zawadi.co.ke",
    "password": "admin123"
}
```

Response:
```json
{
    "success": true,
    "message": "Login successful",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@zawadi.co.ke",
        "role": "admin",
        "company": "Zawadi Express"
    }
}
```

### 2.2 Login as Regular User
```http
POST /auth/login.php
Content-Type: application/json

{
    "email": "user@zawadi.co.ke",
    "password": "user123",
    "device_id": "DEVICE123"  // Required for non-admin users
}
```

Response:
```json
{
    "success": true,
    "message": "Login successful",
    "user": {
        "id": 2,
        "name": "Regular User",
        "email": "user@zawadi.co.ke",
        "role": "clerk",
        "company": "Zawadi Express"
    }
}
```

## 3. User Management (Admin Only)

### 3.1 List Users
```http
GET /users/list.php
```

Response:
```json
{
    "users": [
        {
            "id": 1,
            "name": "Admin User",
            "email": "admin@zawadi.co.ke",
            "role": "admin",
            "office": {
                "name": "Nairobi Central",
                "location": "Moi Avenue, Nairobi"
            },
            "stats": {
                "registered_devices": 2,
                "total_bookings": 5
            },
            "created_at": "2025-04-14 10:09:04"
        }
    ]
}
```

## 4. Route Management

### 4.1 List Routes (All Users)
```http
GET /routes/list.php
```

Response:
```json
{
    "routes": [
        {
            "id": 1,
            "name": "Nairobi - Kisumu",
            "description": "Main western route via Nakuru, Kericho",
            "destinations": [
                {
                    "id": 1,
                    "name": "Naivasha",
                    "stop_order": 1,
                    "fares": [
                        {
                            "id": 1,
                            "label": "Standard",
                            "amount": "500.00"
                        }
                    ]
                }
            ]
        }
    ]
}
```

### 4.2 Create Route (Admin Only)
```http
POST /routes/manage.php
Content-Type: application/json

{
    "name": "Nairobi - Kisumu",
    "description": "Main western route via Nakuru, Kericho",
    "destinations": [
        {
            "name": "Naivasha",
            "stop_order": 1,
            "fares": [
                {
                    "label": "Standard",
                    "amount": "500.00"
                }
            ]
        }
    ]
}
```

Response:
```json
{
    "success": true,
    "message": "Route created successfully",
    "route": {
        "id": 1,
        "name": "Nairobi - Kisumu",
        "description": "Main western route via Nakuru, Kericho",
        "destinations": [
            {
                "id": 1,
                "name": "Naivasha",
                "stop_order": 1,
                "fares": [
                    {
                        "id": 1,
                        "label": "Standard",
                        "amount": "500.00"
                    }
                ]
            }
        ]
    }
}
```

### 4.3 Update Route (Admin Only)
```http
PUT /routes/manage.php
Content-Type: application/json

{
    "id": 1,
    "name": "Nairobi - Kisumu",
    "description": "Updated description",
    "destinations": [
        {
            "id": 1,
            "name": "Naivasha",
            "stop_order": 1,
            "fares": [
                {
                    "id": 1,
                    "label": "Standard",
                    "amount": "600.00"
                }
            ]
        }
    ]
}
```

Response:
```json
{
    "success": true,
    "message": "Route updated successfully",
    "route": {
        "id": 1,
        "name": "Nairobi - Kisumu",
        "description": "Updated description",
        "destinations": [
            {
                "id": 1,
                "name": "Naivasha",
                "stop_order": 1,
                "fares": [
                    {
                        "id": 1,
                        "label": "Standard",
                        "amount": "600.00"
                    }
                ]
            }
        ]
    }
}
```

### 4.4 Delete Route (Admin Only)
```http
DELETE /routes/manage.php
Content-Type: application/json

{
    "id": 1
}
```

Response:
```json
{
    "success": true,
    "message": "Route deleted successfully"
}
```

## 5. Error Responses

All endpoints may return the following error responses:

### 5.1 400 Bad Request
```json
{
    "error": true,
    "message": "Error message here"
}
```

### 5.2 401 Unauthorized
```json
{
    "error": true,
    "message": "Invalid credentials"
}
```

### 5.3 403 Forbidden
```json
{
    "error": true,
    "message": "Access denied"
}
```

### 5.4 404 Not Found
```json
{
    "error": true,
    "message": "Resource not found"
}
```

### 5.5 500 Internal Server Error
```json
{
    "error": true,
    "message": "Internal server error"
}
```

## 6. Important Notes

1. **Device Registration**:
   - Required for all regular users before they can log in
   - Device ID must be unique
   - Device registration is a one-time process
   - Keep the device ID secure as it's required for all operations

2. **Authentication**:
   - Admin users: Login with email and password only
   - Regular users: Must login with email, password, and registered device ID
   - All requests must include the session cookie from login

3. **Routes Management**:
   - Admin users: Full CRUD operations
   - Regular users: Read-only access
   - Regular users must include their device ID in the `X-Device-ID` header

4. **Security**:
   - All endpoints require authentication
   - Admin operations are restricted to admin users only
   - Device verification is required for non-admin users
   - Session management is handled automatically
