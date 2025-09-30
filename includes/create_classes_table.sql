-- Create classes table

CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    Level VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Populate with common classes

INSERT IGNORE INTO classes (name, Level) VALUES
('Creche', 'Pre-School'),
('Nursery 1', 'Pre-School'),
('Nursery 2', 'Pre-School'),
('KG1', 'Pre-School'),
('KG2', 'Pre-School'),
('Basic 1', 'Lower Basic'),
('Basic 2', 'Lower Basic'),
('Basic 3', 'Lower Basic'),
('Basic 4', 'Upper Basic'),
('Basic 5', 'Upper Basic'),
('Basic 6', 'Upper Basic'),
('Basic 7', 'Junior High');

-- Optionally, update students/class references later to use this table
