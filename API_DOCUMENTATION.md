# TKT API Documentation

## 1. Vehicle Management

### 1.1 List Vehicles
```http
GET /vehicles/list.php
```

Response:
```json
{
    "success": true,
    "message": "Vehicles retrieved successfully",
    "data": {
        "vehicles": [
            {
                "id": 1,
                "company_id": 1,
                "owner_id": 1,
                "plate_number": "KAA 123B",
                "vehicle_type": "Mini Bus",
                "created_at": "2025-04-14 10:09:04",
                "vehicle_type_id": null,
                "owner_name": "James Kariuki",
                "owner_phone": "0722000001",
                "active_trips": 4
            }
        ]
    }
}
```

### 1.2 Create Vehicle
```http
POST /vehicles/manage.php
Content-Type: application/json

{
    "plate_number": "KAA 123A",
    "vehicle_type": "Bus",
    "owner_id": 1
}
```

Response (201 Created):
```json
{
    "success": true,
    "message": "Vehicle created successfully",
    "vehicle": {
        "id": 1,
        "company_id": 1,
        "owner_id": 1,
        "plate_number": "KAA 123A",
        "vehicle_type": "Bus",
        "created_at": "2025-04-14 10:09:04"
    }
}
```

### 1.3 Update Vehicle
```http
PUT /vehicles/manage.php
Content-Type: application/json

{
    "id": 1,
    "plate_number": "KAA 123B",
    "vehicle_type": "Mini Bus"
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Vehicle updated successfully",
    "vehicle": {
        "id": 1,
        "company_id": 1,
        "owner_id": 1,
        "plate_number": "KAA 123B",
        "vehicle_type": "Mini Bus",
        "created_at": "2025-04-14 10:09:04"
    }
}
```

### 1.4 Delete Vehicle
```http
DELETE /vehicles/manage.php
Content-Type: application/json

{
    "id": 1
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Vehicle deleted successfully"
}
```

## 2. Vehicle Types Management

### 2.1 List Vehicle Types
```http
GET /vehicle-types/list.php
```

Response:
```json
{
    "success": true,
    "message": "Vehicles retrieved successfully",
    "data": {
        "vehicles": [
            {
                "id": 1,
                "company_id": 1,
                "owner_id": 1,
                "plate_number": "KAA 123B",
                "vehicle_type": "Mini Bus",
                "created_at": "2025-04-14 10:09:04",
                "vehicle_type_id": null,
                "owner_name": "James Kariuki",
                "owner_phone": "0722000001",
                "active_trips": 4
            },  
        ]
    }
}
```

### 2.2 Create Vehicle Type
```http
POST /vehicle-types/manage.php
Content-Type: application/json

{
    "name": "Bus"
}
```

Response (201 Created):
```json
{
    "success": true,
    "message": "Vehicle type created successfully",
    "vehicle_type": {
        "id": 1,
        "name": "Bus"
    }
}
```

### 2.3 Update Vehicle Type
```http
PUT /vehicle-types/manage.php
Content-Type: application/json

{
    "id": 1,
    "name": "Large Bus"
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Vehicle type updated successfully",
    "vehicle_type": {
        "id": 1,
        "name": "Large Bus"
    }
}
```

### 2.4 Delete Vehicle Type
```http
DELETE /vehicle-types/manage.php
Content-Type: application/json

{
    "id": 1
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Vehicle type deleted successfully"
}
```

## 3. Owners Management

### 3.1 List Owners
```http
GET /owners/list.php
```

Response:
```json
{
    "success": true,
    "message": "Owners retrieved successfully",
    "data": {
        "owners": [
            {
                "id": 1,
                "name": "John Doe Transport",
                "phone": "+254712345678",
                "email": "john@doetransport.com",
                "address": "Nairobi CBD"
            }
        ]
    }
}
```

### 3.2 Create Owner
```http
POST /owners/manage.php
Content-Type: application/json

{
    "name": "John Doe Transport",
    "phone": "+254712345678",
    "email": "john@doetransport.com",
    "address": "Nairobi CBD"
}
```

Response (201 Created):
```json
{
    "success": true,
    "message": "Owner created successfully",
    "owner": {
        "id": 1,
        "name": "John Doe Transport",
        "phone": "+254712345678",
        "email": "john@doetransport.com",
        "address": "Nairobi CBD"
    }
}
```

### 3.3 Update Owner
```http
PUT /owners/manage.php
Content-Type: application/json

{
    "id": 1,
    "name": "John Doe Transport Ltd",
    "phone": "+254712345679"
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Owner updated successfully",
    "owner": {
        "id": 1,
        "name": "John Doe Transport Ltd",
        "phone": "+254712345679",
        "email": "john@doetransport.com",
        "address": "Nairobi CBD"
    }
}
```

### 3.4 Delete Owner
```http
DELETE /owners/manage.php
Content-Type: application/json

{
    "id": 1
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Owner deleted successfully"
}
```

## 4. Trips Management

### 4.1 List Trips
```http
GET /trips/list.php
```

Query Parameters:
- `vehicle_id`: Filter by vehicle
- `status`: Filter by status
- `date_from`: Filter by start date (YYYY-MM-DD)
- `date_to`: Filter by end date (YYYY-MM-DD)

Response:
```json
{
    "success": true,
    "message": "Trips retrieved successfully",
    "data": {
        "trips": [
            {
                "id": 1,
                "company_id": 1,
                "vehicle_id": 1,
                "trip_code": "NAI-MSA-20250415-1",
                "departure_time": "2025-04-15 08:00:00",
                "arrival_time": "2025-04-15 14:00:00",
                "status": "scheduled",
                "vehicle": {
                    "plate_number": "KAA 123B",
                    "vehicle_type": "Bus"
                }
            }
        ]
    }
}
```

### 4.2 Create Trip
```http
POST /trips/manage.php
Content-Type: application/json

{
    "vehicle_id": 1,
    "trip_code": "NAI-MSA-20250415-1",
    "departure_time": "2025-04-15 08:00:00",
    "arrival_time": "2025-04-15 14:00:00",
    "status": "scheduled"
}
```

Response (201 Created):
```json
{
    "success": true,
    "message": "Trip created successfully",
    "trip": {
        "id": 1,
        "company_id": 1,
        "vehicle_id": 1,
        "trip_code": "NAI-MSA-20250415-1",
        "departure_time": "2025-04-15 08:00:00",
        "arrival_time": "2025-04-15 14:00:00",
        "status": "scheduled"
    }
}
```

### 4.3 Update Trip
```http
PUT /trips/manage.php
Content-Type: application/json

{
    "id": 1,
    "status": "in_progress",
    "departure_time": "2025-04-15 08:30:00"
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Trip updated successfully",
    "trip": {
        "id": 1,
        "company_id": 1,
        "vehicle_id": 1,
        "trip_code": "NAI-MSA-20250415-1",
        "departure_time": "2025-04-15 08:30:00",
        "arrival_time": "2025-04-15 14:00:00",
        "status": "in_progress"
    }
}
```

### 4.4 Delete Trip
```http
DELETE /trips/manage.php
Content-Type: application/json

{
    "id": 1
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Trip deleted successfully"
}
```

## 5. Tickets Management

### 5.1 List Tickets
```http
GET /tickets/list.php
```

Query Parameters:
- `vehicle_id`: Filter by vehicle
- `trip_id`: Filter by trip
- `officer_id`: Filter by officer
- `status`: Filter by status ('unpaid' or 'paid')
- `included_in_delivery`: Filter by delivery status (0 or 1)
- `date_from`: Filter by start date (YYYY-MM-DD)
- `date_to`: Filter by end date (YYYY-MM-DD)

Response:
```json
{
    "success": true,
    "message": "Tickets retrieved successfully",
    "data": {
        "tickets": [
            {
                "id": 1,
                "company_id": 1,
                "vehicle_id": 1,
                "trip_id": 1,
                "officer_id": 2,
                "offense_id": null,
                "destination_id": 1,
                "booking_id": null,
                "route": "Nairobi - Mombasa",
                "location": "Nairobi Terminal",
                "issued_at": "2025-04-15 08:00:00",
                "status": "paid",
                "included_in_delivery": 1,
                "plate_number": "KAA 123B",
                "trip_code": "NAI-MSA-20250415-1",
                "officer_name": "Booking Clerk",
                "offense_title": null,
                "destination_name": "Mombasa"
            }
        ]
    }
}
```

### 5.2 Create Ticket
```http
POST /tickets/manage.php
Content-Type: application/json

{
    "vehicle_id": 1,
    "trip_id": 1,
    "officer_id": 2,
    "destination_id": 1,
    "route": "Nairobi - Mombasa",
    "location": "Nairobi Terminal",
    "offense_id": null,
    "booking_id": null,
    "status": "unpaid",
    "included_in_delivery": 0
}
```

Response (201 Created):
```json
{
    "success": true,
    "message": "Ticket created successfully",
    "ticket": {
        "id": 1,
        "company_id": 1,
        "vehicle_id": 1,
        "trip_id": 1,
        "officer_id": 2,
        "offense_id": null,
        "destination_id": 1,
        "booking_id": null,
        "route": "Nairobi - Mombasa",
        "location": "Nairobi Terminal",
        "issued_at": "2025-04-15 08:00:00",
        "status": "unpaid",
        "included_in_delivery": 0,
        "plate_number": "KAA 123B",
        "trip_code": "NAI-MSA-20250415-1",
        "officer_name": "Booking Clerk",
        "offense_title": null,
        "destination_name": "Mombasa"
    }
}
```

### 5.3 Update Ticket
```http
PUT /tickets/manage.php
Content-Type: application/json

{
    "id": 1,
    "status": "paid",
    "included_in_delivery": 1
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Ticket updated successfully",
    "ticket": {
        "id": 1,
        "company_id": 1,
        "vehicle_id": 1,
        "trip_id": 1,
        "officer_id": 2,
        "offense_id": null,
        "destination_id": 1,
        "booking_id": null,
        "route": "Nairobi - Mombasa",
        "location": "Nairobi Terminal",
        "issued_at": "2025-04-15 08:00:00",
        "status": "paid",
        "included_in_delivery": 1,
        "plate_number": "KAA 123B",
        "trip_code": "NAI-MSA-20250415-1",
        "officer_name": "Booking Clerk",
        "offense_title": null,
        "destination_name": "Mombasa"
    }
}
```

### 5.4 Delete Ticket
```http
DELETE /tickets/manage.php
Content-Type: application/json

{
    "id": 1
}
```

Response (200 OK):
```json
{
    "success": true,
    "message": "Ticket deleted successfully"
}
```

## 6. Error Responses

All endpoints may return the following error responses:

### 6.1 400 Bad Request
```json
{
    "error": true,
    "message": "Error message here"
}
```

### 6.2 404 Not Found
```json
{
    "error": true,
    "message": "Resource not found"
}
```

### 6.3 405 Method Not Allowed
```json
{
    "error": true,
    "message": "Method not allowed"
}
```

## 7. Important Notes

1. **Company Context**:
   - All operations are scoped to the company context
   - Resources can only be accessed/modified within the company scope

2. **Required Fields**:
   - Vehicle Types: name
   - Owners: name, phone, email, address
   - Trips: vehicle_id, trip_code, departure_time, arrival_time, status
   - Tickets: vehicle_id, trip_id, officer_id, destination_id, route, location

3. **Status Values**:
   - Trips: scheduled, in_progress, completed, cancelled
   - Tickets: unpaid, paid

4. **Relationships**:
   - Tickets are linked to vehicles, trips, officers, and destinations
   - Trips are linked to vehicles
   - Vehicles are linked to vehicle types and owners

## 8. Testing Tips

1. **Vehicle Types**:
   - Test name uniqueness
   - Verify deletion with associated vehicles

2. **Owners**:
   - Test phone number format
   - Verify email format
   - Check deletion with associated vehicles

3. **Trips**:
   - Test trip code format
   - Verify departure/arrival time logic
   - Check status transitions
   - Test with associated tickets

4. **Tickets**:
   - Test all filter combinations
   - Verify status transitions
   - Check delivery status updates
   - Test with associated bookings and offenses 