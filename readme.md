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

### 1.3 Create Device
```http
POST /devices/manage.php
Content-Type: application/json

{
    "device_uuid": "test-device-001",
    "device_name": "Test Device",
    "user_id": 9,
    "is_active": true
}
```
Response (201 Created):
```json
{
    "success": true,
    "message": "Device created successfully",
    "device_id": "10"
}
```

### 1.4 Update Device
```http
PUT /devices/manage.php
Content-Type: application/json

{
    "id": 10,
    "device_name": "Updated Test Device",
    "is_active": false
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "Device updated successfully"
}
```

### 1.5 Delete Device
```http
DELETE /devices/manage.php
Content-Type: application/json

{
    "id": 10
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "Device deleted successfully"
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

## 3. Vehicle Management

### 3.1 List Vehicles
```http
GET /vehicles/list.php
```

Response:
```json
{
    "success": true,
    "vehicles": [
        {
            "id": "1",
            "plate_number": "KBR 123A",
            "vehicle_type": "Bus",
            "owner_name": "James Kariuki",
            "owner_phone": "0722000001"
        }
    ]
}
```

### 3.2 Create Vehicle
```http
POST /vehicles/manage.php
Content-Type: application/json

{
    "plate_number": "KBR 456B",
    "vehicle_type": "Bus",
    "owner_id": 1
}
```
Response (201 Created):
```json
{
    "success": true,
    "message": "Vehicle created successfully",
    "vehicle_id": "2"
}
```

### 3.3 Update Vehicle
```http
PUT /vehicles/manage.php
Content-Type: application/json

{
    "id": 2,
    "plate_number": "KBR 456C",
    "vehicle_type": "Mini Bus"
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "Vehicle updated successfully"
}
```

### 3.4 Delete Vehicle
```http
DELETE /vehicles/manage.php
Content-Type: application/json

{
    "id": 2
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "Vehicle deleted successfully"
}
```

## 4. Destination Management

### 4.1 List Destinations
```http
GET /destinations/list.php
```

Response:
```json
{
    "success": true,
    "destinations": [
        {
            "id": "1",
            "route_id": "1",
            "name": "Naivasha",
            "stop_order": 1,
            "fares": [
                {
                    "id": "1",
                    "label": "Standard",
                    "amount": "500.00"
                }
            ]
        }
    ]
}
```

### 4.2 Create Destination
```http
POST /destinations/manage.php
Content-Type: application/json

{
    "route_id": 1,
    "name": "Eldoret",
    "stop_order": 5,
    "fares": [
        {
            "label": "Standard",
            "amount": 1500
        }
    ]
}
```
Response (201 Created):
```json
{
    "success": true,
    "message": "Destination created successfully",
    "destination_id": "5"
}
```

### 4.3 Update Destination
```http
PUT /destinations/manage.php
Content-Type: application/json

{
    "id": 5,
    "name": "Eldoret City",
    "stop_order": 4,
    "fares": [
        {
            "label": "Standard",
            "amount": 1600
        }
    ]
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "Destination updated successfully"
}
```

### 4.4 Delete Destination
```http
DELETE /destinations/manage.php
Content-Type: application/json

{
    "id": 5
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "Destination deleted successfully"
}
```

## 5. Route Management

### 5.1 List Routes (All Users)
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

### 5.2 Create Route (Admin Only)
```http
POST /routes/manage.php
Content-Type: application/json

{
    "name": "Nairobi - Mombasa",
    "description": "Coastal route via Mtito Andei",
    "destinations": [
        {
            "name": "Mtito Andei",
            "stop_order": 1,
            "fares": [
                {
                    "label": "Standard",
                    "amount": 800
                }
            ]
        },
        {
            "name": "Mombasa",
            "stop_order": 2,
            "fares": [
                {
                    "label": "Standard",
                    "amount": 1200
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
        "id": 2,
        "name": "Nairobi - Mombasa",
        "description": "Coastal route via Mtito Andei",
        "destinations": [
            {
                "id": 1,
                "name": "Mtito Andei",
                "stop_order": 1,
                "fares": [
                    {
                        "id": 1,
                        "label": "Standard",
                        "amount": "800.00"
                    }
                ]
            },
            {
                "id": 2,
                "name": "Mombasa",
                "stop_order": 2,
                "fares": [
                    {
                        "id": 2,
                        "label": "Standard",
                        "amount": "1200.00"
                    }
                ]
            }
        ]
    }
}
```

### 5.3 Update Route (Admin Only)
```http
PUT /routes/manage.php
Content-Type: application/json

{
    "id": 2,
    "name": "Nairobi - Mombasa Express",
    "destinations": [
        {
            "name": "Mtito Andei",
            "stop_order": 1,
            "fares": [
                {
                    "label": "Standard",
                    "amount": 900
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
    "message": "Route updated successfully"
}
```

### 5.4 Delete Route (Admin Only)
```http
DELETE /routes/manage.php
Content-Type: application/json

{
    "id": 2
}
```

Response:
```json
{
    "success": true,
    "message": "Route deleted successfully"
}
```

## 6. User Management (Admin Only)

### 6.1 List Users
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

### 6.2 Create User
```http
POST /users/manage.php
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "role": "officer",
    "office_id": 1
}
```
Response (201 Created):
```json
{
    "success": true,
    "message": "User created successfully",
    "user_id": "2"
}
```

### 6.3 Update User
```http
PUT /users/manage.php
Content-Type: application/json

{
    "id": 2,
    "name": "John Smith",
    "role": "clerk"
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "User updated successfully"
}
```

### 6.4 Delete User
```http
DELETE /users/manage.php
Content-Type: application/json

{
    "id": 2
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "User deleted successfully"
}
```

## 7. Office Management

### 7.1 List Offices
```http
GET /offices/list.php
```

Response:
```json
{
    "success": true,
    "offices": [
        {
            "id": "1",
            "name": "Nairobi Central",
            "location": "Moi Avenue, Nairobi",
            "user_count": "2"
        }
    ]
}
```

### 7.2 Create Office
```http
POST /offices/manage.php
Content-Type: application/json

{
    "name": "Nairobi West",
    "location": "Westlands, Nairobi"
}
```
Response (201 Created):
```json
{
    "success": true,
    "message": "Office created successfully",
    "office_id": "2"
}
```

### 7.3 Update Office
```http
PUT /offices/manage.php
Content-Type: application/json

{
    "id": 2,
    "name": "Nairobi West Branch",
    "location": "Westlands Mall, 3rd Floor"
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "Office updated successfully"
}
```

### 7.4 Delete Office
```http
DELETE /offices/manage.php
Content-Type: application/json

{
    "id": 2
}
```
Response (200 OK):
```json
{
    "success": true,
    "message": "Office deleted successfully"
}
```

## 8. Error Responses

All endpoints may return the following error responses:

### 8.1 400 Bad Request
```json
{
    "error": true,
    "message": "Error message here"
}
```

### 8.2 401 Unauthorized
```json
{
    "error": true,
    "message": "Invalid credentials"
}
```

### 8.3 403 Forbidden
```json
{
    "error": true,
    "message": "Access denied"
}
```

### 8.4 404 Not Found
```json
{
    "error": true,
    "message": "Resource not found"
}
```

### 8.5 500 Internal Server Error
```json
{
    "error": true,
    "message": "Internal server error"
}
```

## 9. Important Notes

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

## 10. Testing Tips

1. **Devices**
   - Test UUID uniqueness
   - Verify user associations
   - Check active status
   - Test device registration

2. **Authentication**
   - Always include `Content-Type: application/json` header
   - Test with invalid credentials
   - Test with missing fields
   - Verify session handling

3. **Vehicles**
   - Test plate number uniqueness
   - Verify owner relationships
   - Check vehicle type validation
   - Test seat management

4. **Destinations**
   - Verify stop order
   - Test fare management
   - Check route associations
   - Validate location data

5. **Routes**
   - Test destination order
   - Verify fare calculations
   - Check route-destination relationships
   - Test with invalid data

6. **Users**
   - Test role-based access control
   - Verify password hashing
   - Test user-office relationships
   - Check validation of email uniqueness

7. **Offices**
   - Test user associations
   - Verify location data
   - Check company relationships
   - Test office deletion with users

## 11. Common Response Codes

- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 405: Method Not Allowed
- 500: Internal Server Error

## 12. Response Formats

### Error Response Format
```json
{
    "error": true,
    "message": "Error description"
}
```

### Success Response Format
```json
{
    "success": true,
    "message": "Operation successful",
    "data": {} // Optional data object
}
```