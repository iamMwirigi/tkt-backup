# TKT API Documentation

## Base URL
```
https://tkt-backup.onrender.com
```

## Authentication
Currently using dummy values for testing:
- user_id: 1
- company_id: 1
- user_role: admin

## API Endpoints

### Routes

#### List Routes
```http
GET /routes/list.php
```
Response:
```json
{
    "success": true,
    "message": "Routes retrieved successfully",
    "data": {
        "routes": [
            {
                "id": 1,
                "name": "Nairobi - Kisumu",
                "description": "Main western route via Nakuru, Kericho",
                "company_id": 1,
                "created_at": "2025-04-14 10:09:04",
                "destination_count": 4
            }
        ]
    }
}
```

#### Manage Routes
```http
POST /routes/manage.php
```
Request:
```json
{
    "name": "Nairobi - Mombasa",
    "description": "Coastal route via Voi"
}
```

```http
PUT /routes/manage.php
```
Request:
```json
{
    "id": 1,
    "name": "Nairobi - Mombasa",
    "description": "Updated coastal route via Voi"
}
```

```http
DELETE /routes/manage.php
```
Request:
```json
{
    "id": 1
}
```

### Destinations

#### List Destinations
```http
GET /destinations/list.php
```
Response:
```json
{
    "success": true,
    "message": "Destinations retrieved successfully",
    "data": {
        "destinations": [
            {
                "id": 1,
                "route_id": 1,
                "name": "Naivasha",
                "stop_order": 1,
                "route_name": "Nairobi - Kisumu",
                "route_description": "Main western route via Nakuru, Kericho",
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

#### Manage Destinations
```http
POST /destinations/manage.php
```
Request:
```json
{
    "route_id": 1,
    "name": "Naivasha",
    "stop_order": 1,
    "fares": [
        {
            "label": "Standard",
            "amount": 500.00
        },
        {
            "label": "VIP",
            "amount": 800.00
        }
    ]
}
```

```http
PUT /destinations/manage.php
```
Request:
```json
{
    "id": 1,
    "name": "Naivasha",
    "stop_order": 1,
    "fares": [
        {
            "label": "Standard",
            "amount": 550.00
        }
    ]
}
```

```http
DELETE /destinations/manage.php
```
Request:
```json
{
    "id": 1
}
```

### Fares

#### List Fares
```http
GET /fares/list.php
```
Response:
```json
{
    "success": true,
    "message": "Fares retrieved successfully",
    "data": {
        "fares": [
            {
                "id": 1,
                "destination_id": 1,
                "label": "Standard",
                "amount": "500.00",
                "destination_name": "Naivasha",
                "stop_order": 1,
                "route_id": 1,
                "route_name": "Nairobi - Kisumu",
                "route_description": "Main western route via Nakuru, Kericho"
            }
        ]
    }
}
```

#### Manage Fares
```http
POST /fares/manage.php
```
Request:
```json
{
    "destination_id": 1,
    "label": "Standard",
    "amount": 500.00
}
```

```http
PUT /fares/manage.php
```
Request:
```json
{
    "id": 1,
    "label": "Standard",
    "amount": 550.00
}
```

```http
DELETE /fares/manage.php
```
Request:
```json
{
    "id": 1
}
```

### Devices

#### Register Device
```http
POST /devices/register.php
```
Request:
```json
{
    "device_uuid": "test-device-001",
    "device_name": "Test Device"
}
```

#### Manage Devices
```http
POST /devices/manage.php
```
Request:
```json
{
    "device_uuid": "test-device-001",
    "device_name": "Test Device"
}
```

## Database Schema

### Routes
- id (int, primary key)
- company_id (int, foreign key)
- name (varchar)
- description (text)
- created_at (datetime)

### Destinations
- id (int, primary key)
- route_id (int, foreign key)
- name (varchar)
- stop_order (int)

### Fares
- id (int, primary key)
- destination_id (int, foreign key)
- label (varchar)
- amount (decimal)
- created_at (datetime)

### Devices
- id (int, primary key)
- company_id (int, foreign key)
- user_id (int, foreign key)
- device_uuid (varchar, unique)
- device_name (varchar)
- is_active (tinyint)
- registered_at (datetime)

## Notes
1. All endpoints require company_id for data isolation
2. Destinations require at least one fare to be created
3. Fares are linked to destinations
4. Routes can have multiple destinations
5. Devices must have unique UUIDs
6. All monetary amounts are in decimal format
7. All timestamps are in UTC