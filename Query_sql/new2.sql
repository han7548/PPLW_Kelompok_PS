ALTER TABLE ruang ADD COLUMN IF NOT EXISTS foto VARCHAR(255);
ALTER TABLE unit ADD COLUMN IF NOT EXISTS foto VARCHAR(255);


----- BARU LAGI TOLONG DI RUN YA -----

ALTER TABLE request_booking DROP CONSTRAINT fk_ruang_id;

ALTER TABLE request_booking ADD CONSTRAINT fk_ruang_id 
FOREIGN KEY (ruang_id) REFERENCES ruang(id) ON DELETE CASCADE;

ALTER TABLE kategori_ruang ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE kategori_unit ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;

-- Hapus aturan lama yang membatasi status
ALTER TABLE request_booking DROP CONSTRAINT IF EXISTS request_booking_approval_status_check;

-- Buat aturan baru yang memasukkan status log book
ALTER TABLE request_booking ADD CONSTRAINT request_booking_approval_status_check 
CHECK (approval_status IN ('pending', 'approved', 'rejected', 'terkonfirmasi datang'));


ALTER TABLE request_booking ALTER COLUMN approval_status TYPE VARCHAR(50);