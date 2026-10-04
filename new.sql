---- tambahin ini yh langsung run

CREATE TABLE IF NOT EXISTS public.users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(255),
    role VARCHAR(50) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

GRANT ALL ON TABLE public.users TO framework_user;
GRANT ALL ON SEQUENCE public.users_id_seq TO framework_user;

-- akun dummy: username admin, password admin123
INSERT INTO public.users (username, password, nama_lengkap, role)
VALUES ('admin', '$2y$10$ljcs3dYPQT2haB/lQuS.YeQ2WU0mN7.WY6NU8ydRCk4Oe5pw.Nq/a', 'Admin Utama', 'admin');


ALTER TABLE kategori_unit ADD COLUMN created_by BIGINT,
ADD CONSTRAINT fk_created_by FOREIGN KEY (created_by) REFERENCES users(id);

ALTER TABLE kategori_unit
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS created_by BIGINT,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_by BIGINT;

ALTER TABLE kategori_ruang
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS created_by BIGINT,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_by BIGINT;

ALTER TABLE unit
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS created_by BIGINT,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_by BIGINT;

ALTER TABLE ruang
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS created_by BIGINT,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_by BIGINT;

ALTER TABLE kategori_ruang_unit
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS created_by BIGINT,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_by BIGINT;

ALTER TABLE request_booking
    ADD COLUMN IF NOT EXISTS created_by BIGINT,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_by BIGINT;

ALTER TABLE kategori_unit
    ADD CONSTRAINT fk_kategori_unit_created_by
    FOREIGN KEY (created_by) REFERENCES users(id);

ALTER TABLE kategori_unit
    ADD CONSTRAINT fk_kategori_unit_updated_by
    FOREIGN KEY (updated_by) REFERENCES users(id);


ALTER TABLE kategori_ruang
    ADD CONSTRAINT fk_kategori_ruang_created_by
    FOREIGN KEY (created_by) REFERENCES users(id);

ALTER TABLE kategori_ruang
    ADD CONSTRAINT fk_kategori_ruang_updated_by
    FOREIGN KEY (updated_by) REFERENCES users(id);


ALTER TABLE unit
    ADD CONSTRAINT fk_unit_created_by
    FOREIGN KEY (created_by) REFERENCES users(id);

ALTER TABLE unit
    ADD CONSTRAINT fk_unit_updated_by
    FOREIGN KEY (updated_by) REFERENCES users(id);


ALTER TABLE ruang
    ADD CONSTRAINT fk_ruang_created_by
    FOREIGN KEY (created_by) REFERENCES users(id);

ALTER TABLE ruang
    ADD CONSTRAINT fk_ruang_updated_by
    FOREIGN KEY (updated_by) REFERENCES users(id);


ALTER TABLE kategori_ruang_unit
    ADD CONSTRAINT fk_kategori_ruang_unit_created_by
    FOREIGN KEY (created_by) REFERENCES users(id);

ALTER TABLE kategori_ruang_unit
    ADD CONSTRAINT fk_kategori_ruang_unit_updated_by
    FOREIGN KEY (updated_by) REFERENCES users(id);


ALTER TABLE request_booking
    ADD CONSTRAINT fk_request_booking_created_by
    FOREIGN KEY (created_by) REFERENCES users(id);

ALTER TABLE request_booking
    ADD CONSTRAINT fk_request_booking_updated_by
    FOREIGN KEY (updated_by) REFERENCES users(id);

----- trigger for updated at

CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-----

CREATE TRIGGER trg_kategori_unit_updated_at
BEFORE UPDATE ON kategori_unit
FOR EACH ROW
EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_kategori_ruang_updated_at
BEFORE UPDATE ON kategori_ruang
FOR EACH ROW
EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_unit_updated_at
BEFORE UPDATE ON unit
FOR EACH ROW
EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_ruang_updated_at
BEFORE UPDATE ON ruang
FOR EACH ROW
EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_kategori_ruang_unit_updated_at
BEFORE UPDATE ON kategori_ruang_unit
FOR EACH ROW
EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_request_booking_updated_at
BEFORE UPDATE ON request_booking
FOR EACH ROW
EXECUTE FUNCTION update_updated_at();