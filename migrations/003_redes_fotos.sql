-- Adiciona redes sociais e fotos a instalações que já possuem businesses.
ALTER TABLE businesses
 ADD COLUMN instagram VARCHAR(500) NOT NULL DEFAULT '' AFTER website,
 ADD COLUMN facebook VARCHAR(500) NOT NULL DEFAULT '' AFTER instagram,
 ADD COLUMN tiktok VARCHAR(500) NOT NULL DEFAULT '' AFTER facebook,
 ADD COLUMN youtube VARCHAR(500) NOT NULL DEFAULT '' AFTER tiktok,
 ADD COLUMN photos TEXT NOT NULL AFTER youtube;
