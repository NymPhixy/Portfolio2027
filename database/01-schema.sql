
CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    summary TEXT NULL,
    description TEXT NULL,
    client VARCHAR(150) NULL,
    year SMALLINT UNSIGNED NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',

    role VARCHAR(150) NULL,
    technologies TEXT NULL,
    challenge TEXT NULL,
    solution TEXT NULL,
    result TEXT NULL,

    cover_image VARCHAR(255) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;