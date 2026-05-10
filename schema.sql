-- АРХИВИ КОРҲОИ ИЛМӢ — ДИС ДДТТ (PostgreSQL — Neon)

DROP TABLE IF EXISTS comments CASCADE;
DROP TABLE IF EXISTS download_logs CASCADE;
DROP TABLE IF EXISTS activity_log CASCADE;
DROP TABLE IF EXISTS favorites CASCADE;
DROP TABLE IF EXISTS ratings CASCADE;
DROP TABLE IF EXISTS works CASCADE;
DROP TABLE IF EXISTS students CASCADE;
DROP TABLE IF EXISTS teachers CASCADE;
DROP TABLE IF EXISTS specialties CASCADE;
DROP TABLE IF EXISTS faculties CASCADE;
DROP TABLE IF EXISTS users CASCADE;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'student' CHECK (role IN ('admin','teacher','student')),
    phone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE faculties (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(50)
);

CREATE TABLE specialties (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    faculty_id INT REFERENCES faculties(id) ON DELETE SET NULL
);

CREATE TABLE students (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    student_code VARCHAR(50),
    group_name VARCHAR(100),
    faculty_id INT REFERENCES faculties(id) ON DELETE SET NULL,
    specialty_id INT REFERENCES specialties(id) ON DELETE SET NULL,
    course INT,
    enrolled_year INT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE teachers (
    id SERIAL PRIMARY KEY,
    user_id INT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    academic_title VARCHAR(100),
    academic_degree VARCHAR(100),
    department VARCHAR(255),
    faculty_id INT REFERENCES faculties(id) ON DELETE SET NULL,
    bio TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE works (
    id SERIAL PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    student_id INT REFERENCES students(id) ON DELETE SET NULL,
    teacher_id INT REFERENCES teachers(id) ON DELETE SET NULL,
    type VARCHAR(50) CHECK (type IN ('курсовая','дипломная','магистр','мақола','реферат')),
    subject VARCHAR(255),
    year INT NOT NULL,
    keywords VARCHAR(500),
    description TEXT,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    file_size BIGINT,
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft','pending','approved','rejected')),
    grade VARCHAR(20),
    rejection_reason TEXT,
    views INT DEFAULT 0,
    downloads INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    approved_at TIMESTAMP,
    approved_by INT REFERENCES users(id)
);

CREATE TABLE comments (
    id SERIAL PRIMARY KEY,
    work_id INT REFERENCES works(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE favorites (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    work_id INT REFERENCES works(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, work_id)
);

CREATE TABLE ratings (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    work_id INT REFERENCES works(id) ON DELETE CASCADE,
    stars INT CHECK (stars BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, work_id)
);

CREATE TABLE download_logs (
    id SERIAL PRIMARY KEY,
    work_id INT REFERENCES works(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    ip_address VARCHAR(50),
    downloaded_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE activity_log (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

INSERT INTO faculties (name, short_name) VALUES
('Технологияи иттилоотӣ', 'ТИ'),
('Иқтисодиёт ва идоракунӣ', 'ИИ'),
('Молия ва бонкдорӣ', 'МБ'),
('Ҳисоб ва аудит', 'ҲА');

INSERT INTO specialties (name, code, faculty_id) VALUES
('Барномасозии компютерӣ', 'ИТ-101', 1),
('Системаҳои иттилоотӣ', 'ИТ-102', 1),
('Иқтисоди корхона', 'ИИ-201', 2),
('Молия', 'МБ-301', 3),
('Бухгалтерия', 'ҲА-401', 4);

INSERT INTO users (full_name, email, password_hash, role) VALUES
('Администратори система', 'admin@dis.tj', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Раҳимов Баҳодур Исмоилович', 'rahimov@dis.tj', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Алиев Муҳаммад Саидович', 'aliev@dis.tj', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

INSERT INTO teachers (user_id, academic_title, academic_degree, department, faculty_id) VALUES
((SELECT id FROM users WHERE email='rahimov@dis.tj'), 'Дотсент', 'Номзади илм', 'Информатика', 1);

INSERT INTO students (user_id, student_code, group_name, faculty_id, specialty_id, course, enrolled_year) VALUES
((SELECT id FROM users WHERE email='aliev@dis.tj'), 'СТ-2021-001', 'ТИ-401', 1, 1, 4, 2021);
