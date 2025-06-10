# TKT API Documentation

## Device Management (Required for Regular Users)

### 1. Register Device (Required First Step for Regular Users)
```http
POST /devices/register.php
Content-Type: application/json

{
    "device_id": "DEVICE123",
    "device_name": "POS Terminal 1",
    "location": "Nairobi Branch"
}
```
**Response (201 Created):**
```json
{
    "success": true,
    "message": "Device registered successfully",
    "device_id": "DEVICE123"
}
```

### 2. List Devices
```http
GET /devices/list.php
```
**Response (200 OK):**
```json
{
    "devices": [
        {
            "id": "DEVICE123",
            "name": "POS Terminal 1",
            "location": "Nairobi Branch",
            "status": "active",
            "last_seen": "2024-03-20 10:30:00"
        }
    ]
}
```

## Authentication

### 1. Login as Admin
```http
POST /auth/login.php
Content-Type: application/json

{
    "email": "admin@zawadi.co.ke",
    "password": "admin123"
}
```
**Response (200 OK):**
```json
{
    "message": "Login successful",
    "user": {
        "id": 7,
        "name": "Admin User",
        "email": "admin@zawadi.co.ke",
        "role": "admin",
        "company": "Zawadi Express"
    }
}
```

### 2. Login as Regular User (After Device Registration)
```http
POST /auth/login.php
Content-Type: application/json

{
    "email": "user@zawadi.co.ke",
    "password": "user123",
    "device_id": "DEVICE123"  // Must be a registered device
}
```
**Response (200 OK):**
```json
{
    "message": "Login successful",
    "user": {
        "id": 8,
        "name": "Regular User",
        "email": "user@zawadi.co.ke",
        "role": "user",
        "company": "Zawadi Express"
    }
}
```

## Routes Management

### 1. Create Route (Admin Only)
```http
POST /routes/manage.php
Content-Type: application/json

{
    "name": "Nairobi - Mombasa",
    "description": "Main route between Nairobi and Mombasa",
    "destinations": [
        {
            "name": "Nairobi",
            "stop_order": 1,
            "fares": [
                {
                    "label": "Adult",
                    "amount": 1500
                },
                {
                    "label": "Child",
                    "amount": 750
                }
            ]
        },
        {
            "name": "Mombasa",
            "stop_order": 2,
            "fares": [
                {
                    "label": "Adult",
                    "amount": 1500
                },
                {
                    "label": "Child",
                    "amount": 750
                }
            ]
        }
    ]
}
```
**Response (201 Created):**
```json
{
    "success": true,
    "message": "Route created successfully",
    "route_id": 1
}
```

### 2. View Routes (All Users)
```http
GET /routes/list.php
X-Device-ID: DEVICE123  // Required for non-admin users
```
**Response (200 OK):**
```json
{
    "routes": [
        {
            "id": 1,
            "name": "Nairobi - Mombasa",
            "description": "Main route between Nairobi and Mombasa",
            "destinations": [
                {
                    "id": 1,
                    "name": "Nairobi",
                    "stop_order": 1,
                    "fares": [
                        {
                            "id": 1,
                            "label": "Adult",
                            "amount": 1500
                        },
                        {
                            "id": 2,
                            "label": "Child",
                            "amount": 750
                        }
                    ]
                },
                {
                    "id": 2,
                    "name": "Mombasa",
                    "stop_order": 2,
                    "fares": [
                        {
                            "id": 3,
                            "label": "Adult",
                            "amount": 1500
                        },
                        {
                            "id": 4,
                            "label": "Child",
                            "amount": 750
                        }
                    ]
                }
            ]
        }
    ]
}
```

## User Flow

### Regular User Flow:
1. **First Time Setup**:
   - Register device using `/devices/register.php`
   - Save the `device_id` for future use

2. **Daily Usage**:
   - Login with email, password, and registered `device_id`
   - Include `device_id` in `X-Device-ID` header for all API calls
   - Can only view routes, no modification permissions

### Admin User Flow:
1. **Login**:
   - Login with email and password only
   - No device registration required

2. **Daily Usage**:
   - Full access to all API endpoints
   - Can create, update, and delete routes
   - Can view all routes without device ID

## Important Notes

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

4. **Error Responses**:
   - 400 Bad Request: Invalid input data or unregistered device
   - 401 Unauthorized: Invalid credentials
   - 403 Forbidden: Insufficient permissions
   - 404 Not Found: Resource not found
   - 405 Method Not Allowed: Invalid HTTP method

5. **Security**:
   - All endpoints require authentication
   - Admin operations are restricted to admin users only
   - Device verification is required for non-admin users
   - Session management is handled automatically
