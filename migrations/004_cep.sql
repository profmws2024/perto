-- Adiciona o CEP aos comércios existentes.
ALTER TABLE businesses
 ADD COLUMN cep VARCHAR(9) NOT NULL DEFAULT '' AFTER address;
