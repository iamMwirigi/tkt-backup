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

---

## 5. Create Booking

After creating a trip, you can create a booking for a specific seat. This endpoint requires an active session and the trip must exist.

### Request

- **Method:** `POST`
- **URL:** `https://tkt-backup.onrender.com/bookings/create.php`
- **Headers:**
  ```json
  {
    "Content-Type": "application/json",
    "X-Device-ID": "my-awesome-test-device-001"
  }
  ```
  *(Ensure the session cookie from login is sent with this request)*

- **Body (Raw JSON):**
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

### Required Fields:
- `trip_id`: ID of the trip (from trip creation)
- `customer_name`: Name of the customer
- `customer_phone`: Phone number of the customer
- `destination_id`: ID of the destination
- `seat_number`: Seat number to book (e.g., "1A")
- `fare_amount`: Amount to charge
- `status`: (optional) Defaults to "booked"

### Expected Response

- **Status:** `201 Created`
- **Body:**
  ```json
  {
    "success": true,
    "message": "Booking created successfully.",
    "booking_id": 1
  }
  ```

### Error Responses:

1. **Unauthorized (401):**
   ```json
   {
     "error": true,
     "message": "Unauthorized. Please login."
   }
   ```

2. **Seat Already Booked (409):**
   ```json
   {
     "error": true,
     "message": "Seat 1A is already booked for this trip."
   }
   ```

3. **Invalid Fare Amount (400):**
   ```json
   {
     "error": true,
     "message": "Invalid fare amount."
   }
   ```

> **Important Notes:**
> 1. Make sure you're logged in first
> 2. The trip must exist and belong to your company
> 3. The seat must be available
> 4. The destination must exist and belong to your company's route
> 5. The fare amount must be a positive number

## 6. Create Ticket

You can create a ticket directly (walk-in) or convert an existing booking to a ticket. Both methods require an active session.

### Direct Ticket Creation

- **Method:** `POST`
- **URL:** `https://tkt-backup.onrender.com/tickets/create.php`
- **Headers:**
  ```json
  {
    "Content-Type": "application/json",
    "X-Device-ID": "my-awesome-test-device-001"
  }
  ```
  *(Ensure the session cookie from login is sent with this request)*

- **Body (Raw JSON):**
  ```json
  {
    "trip_id": 1,
    "destination_id": 1,
    "seat_number": "1A",
    "fare_amount": 1000,
    "location": "Terminal",
    "payment_method": "cash",
    "transaction_id": null
  }
  ```

### Required Fields:
- `trip_id`: ID of the trip
- `destination_id`: ID of the destination
- `seat_number`: Seat number to book
- `fare_amount`: Amount to charge

### Optional Fields:
- `location`: Where the ticket is being issued (defaults to "Terminal")
- `payment_method`: Method of payment (defaults to "cash")
- `transaction_id`: External payment reference (optional)

### Expected Response

- **Status:** `201 Created`
- **Body:**
  ```json
  {
    "success": true,
    "message": "Ticket created successfully",
    "ticket_id": 1
  }
  ```

### Error Responses:

1. **Unauthorized (401):**
   ```json
   {
     "error": true,
     "message": "Unauthorized. Please login."
   }
   ```

2. **Seat Already Taken (400):**
   ```json
   {
     "error": true,
     "message": "Seat 1A is already taken for this trip"
   }
   ```

3. **Invalid Destination (400):**
   ```json
   {
     "error": true,
     "message": "Invalid destination for this route"
   }
   ```

### Convert Booking to Ticket

- **Method:** `POST`
- **URL:** `https://tkt-backup.onrender.com/tickets/convert-booking.php`
- **Headers:**
  ```json
  {
    "Content-Type": "application/json",
    "X-Device-ID": "my-awesome-test-device-001"
  }
  ```
  *(Ensure the session cookie from login is sent with this request)*

- **Body (Raw JSON):**
  ```json
  {
    "booking_id": 1,
    "location": "Terminal",
    "payment_method": "cash",
    "transaction_id": null,
    "override_seat": false
  }
  ```

### Required Fields:
- `booking_id`: ID of the booking to convert

### Optional Fields:
- `location`: Where the ticket is being issued (defaults to "Terminal")
- `payment_method`: Method of payment (defaults to "cash")
- `transaction_id`: External payment reference (optional)
- `override_seat`: Set to true to force conversion even if seat is taken (defaults to false)

### Expected Response

- **Status:** `201 Created`
- **Body:**
  ```json
  {
    "success": true,
    "message": "Booking converted to ticket successfully",
    "ticket_id": 1
  }
  ```

### Error Responses:

1. **Unauthorized (401):**
   ```json
   {
     "error": true,
     "message": "Unauthorized. Please login."
   }
   ```

2. **Booking Not Found (400):**
   ```json
   {
     "error": true,
     "message": "Booking not found or doesn't belong to your company"
   }
   ```

3. **Already Converted (400):**
   ```json
   {
     "error": true,
     "message": "This booking has already been converted to a ticket"
   }
   ```

4. **Seat Already Taken (400):**
   ```json
   {
     "error": true,
     "message": "Seat 1A is already taken for this trip. Use override_seat=true to force conversion."
   }
   ```

> **Important Notes:**
> 1. Make sure you're logged in first
> 2. For direct ticket creation:
>    - The trip must exist and belong to your company
>    - The seat must be available
>    - The destination must exist and belong to your company's route
>    - The fare amount must be a positive number
> 3. For booking conversion:
>    - The booking must exist and belong to your company
>    - The booking must not be already converted
>    - The seat must be available (unless override_seat is true)


