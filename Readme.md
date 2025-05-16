
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