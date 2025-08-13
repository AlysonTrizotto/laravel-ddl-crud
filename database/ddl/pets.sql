-- Criação da tabela de animais de estimação para um sistema de pet shop (MySQL)

CREATE TABLE pet_shop_pets (
    uuid CHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    species VARCHAR(50) NOT NULL,
    breed VARCHAR(50),
    birth_date DATE,
    weight DECIMAL(6,2),
    owner_uuid CHAR(36) NOT NULL,
    vaccinated BOOLEAN DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_pet_owner FOREIGN KEY (owner_uuid) REFERENCES pet_shop_owners(uuid)
);

-- Índice para busca rápida por nome e espécie
CREATE INDEX idx_pets_name_species ON pet_shop_pets(name, species);