# Simulation Manager Plugin for WordPress

A lightweight WordPress plugin for managing and hosting interactive simulations directly from the WordPress admin dashboard.

---

## 🚀 Overview

The Simulation Manager Plugin allows administrators to upload, organize, and serve simulation files (HTML or ZIP packages) without using cPanel or manual server file management.

It automatically handles:

* File uploads (HTML or ZIP)
* Folder creation and organization
* ZIP extraction
* Public URL generation
* Simulation listing and management

This makes it ideal for schools, training platforms, CBT systems, and e-learning websites.

---

## ✨ Features

* Upload simulation files (HTML or ZIP)
* Automatic folder creation per simulation
* ZIP auto-extraction into dedicated folders
* Direct public URL generation for each simulation
* Admin dashboard for managing all simulations
* Edit and delete simulations
* View stored simulations in a structured table
* Optional folder content viewer

---

## 🧩 How It Works

1. Admin uploads a simulation file (HTML or ZIP)
2. Plugin creates a folder inside:

   ```
   /simulation-library/
   ```
3. If ZIP is uploaded:

   * It is extracted automatically into the folder
4. If HTML is uploaded:

   * It is stored directly in the folder
5. A public URL is generated:

   ```
   https://yourdomain.com/simulation-library/folder-name/
   ```
6. Simulation is saved in the database and displayed in the admin panel

---

## 📂 Database Structure

The plugin creates a custom table:

```
{wp_prefix}simulation
```

### Columns:

* id
* name
* folder_name
* file_name
* file_type
* link
* created_at
* updated_at

---

## 🖥️ Admin Features

### Simulation Manager Page

* Add new simulation button
* Table listing all simulations
* View simulation link
* Edit simulation details
* Delete simulation

### Add Simulation Modal

Fields:

* Name (required)
* Folder Name (optional)
* File Upload (HTML or ZIP)

---

## 📁 File Storage Structure

All files are stored in:

```
/wp-root/simulation-library/
```

Example:

```
simulation-library/
   math-test/
      index.html
      assets/
```

---

## 🔒 Security Features

* File type validation (HTML, ZIP only)
* Secure upload handling
* Nonce verification on forms
* Path traversal protection during ZIP extraction

---

## ⚙️ Requirements

* WordPress 5.8+
* PHP 7.4+
* Write permission to root directory (for folder creation)

---

## 🛠️ Installation

1. Upload plugin folder to:

   ```
   wp-content/plugins/
   ```
2. Activate plugin from WordPress dashboard
3. Go to:

   ```
   WordPress Admin → Simulation Manager
   ```
4. Start uploading simulations

---

## 🎯 Use Cases

* Online CBT systems
* School learning simulations
* Training platforms
* Interactive HTML learning modules
* Educational testing environments

---

## 🚧 Future Improvements (Optional)

* Simulation categories
* User access restrictions
* Analytics (views per simulation)
* Drag & drop file manager
* Versioning for simulations
* Cloud storage integration (S3, etc.)

---

## 📌 Notes

* Folder name determines the public URL structure
* If no folder name is provided, one is auto-generated
* ZIP files are automatically unpacked for immediate use

---

## 👨‍💻 Author

Built as a custom WordPress solution for interactive simulation hosting and management.

---

## 📄 License

Custom project license — not for redistribution without permission.
