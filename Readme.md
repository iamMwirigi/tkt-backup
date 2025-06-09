# API Testing Guide: TKT Backup System

This guide walks you through testing key workflows for the TKT Backup System API, hosted at `https://tkt-backup.onrender.com`. These examples use Postman, but any API testing tool can be used.

## Prerequisites

- An API client like Postman installed.
- A stable internet connection.
- Access to the API base URL: `https://tkt-backup.onrender.com`

---

## 1. Register Device

Before authenticating, a device must be registered with a unique UUID and a descriptive name.

### Request

- **Method:** `POST`
- **URL:** `https://tkt-backup.onrender.com/devices/register.php`
- **Headers:**
  ```json
  {
    "Content-Type": "application/json"
  }
  ```
- **Body (Raw JSON):**
  ```json
  {
    "device_uuid": "my-awesome-test-device-001",
    "device_name": "My Test Device"
  }
  ```

### Expected Response

- **Status:** `201 Created`
- **Body:**
  ```json
  {
    "message": "Device registered successfully",
    "device_id": "1" 
  }
  ```
  *(Note: `device_id` is the database primary key for the device record).*

---

## 2. Login User

After device registration, log in with user credentials and the registered device ID. This establishes a session required for subsequent authenticated requests.

### Request

- **Method:** `POST`
- **URL:** `https://tkt-backup.onrender.com/auth/login.php`
- **Headers:**
  ```json
  {
    "Content-Type": "application/json",
    "X-Device-ID": "my-awesome-test-device-001" 
  }
  ```
  *(Use the same `device_uuid` from the device registration step for `X-Device-ID`)*

> **Important:** After a successful login, the server will set a session cookie (e.g., `PHPSESSID`). Ensure your API client (like Postman) is configured to automatically send this cookie with subsequent requests to maintain the session.

- **Body (Raw JSON):**
  ```json
  {
    "email": "clerk@zawadi.co.ke",
    "password": "password",
    "device_id": "my-awesome-test-device-001" 
  }
  ```
  *(The `device_id` in the body should also match the registered `device_uuid`)*

### Expected Response

- **Status:** `200 OK`
- **Body:**
  ```json
  {
    "message": "Login successful",
    "user": {
      "id": 2,
      "name": "Booking Clerk",
      "email": "clerk@zawadi.co.ke",
      "role": "clerk",
      "company": "Zawadi Express"
    }
  }
  ```

---

## 3. Create Trip

Once logged in, you can create a new trip. This request requires the active session (via cookie) and the `X-Device-ID` header.

### Request

- **Method:** `POST`
- **URL:** `https://tkt-backup.onrender.com/trips/create.php`
- **Headers:**
  ```json
  {
    "Content-Type": "application/json",
    "X-Device-ID": "my-awesome-test-device-001" 
  }
  ```
  *(Ensure the session cookie from login is also sent with this request)*

- **Body (Raw JSON):**
  ```json
  {
    "vehicle_id": 1,
    "route_id": 1,
    "driver_name": "Test Driver",
    "conductor_name": "Test Conductor",
    "departure_time": "2025-06-02 10:00:00" 
  }
  ```
  *(Ensure `vehicle_id` and `route_id` correspond to existing records in your database for the logged-in user's company.)*

### Expected Response

- **Status:** `201 Created`
- **Body:**
  ```json
  {
    "message": "Trip created successfully",
    "trip": {
        "id": "some_trip_id", 
        "trip_code": "GENERATED_TRIP_CODE",
        "status": "pending"
    }
  }
  ```

---

## 4. List Vehicles

After logging in, you can retrieve a list of vehicles for your company. This endpoint requires an active session.

### Request

- **Method:** `GET`
- **URL:** `https://tkt-backup.onrender.com/vehicles/list.php`
- **Headers:**
  ```json
  {
    "Content-Type": "application/json"
  }
  ```
  *(Ensure the session cookie from login is sent with this request)*

### Expected Response

- **Status:** `200 OK`
- **Body:**
  ```json
  {
    "error": false,
    "message": "Vehicles retrieved successfully",
    "data": [
      {
        "id": 1,
        "plate_number": "KBR 123A",
        "vehicle_type": "Bus",
        "created_at": "2025-04-14 10:09:04",
        "owner_name": "James Kariuki",
        "owner_phone": "0722000001",
        "owner_id_number": "12345678",
        "seats": [
          {
            "seat_number": "1A",
            "position": "left",
            "is_reserved": 0
          },
          {
            "seat_number": "1B",
            "position": "right",
            "is_reserved": 0
          }
        ]
      }
    ]
  }
  ```

> **Note:** This endpoint requires authentication. If you're not logged in, you'll receive an "Unauthorized access" error.


