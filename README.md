# TKT Backup API Documentation

## Authentication

### Login
- **Endpoint**: `POST /auth/login.php`
- **Headers**:
  - `Content-Type: application/json`
- **Body**:
```json
// For Admin Users:
{
    "email": "admin@zawadi.co.ke",
    "password": "your_password"
}

// For Non-Admin Users (Officers, Clerks):
{
    "email": "officer@zawadi.co.ke",
    "password": "your_password",
    "device_id": "my-awesome-test-device-001"  // Required for non-admin users
}
```
- **Response**:
```json
{
    "success": true,
    "message": "Login successful",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@zawadi.co.ke",
        "role": "admin",
        "company": "Zawadi Co. Ltd"
    }
}
```

## Routes

### List Routes with Destinations and Fares
- **Endpoint**: `GET /routes/list.php`
- **Headers**:
  - `Content-Type: application/json`
  - `X-Device-ID: <device_id>` (Required for non-admin users only)
- **Response**:
```json
{
    "success": true,
    "message": "Routes retrieved successfully",
    "data": {
        "routes": [
            {
                "id": 1,
                "company_id": 1,
                "name": "Nairobi - Kisumu",
                "description": "Main western route via Nakuru, Kericho",
                "created_at": "2025-04-14 10:09:04",
                "destination_count": 4,
                "fare_count": 4,
                "destinations": [
                    {
                        "id": 1,
                        "route_id": 1,
                        "name": "Naivasha",
                        "stop_order": 1,
                        "fare_count": 1,
                        "fares": [
                            {
                                "id": 1,
                                "destination_id": 1,
                                "label": "Standard",
                                "amount": "500.00",
                                "created_at": "2025-04-14 10:09:04"
                            }
                        ]
                    }
                ]
            }
        ]
    }
}
```

## Trips

### Create Trip
- **Endpoint**: `POST /trips/create.php`
- **Headers**:
  - `Content-Type: application/json`
  - `X-Device-ID: <device_id>` (Required for non-admin users only)
- **Body**:
```json
{
    "vehicle_id": 1,
    "route_id": 1,
    "driver_name": "John Driver",
    "conductor_name": "Mike Conductor",
    "departure_time": "2025-06-10 10:00:00"
}
```
- **Response**:
```json
{
    "success": true,
    "message": "Trip created successfully",
    "trip_id": 1,
    "trip_code": "ZAW-NAI-20250610-1"
}
```

## Tickets

### Create Ticket
- **Endpoint**: `POST /tickets/create.php`
- **Headers**:
  - `Content-Type: application/json`
  - `X-Device-ID: <device_id>` (Required for non-admin users only)
- **Body**:
```json
{
    "trip_id": 1,
    "destination_id": 1,
    "seat_number": "4A",
    "fare_amount": 1000,
    "location": "Terminal",
    "payment_method": "cash",
    "transaction_id": null
}
```
- **Response**:
```json
{
    "success": true,
    "message": "Ticket created successfully",
    "ticket_id": 1
}
```

### Convert Booking to Ticket
- **Endpoint**: `POST /tickets/convert-booking.php`
- **Headers**:
  - `Content-Type: application/json`
  - `X-Device-ID: <device_id>` (Required for non-admin users only)
- **Body**:
```json
{
    "booking_id": 1,
    "location": "Terminal",
    "payment_method": "cash",
    "transaction_id": null,
    "override_seat": false
}
```
- **Response**:
```json
{
    "success": true,
    "message": "Booking converted to ticket successfully",
    "ticket_id": 1
}
```

## Bookings

### Create Booking
- **Endpoint**: `POST /bookings/create.php`
- **Headers**:
  - `Content-Type: application/json`
  - `X-Device-ID: <device_id>` (Required for non-admin users only)
- **Body**:
```json
{
    "trip_id": 1,
    "customer_name": "John Doe",
    "customer_phone": "0712345678",
    "destination_id": 1,
    "seat_number": "1A",
    "fare_amount": 1000,
    "status": "booked"
}
```
- **Response**:
```json
{
    "success": true,
    "message": "Booking created successfully",
    "booking_id": 1
}
```

## Error Responses

All endpoints may return the following error responses:

### Unauthorized (401)
```json
{
    "error": true,
    "message": "Unauthorized. Please login."
}
```

### Bad Request (400)
```json
{
    "error": true,
    "message": "Missing required field: field_name"
}
```

### Device ID Required (400)
```json
{
    "error": true,
    "message": "Device ID is required",
    "debug": {
        "headers": {...},
        "server_vars": {...}
    }
}
```

## Notes

1. All endpoints require authentication. Make sure to login first and include the session cookie in subsequent requests.
2. Device ID requirements are role-based:
   - Admin users (`role: "admin"`) do not need to provide a device ID
   - Non-admin users (`role: "officer"` or `role: "clerk"`) must provide a device ID in either:
     - `X-Device-ID` header
     - `device_kubwa` header
3. All timestamps are in UTC format (YYYY-MM-DD HH:mm:ss)
4. All monetary values are in the local currency (KES)
5. Seat numbers should be in the format "number+letter" (e.g., "1A", "2B", etc.)
6. Routes are ordered by name, destinations by stop_order, and fares by amount
7. Each route includes:
   - Basic route information
   - Count of destinations and fares
   - List of destinations with their fares
8. Each destination includes:
   - Basic destination information
   - Count of available fares
   - List of fares with their amounts

## User Roles

The system supports three user roles:

1. **Admin** (`role: "admin"`)
   - Full system access
   - No device ID required
   - Can manage all company data
   - Example: `admin@zawadi.co.ke`

2. **Officer** (`role: "officer"`)
   - Limited system access
   - Device ID required
   - Can manage tickets and bookings
   - Example: `officer@zawadi.co.ke`

3. **Clerk** (`role: "clerk"`)
   - Basic system access
   - Device ID required
   - Can create bookings and tickets
   - Example: `clerk@zawadi.co.ke` 