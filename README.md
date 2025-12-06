# 🛠️ UrbanServe – A Local Service Management System

UrbanServe is a PHP + MySQL–based platform that allows customers to book local services, providers to manage their services, and admins to oversee the system.  
This repository contains the *localservice-main* project folder with all backend and frontend functionality.

---

## 📂 Project Structure 

localservice-main/
│── about.php
│── add_service.php
│── admin_dashboard.php
│── admincontacted.php
│── book_service.php
│── bookings.php
│── contact.php
│── customer_bookings.php
│── customer_dashboard.php
│── db.php
│── newdata.sql
│── edit_my_services.php
│── edit_profile.php
│── footer.php
│── header.php
│── index.php
│── login.php
│── logout.php
│── manage_categories.php
│── manage_services.php
│── manage_users.php
│── my_services.php
│── navbar.php
│── provider_dashboard.php
│── register.php
│── service_details.php
│── update_booking_status.php
│── usercontacted.php
│── users.php
│── view_booking.php
│── view_id_proof.php
│
├── uploads/
│ ├── id_proofs/
│ ├── profiles/
│ └── services/
│
├── css/
├── js/
└── images/


---

# 🗄️ **Database Structure (FROM: newdata.sql)**  

newdata.sql creates and uses these major tables:

### **1️⃣ users**
Stores all user accounts  
Columns include:
- `user_id`
- `username`
- `email`
- `password`
- `role` (customer / provider / admin)
- `city`, `state`, `pincode`
- `bio`, `experience`
- `profile_image`

---

### **2️⃣ providers**
Extra information for providers  
- `provider_id`
- `user_id`
- `id_proof`
- `verification_status`

---

### **3️⃣ categories**
Service categories such as:
- Plumbing  
- Cleaning  
- Electrical  
- Repair  
- Etc.

---

### **4️⃣ services**
Each service under a category  
Columns:
- `service_id`
- `category_id`
- `service_name`
- `price`
- `image`

---

### **5️⃣ provider_services**
Mapping table: which provider offers which services  
- `provider_service_id`
- `provider_id`
- `service_id`

---

### **6️⃣ bookings**
Stores all bookings  
Columns:
- `booking_id`
- `customer_id`
- `provider_id`
- `service_id`
- `datetime`
- `status` (pending, confirmed, completed, cancelled)

---

### **7️⃣ usercontacted**
Stores messages submitted through Contact page  
- `contact_id`
- `name`, `email`, `message`

---

### **8️⃣ admincontacted**
Messages for admin dashboard

---

# 🚀 Features

## **👤 Customer Features**
- Register & login  
- View categories & services  
- Book service: `book_service.php`  
- Manage bookings (customer_bookings.php)  
- Update profile  
- Contact support  

---

## **🧑‍🔧 Provider Features**
- Provider registration + profile  
- Add offered services  
- Manage their services  
- View bookings & update status  
- Upload ID proof → Admin verifies  
- Dashboard with stats  

---

## **🛡️ Admin Features**
- Approve provider ID proofs  
- Manage users  
- Manage categories  
- Manage services  
- View all bookings  
- Handle customer/provider queries  

---

# ⚙️ Installation Guide

### **1️⃣ Move project to XAMPP**
C:/xampp/htdocs/localservice-main/

### **2️⃣ Create database**
Open phpMyAdmin → Create DB: urbanserve
Import:   newdata.sql


### **3️⃣ Configure connection (db.php)**

```php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "urbanserve";

4️⃣ Run
http://localhost/localservice-main/

🔮 Future Enhancements

Rating & review system
Admin analytics
Wallet / payment gateway
Push notifications

🧑‍💻 Author
Alfin Sebastian – BCA Mini Project
