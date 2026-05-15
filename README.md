# Online Quiz System

A full-stack web-based quiz platform that allows users to register, log in, take timed quizzes, review answers, and view leaderboard rankings. Administrators can create quizzes, manage questions, monitor user attempts, and analyze performance through a comprehensive dashboard.

---

## Features

### User Features
- User registration and login
- Secure session-based authentication
- Dynamic quiz loading from the database
- Multiple-choice questions (MCQs)
- Countdown timer for each quiz
- Automatic score calculation
- Instant result display with percentage
- Answer review with correct and incorrect highlights
- Interactive performance chart using Chart.js
- Leaderboard ranking
- Responsive dark-themed interface

### Admin Features
- Admin login and logout
- Add, edit, and delete quizzes
- Add, edit, and delete questions
- Search and filter questions
- View recent quiz attempts
- User attempt history lookup
- Quiz statistics and analytics
- Leaderboard preview

---

## Technologies Used

### Frontend
- HTML5
- CSS3
- Bootstrap 5
- JavaScript (ES6)
- Chart.js

### Backend
- PHP 7+

### Database
- MySQL / MariaDB

### Development Tools
- XAMPP or WAMP
- phpMyAdmin
- Visual Studio Code

---

## Project Structure

ONLINE_QUIZ_SYSTEM/
│
├── admin/
│   ├── dashboard.php
│   ├── login.php
│   ├── logout.php
│   └── fetch_attempts.php
│
├── api/
│   ├── get_quizzes.php
│   ├── get_questions.php
│   ├── submit_score.php
│   ├── get_scores.php
│   ├── get_leaderboard.php
│   ├── get_user_attempts.php
│   └── whoami.php
│
├── php/
│   └── database.php
│
├── quiz.php
├── quiz_script.js
├── leaderboard.php
├── login.php
├── register.html
├── logout.php
├── index.php
├── style.css
└── README.md

---

## Database Tables
- `users` – Stores registered user credentials
- `quizzes` – Stores quiz titles and descriptions
- `questions` – Stores questions, options, and correct answers
- `scores` – Stores quiz attempt results and timestamps

---

## Installation and Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/your-username/online-quiz-system.git
   cd online-quiz-system
