<div align="center">

# 💬 TalkSpace

### A Modern, Real-Time Full-Stack Chat & Video Calling Platform

[![Laravel](https://img.shields.io/badge/Laravel-11%2B%20%7C%2012-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-v1.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Laravel Reverb](https://img.shields.io/badge/Laravel_Reverb-WebSockets-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/docs/reverb)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

<p align="center">
  <b>TalkSpace</b> is a feature-rich, WhatsApp-style real-time messaging and WebRTC calling web application built with Laravel, Inertia.js, Vue 3, and Laravel Reverb WebSockets.
</p>

</div>

---

## ⚡ Fast Track Setup (One-Liner Cheat Sheet)

If you already have PHP 8.3+, Composer, Node.js, and MySQL installed, you can get up and running in under 2 minutes:

```bash
# 1. Clone the repository & enter folder
git clone https://github.com/yash-bodar/TalkSpace.git && cd TalkSpace

# 2. Install dependencies
composer install && npm install

# 3. Environment & Key setup
cp .env.example .env && php artisan key:generate

# 4. (Configure DB in .env then run database setup)
php artisan migrate --seed && php artisan storage:link
```

---

## 🌟 Key Features

### ⚡ Real-Time Messaging & Presence
- **Instant Messaging**: Real-time message dispatch and reception via Laravel Reverb WebSockets.
- **WhatsApp-Style Read Receipts**:
  - `✓` **Single Grey Tick**: Message sent to server.
  - `✓✓` **Double Grey Tick**: Message delivered to all recipient devices.
  - `✓✓` **Double Blue Tick**: Message read by all conversation participants.
- **Live Online Presence**: Instant status reflection (`Online now` vs contextual `Last seen...`).
- **Typing Indicators**: Real-time typing status with smooth animations.
- **Message Editing & Deletion**:
  - Edit sent messages.
  - Delete messages with options: **"Delete for Me"** or **"Delete for Everyone"**.
- **Media & File Attachments**: Send images, documents, and files with instant previews and downloads.

### 👥 WhatsApp-Style Group Architecture
- **Admin Privileges & Management**:
  - Promote members to **Group Admin** or dismiss admins.
  - Add new members from contacts.
  - Remove members with real-time UI synchronization.
  - Safe guardrails: Ensures groups always maintain at least one active administrator.
- **Public & Private Group Links**:
  - **Public Groups**: Anyone with the link (`/chat/join/{invite_code}`) joins instantly.
  - **Private Groups**: Generates join requests that group admins review, approve, or reject.
- **Group Info Drawer**: View group logo, edit bio, manage members, and reset shareable invite links.
- **Centered System Events**: Role changes, member updates, and settings changes display as clean, centered notification badges.

### 📞 WebRTC Audio & Video Calling
- **1-on-1 Voice & Video Calls**: Peer-to-peer WebRTC signaling via Laravel Echo.
- **Call Dialogs**: Live incoming call alerts (Accept / Decline), mute/video toggles, connected timers, and call logging.

### 🎨 Premium UI / UX
- **Interactive Avatar Cropper**: Crop profile photos with a squircle mask, drag panning, and mouse-wheel zooming.
- **Conversation Pinning & Search**: Pin important conversations to the top and filter by Direct, Groups, or Unread.
- **Responsive Layout**: Mobile drawer support and desktop multi-pane design.

---

## 🛠️ Tech Stack

| Layer | Technologies |
|---|---|
| **Backend** | Laravel 11 / 12, PHP 8.3+, Eloquent ORM |
| **Frontend** | Vue 3 (Composition API / `<script setup>`), Inertia.js |
| **Styling** | Tailwind CSS, Lucide Vue Icons |
| **WebSockets** | Laravel Reverb, Laravel Echo, Pusher JS |
| **Real-time Calls** | WebRTC (STUN / PeerConnection) |
| **Database** | MySQL / MariaDB (or SQLite / PostgreSQL) |

---

## 🚀 Step-by-Step Installation Guide

### 1. Prerequisites
Ensure your system meets the following requirements:
- **PHP** >= `8.3.0` with `pdo`, `mbstring`, `openssl`, `curl`, and `gd` / `fileinfo` extensions enabled.
- **Composer** >= `2.x`
- **Node.js** >= `18.x` & **NPM**
- **MySQL** / MariaDB server

---

### 2. Clone the Repository
```bash
git clone https://github.com/yash-bodar/TalkSpace.git
cd TalkSpace
```

---

### 3. Install Dependencies
Install PHP dependencies:
```bash
composer install
```

Install Frontend NPM packages:
```bash
npm install
```

---

### 4. Configure Environment
Copy the example environment file:
```bash
cp .env.example .env
```

Generate your application encryption key:
```bash
php artisan key:generate
```

Open `.env` in your code editor and configure your database credentials:
```env
APP_NAME=TalkSpace
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=talkspace
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

Verify the **Reverb WebSocket** configuration in your `.env`:
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=275038
REVERB_APP_KEY=6xkjxshyi5q8zwogwnxv
REVERB_APP_SECRET=yxdfoq0dgelcjinq1kym
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

REVERB_SERVER_HOST="0.0.0.0"
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

### 5. Run Migrations & Storage Link
Run database migrations (and seed demo data):
```bash
php artisan migrate --seed
```

Create the symbolic link to make uploaded attachments and avatars publicly accessible:
```bash
php artisan storage:link
```

---

## 🏃 Running the Application (3 Terminal Windows)

To run the full stack with real-time WebSockets, open **3 separate terminal tabs/windows**:

#### 💻 Terminal 1: Laravel Web Server
```bash
php artisan serve
```
*(Application will run at `http://localhost:8000` or your Laragon virtual host)*

#### ⚡ Terminal 2: Reverb WebSocket Server
```bash
php artisan reverb:start --debug
```
*(Runs the real-time WebSocket broker on port `8080` with verbose event debugging)*

#### 🎨 Terminal 3: Vite Dev Server
```bash
npm run dev
```
*(Compiles and hot-reloads Vue 3 & Tailwind CSS assets in real-time)*

---

## 📟 All Useful Commands (Cheat Sheet)

### Daily Development Commands
| Action | Command |
|---|---|
| Start Laravel server | `php artisan serve` |
| Start Reverb WebSockets | `php artisan reverb:start --debug` |
| Start Vite asset compiler | `npm run dev` |
| Build for Production | `npm run build` |

### Database & Setup Commands
| Action | Command |
|---|---|
| Run pending migrations | `php artisan migrate` |
| Fresh reset DB with seed data | `php artisan migrate:fresh --seed` |
| Create storage symlink | `php artisan storage:link` |
| Generate APP_KEY | `php artisan key:generate` |

### Cache & Maintenance Commands
| Action | Command |
|---|---|
| Clear all application caches | `php artisan optimize:clear` |
| Clear config cache | `php artisan config:clear` |
| Clear route cache | `php artisan route:clear` |
| View registered routes | `php artisan route:list` |
| Check PHP interactive shell | `php artisan tinker` |

---

## 🌐 Testing on Local Network (LAN / Multiple Devices)

To test between multiple laptops or phones on your local Wi-Fi:

1. Find your local machine IP address (e.g. `192.168.1.180`).
2. Update your `.env`:
   ```env
   APP_URL=http://192.168.1.180:8000
   REVERB_HOST="192.168.1.180"
   VITE_REVERB_HOST="192.168.1.180"
   ```
3. Start the servers with binding on `0.0.0.0`:
   ```bash
   # Terminal 1:
   php artisan serve --host=0.0.0.0 --port=8000

   # Terminal 2:
   php artisan reverb:start --host=0.0.0.0 --port=8080 --debug

   # Terminal 3:
   npm run dev -- --host
   ```
4. Access `http://192.168.1.180:8000` from any phone or computer on the same network!

---

## 📁 Project Structure

```
TalkSpace/
├── app/
│   ├── Events/               # Real-time WebSocket broadcast events (MessageSent, GroupRoleUpdated, etc.)
│   ├── Http/
│   │   ├── Controllers/      # ChatController, ProfileController
│   │   ├── Requests/         # Form validation request classes
│   │   └── Resources/        # JSON API & Inertia resources (Message, User, Conversation)
│   ├── Models/               # Conversation, Message, User, ConversationUser, GroupJoinRequest
│   └── Services/             # ChatService (Core messaging & group business logic)
├── database/
│   └── migrations/           # Database schema migrations
├── resources/
│   └── js/
│       ├── Layouts/          # AuthenticatedLayout
│       ├── Pages/
│       │   ├── Chat/         # Chat Index & WhatsApp UI components
│       │   └── Profile/      # User profile edit & image cropper
│       └── echo.js           # Laravel Echo & Reverb client configuration
├── routes/
│   ├── web.php               # Application routes
│   └── channels.php          # Presence and private broadcast authorization channels
└── README.md
```

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome!
Feel free to check the [Issues page](https://github.com/yash-bodar/TalkSpace/issues).

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This project is open-sourced under the [MIT License](LICENSE).
