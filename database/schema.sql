-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS petquest
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- Use the database
USE petquest;

-- Users and Authentication Tables
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    bio TEXT NULL,
    password VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255) NULL DEFAULT NULL,
    cover_picture VARCHAR(255) NULL DEFAULT NULL,
    phone VARCHAR(20) NULL DEFAULT NULL,
    address TEXT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pets Table
CREATE TABLE IF NOT EXISTS pets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    species VARCHAR(100) NOT NULL,
    breed VARCHAR(100) NULL DEFAULT NULL,
    color VARCHAR(100) NULL DEFAULT NULL,
    age INT NULL DEFAULT NULL,
    gender ENUM('male', 'female', 'unknown') DEFAULT 'unknown',
    description TEXT NULL,
    image_path VARCHAR(255) NULL DEFAULT NULL,
    qr_code_path VARCHAR(255) NULL DEFAULT NULL,
    status ENUM('safe', 'active', 'missing', 'found') DEFAULT 'active', -- Added 'safe' as a common default
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Missing Pets Reports
CREATE TABLE IF NOT EXISTS missing_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    owner_id INT NOT NULL,
    last_seen_date DATE NOT NULL,
    last_seen_location TEXT NOT NULL,
    contact_info VARCHAR(255) NOT NULL,
    additional_info TEXT NULL,
    status ENUM('active', 'resolved') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Found Pets Reports
CREATE TABLE IF NOT EXISTS found_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    founder_name VARCHAR(255) NOT NULL,
    founder_email VARCHAR(255) NOT NULL,
    founder_phone VARCHAR(20) NULL DEFAULT NULL,
    pet_id INT NULL, -- Can be null if pet is not yet identified in the system
    found_location TEXT NOT NULL,
    description TEXT NULL,
    image_path VARCHAR(255) NULL DEFAULT NULL,
    status ENUM('pending', 'matched', 'resolved') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for conversations
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    founder_name VARCHAR(255) NOT NULL, -- Could be a user ID if founder is a registered user
    founder_email VARCHAR(255) NOT NULL, -- Or user_id
    owner_id INT NOT NULL,
    -- owner_name VARCHAR(255) NOT NULL, -- Redundant if owner_id links to users.name
    -- owner_email VARCHAR(255) NOT NULL, -- Redundant
    owner_unread_count INT DEFAULT 0,
    founder_unread_count INT DEFAULT 0,
    last_message_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
    -- Consider FK for founder if they are users
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for messages (unified or separate, here unified example)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL, -- User ID of the sender
    -- sender_role ENUM('owner', 'founder') NOT NULL, -- To distinguish if sender is owner or founder
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE, -- Can be tricky for group chats, simpler for 1-on-1
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE -- Assumes founders are also users or have a user entry
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('message', 'report_update', 'memory_comment', 'memory_reaction', 'pet_match', 'system') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL, -- Optional link related to the notification
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password Reset Tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Memories table (Stores the main post/text content)
CREATE TABLE IF NOT EXISTS memories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    description TEXT NULL, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Media items for memories (images or videos)
CREATE TABLE IF NOT EXISTS memory_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    memory_id INT NOT NULL,
    user_id INT NOT NULL, 
    media_type ENUM('image', 'video') NOT NULL,
    file_path VARCHAR(255) NOT NULL, 
    original_filename VARCHAR(255) NULL,
    file_size INT NULL,
    sort_order INT DEFAULT 0, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (memory_id) REFERENCES memories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments on Memories
CREATE TABLE IF NOT EXISTS memory_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    memory_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (memory_id) REFERENCES memories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reactions to Memories
CREATE TABLE IF NOT EXISTS memory_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    memory_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_type VARCHAR(50) NOT NULL, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (memory_id, user_id), 
    FOREIGN KEY (memory_id) REFERENCES memories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Profile Reports Table
CREATE TABLE IF NOT EXISTS profile_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_user_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    details TEXT NULL,
    status ENUM('pending', 'reviewed', 'action_taken', 'dismissed') DEFAULT 'pending',
    reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewer_notes TEXT NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT uc_report UNIQUE (reporter_id, reported_user_id, reason) -- Avoid exact duplicate reports by same user for same reason
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_pets_owner ON pets(owner_id);
CREATE INDEX idx_pets_status ON pets(status);
CREATE INDEX idx_missing_reports_pet_id ON missing_reports(pet_id);
CREATE INDEX idx_missing_reports_status ON missing_reports(status);
CREATE INDEX idx_found_reports_pet_id ON found_reports(pet_id);
CREATE INDEX idx_found_reports_status ON found_reports(status);
CREATE INDEX idx_conversations_pet_id ON conversations(pet_id);
CREATE INDEX idx_conversations_owner_id ON conversations(owner_id);
CREATE INDEX idx_messages_conversation_id ON messages(conversation_id);
CREATE INDEX idx_messages_sender_id ON messages(sender_id);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_type ON notifications(type);
CREATE INDEX idx_memories_user_id ON memories(user_id);
CREATE INDEX idx_memory_media_memory_id ON memory_media(memory_id);
CREATE INDEX idx_memory_media_user_id ON memory_media(user_id);
CREATE INDEX idx_memory_media_type ON memory_media(media_type);
CREATE INDEX idx_memory_comments_memory_id ON memory_comments(memory_id);
CREATE INDEX idx_memory_comments_user_id ON memory_comments(user_id);
CREATE INDEX idx_memory_reactions_memory_id ON memory_reactions(memory_id);
CREATE INDEX idx_memory_reactions_user_id ON memory_reactions(user_id);
CREATE INDEX idx_profile_reports_reporter ON profile_reports(reporter_id);
CREATE INDEX idx_profile_reports_reported_user ON profile_reports(reported_user_id);
CREATE INDEX idx_profile_reports_status ON profile_reports(status);
