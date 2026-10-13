-- Migración aditiva para MariaDB 10.4+. Aplicar una vez en devioz_parking.
-- No altera stream_url, IDs ni registros existentes.
USE devioz_parking;
ALTER TABLE cameras
 ADD COLUMN IF NOT EXISTS channel_number TINYINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS location VARCHAR(150) NULL,
 ADD COLUMN IF NOT EXISTS rtsp_path VARCHAR(255) NULL,
 ADD COLUMN IF NOT EXISTS stream_protocol ENUM('hls','webrtc') NOT NULL DEFAULT 'hls',
 ADD COLUMN IF NOT EXISTS preferred_quality ENUM('sub','main') NOT NULL DEFAULT 'sub',
 ADD COLUMN IF NOT EXISTS brightness SMALLINT UNSIGNED NOT NULL DEFAULT 100,
 ADD COLUMN IF NOT EXISTS contrast SMALLINT UNSIGNED NOT NULL DEFAULT 100,
 ADD COLUMN IF NOT EXISTS saturation SMALLINT UNSIGNED NOT NULL DEFAULT 100,
 ADD COLUMN IF NOT EXISTS osd_datetime TINYINT(1) NOT NULL DEFAULT 1,
 ADD COLUMN IF NOT EXISTS osd_camera_name TINYINT(1) NOT NULL DEFAULT 1;

-- Comprobar conflictos antes de asignar los dos canales históricos.
DELIMITER //
CREATE PROCEDURE devioz_migrate_camera_channels()
BEGIN
 IF EXISTS (SELECT 1 FROM cameras WHERE channel_number IS NOT NULL AND channel_number NOT BETWEEN 1 AND 4)
 OR EXISTS (SELECT 1 FROM cameras WHERE channel_number IS NOT NULL GROUP BY parking_id,channel_number HAVING COUNT(*) > 1)
 OR EXISTS (SELECT 1 FROM cameras a JOIN cameras b ON a.parking_id=b.parking_id AND a.id<>b.id
            WHERE a.id IN (1,2) AND a.channel_number IS NULL AND b.channel_number=a.id)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conflicto de canales: revisar asignaciones antes de migrar.';
 END IF;
 UPDATE cameras SET updated_at=updated_at, channel_number=id,
   rtsp_path=COALESCE(NULLIF(rtsp_path,''),CONCAT('/Streaming/Channels/',id,'02'))
 WHERE id IN (1,2) AND channel_number IS NULL;
END//
DELIMITER ;
CALL devioz_migrate_camera_channels();
DROP PROCEDURE devioz_migrate_camera_channels;
ALTER TABLE cameras
 ADD CONSTRAINT uq_cameras_parking_channel UNIQUE (parking_id, channel_number),
 ADD CONSTRAINT chk_cameras_channel CHECK (channel_number IS NULL OR channel_number BETWEEN 1 AND 4),
 ADD CONSTRAINT chk_cameras_image CHECK (brightness BETWEEN 0 AND 200 AND contrast BETWEEN 0 AND 200 AND saturation BETWEEN 0 AND 200),
 ADD CONSTRAINT chk_cameras_osd CHECK (osd_datetime IN (0,1) AND osd_camera_name IN (0,1));
-- Otros registros históricos sin canal se conservan; asignarlos desde Gestión.
