<?php
include 'includes/db.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `faculty_number` VARCHAR(50) NOT NULL UNIQUE,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(100),
        `role` VARCHAR(50) NOT NULL DEFAULT 'user',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `teams` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `team_name` VARCHAR(100) NOT NULL,
        `leader_id` INT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`leader_id`) REFERENCES `users`(`id`)
            ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `team_members` (
        `team_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `role_in_team` VARCHAR(50),
        PRIMARY KEY (`team_id`, `user_id`),
        FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `projects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `status` VARCHAR(50) DEFAULT 'open',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS project_team (
        project_id INT NOT NULL,
        team_id INT NOT NULL,
        PRIMARY KEY (project_id, team_id),
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `system_estimated_hours` INT DEFAULT 0,
        `parent_id` INT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`parent_id`) REFERENCES `tasks`(`id`)
            ON DELETE SET NULL
            ON UPDATE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `user_project_task` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `task_id` INT NOT NULL,
        `team_estimated_hours` INT DEFAULT 0,
        `actual_hours` INT DEFAULT 0,
        `status` VARCHAR(50) DEFAULT 'pending',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE,
        FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB"
];

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table created or already exists.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

$conn->close();

echo "Setup complete!";
