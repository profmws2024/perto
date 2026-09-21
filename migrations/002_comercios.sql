-- Apenas para quem instalou a versão anterior de notícias. Faça backup antes.
-- Cria os cadastros de comércios sem apagar notícias, usuários ou logs antigos.
CREATE TABLE businesses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 summary VARCHAR(300) NOT NULL,
 description TEXT NOT NULL,
 category VARCHAR(50) NOT NULL,
 city VARCHAR(100) NOT NULL,
 neighborhood VARCHAR(100) NOT NULL,
 address VARCHAR(300) NOT NULL,
 hours VARCHAR(1000) NOT NULL,
 whatsapp VARCHAR(20) NOT NULL DEFAULT '',
 website VARCHAR(500) NOT NULL DEFAULT '',
 instagram VARCHAR(500) NOT NULL DEFAULT '',
 facebook VARCHAR(500) NOT NULL DEFAULT '',
 tiktok VARCHAR(500) NOT NULL DEFAULT '',
 youtube VARCHAR(500) NOT NULL DEFAULT '',
 photos TEXT NOT NULL,
 image VARCHAR(500) NOT NULL DEFAULT '',
 status ENUM('draft','pending','published') NOT NULL DEFAULT 'pending',
 featured TINYINT NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 consent_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES users(id),
 INDEX idx_status_city (status,city),
 INDEX idx_category (category,status),
 INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE audit_log ADD COLUMN business_id BIGINT UNSIGNED NULL;
