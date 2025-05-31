
# 🚍 Ticketing System — Backend Developer Brief

Welcome to the Vehicle Ticketing System. This project powers ticket sales, booking, and delivery reconciliation for multiple transportation companies via a mobile app and a centralized backend.

---

## 🛠 Stack

- **Backend**: PHP (PDO + REST API)
- **Frontend**: React Native
- **Database**: MySQL
- **Security**: Session-based login + registered device verification

---

## 🧩 System Modules

### Multi-Company Architecture

- Each company has its own users, offices, vehicles, routes, and settings.
- Most tables reference `company_id` for scope.

### Booking Flow

1. A **trip** is created for a vehicle.
2. **Bookings** are made in advance for seats.
3. **Tickets** are issued — either walk-in or converted from bookings.
4. A **delivery** is generated after ticketing, summarizing total revenue and applying deductions.

---

## 🗃 Key Database Tables

- `companies`, `users`, `offices`: core structure
- `vehicles`, `vehicle_owners`, `vehicle_seats`: vehicle config
- `routes`, `destinations`, `fares`: travel paths and prices
- `trips`: individual vehicle journeys
- `bookings`: reserved seats
- `tickets`: issued for passengers (linked to booking or standalone)
- `deliveries`: reconciles ticket revenue + deductions
- `delivery_deductions`: each company’s standard deductions
- `devices`: limits ticketing to approved devices

---

## 🔐 Security

- Only **logged in users** can access booking/ticketing endpoints.
- Devices must be pre-registered with `/devices/register.php`
- Device ID is passed in header: `X-Device-ID`

---

## 🧾 Key Endpoints

| Module      | Endpoint                   | Description |
|-------------|----------------------------|-------------|
| auth        | `login.php`                | Session login with device check |
| devices     | `register.php`             | Register a hardware device |
| trips       | `create.php`               | Create a trip |
| bookings    | `create.php`               | Reserve a seat |
| tickets     | `create.php`               | Issue a ticket directly |
| tickets     | `convert-booking.php`      | Convert a booking to ticket (seat check + override supported) |
| deliveries  | `create.php`               | Create delivery record (receives structured deductions) |

---

## 🧪 Testing Endpoints with Postman

To test the API endpoints, you can use a tool like Postman.

### Prerequisites

1.  **Web Server**: Ensure you have a local web server (e.g., XAMPP, MAMP, WAMP) running and serving the project files from a directory like `htdocs/tkt-backup/`. The base URL will typically be `http://localhost/tkt-backup/`.
2.  **Database**:
    *   Make sure your MySQL server is running.
    *   Create a database named `tkt` (or as configured in `config/db.php` or your environment variables).
    *   Import the necessary database schema (tables like `devices`, `users`, etc.).
    *   Ensure the database credentials in `config/db.php` match your local setup, or set the corresponding environment variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`).
3.  **Postman**: Download and install Postman from postman.com.

### General Postman Request Setup

*   **Method**: Select the appropriate HTTP method (e.g., `POST`, `GET`).
*   **URL**: Enter the full URL to the endpoint (e.g., `http://localhost/tkt-backup/devices/register.php`).
*   **Headers**:
    *   For `POST` or `PUT` requests sending JSON data, add:
        *   `Key`: `Content-Type`
        *   `Value`: `application/json`
    *   For endpoints requiring device identification (after registration), add:
        *   `Key`: `X-Device-ID`
        *   `Value`: The `device_uuid` of the registered device.
*   **Body**: For `POST` or `PUT` requests, go to the "Body" tab, select "raw", and choose "JSON" from the dropdown. Then, enter your JSON payload.

### Example 1: Registering a Device

Endpoint: `POST /devices/register.php`

1.  **Method**: `POST`
2.  **URL**: `http://localhost/tkt-backup/devices/register.php`
3.  **Headers**:
    *   `Content-Type`: `application/json`
4.  **Body** (raw, JSON):
    ```json
    {
      "device_uuid": "YOUR_UNIQUE_DEVICE_UUID_HERE",
      "device_name": "My Test Device"
    }
    ```
    Replace `"YOUR_UNIQUE_DEVICE_UUID_HERE"` with a unique string.
5.  **Send** the request.

    *   **Successful Response (201 Created)**:
        ```json
        {
          "message": "Device registered successfully",
          "device_id": 1 // This is the auto-incremented ID from the database
        }
        ```
    *   **Error Response (400 Bad Request - Device already registered)**:
        ```json
        {
          "error": "Device already registered"
        }
        ```

### Example 2: Logging In (Conceptual - requires `X-Device-ID`)

Endpoint: `POST /auth/login.php` (This endpoint requires a registered device)

1.  **Method**: `POST`
2.  **URL**: `http://localhost/tkt-backup/auth/login.php`
3.  **Headers**:
    *   `Content-Type`: `application/json`
    *   `X-Device-ID`: Use the `device_uuid` you used during device registration (e.g., `"YOUR_UNIQUE_DEVICE_UUID_HERE"`).
4.  **Body** (raw, JSON - fields depend on `login.php` implementation):
    The `login.php` script expects `email`, `password`, and `device_id` (which is the device's UUID) in the body.
    ```json
    {
      "username": "your_username",
      "password": "your_password"
    }
    ```
5.  **Send** the request.

    *   Look for a successful login response (e.g., session token) or error messages.

### Testing Other Endpoints

Refer to the "Key Endpoints" table below for module paths and specific PHP files.

*   For endpoints that create or modify data (`create.php`, `convert-booking.php`), you will typically use `POST`.
*   Remember to include the `X-Device-ID` header with the `device_uuid` for endpoints that require device verification.
*   Check the specific PHP file or documentation for required JSON body parameters.

---

## 💡 Delivery Logic

- Frontend fetches predefined deduction rules from `delivery_deductions`
- User can modify deduction amounts depending on trip
- Final deduction array is passed to `deliveries/create.php`
- Backend calculates:
  - Total tickets
  - Gross fare
  - Total deductions
  - Net revenue
- Stores deductions as `JSON` in `deliveries`

---



---

For any questions, talk to the lead developer or refer to this briefing.