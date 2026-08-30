<?php

use System\Engine\Registry;
use System\Library\Database;

return new class {
    public function install(Registry $registry, Database $db): void
    {
        // 1. TEACHERS
        $db->query("
            CREATE TABLE IF NOT EXISTS `#__hifdh_teachers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NULL UNIQUE,
                `phone` VARCHAR(30) NULL,
                `bio` TEXT NULL,
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $db->query(
            "INSERT IGNORE INTO `#__hifdh_teachers`
             (`name`, `email`, `phone`, `bio`, `status`)
             VALUES (?, ?, ?, ?, ?)",
            ['Admin Teacher', 'admin@example.com', '1234567890', 'Administrator', 'active']
        );

        // 2. STUDENTS
        $db->query("
            CREATE TABLE IF NOT EXISTS `#__hifdh_students` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `teacher_id` INT NULL,
                `name` VARCHAR(100) NOT NULL,
                `age` INT NULL,
                `level` ENUM('beginner','intermediate','advanced','completed') DEFAULT 'beginner',
                `target_juz` INT DEFAULT 30,
                `start_date` DATE NULL,
                `notes` TEXT NULL,
                `status` ENUM('active','paused','graduated','suspended') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_teacher` (`teacher_id`),
                INDEX `idx_status` (`status`),
                FOREIGN KEY (`teacher_id`) REFERENCES `#__hifdh_teachers`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. JUZ TABLE
        // A Juz is its own entity. A Juz may contain portions of multiple Surahs.
        $db->query("
            CREATE TABLE IF NOT EXISTS `#__hifdh_juz` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `number` INT NOT NULL UNIQUE,
                `name` VARCHAR(50) NOT NULL,
                `name_arabic` VARCHAR(100) NULL,
                INDEX `idx_number` (`number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 4. SURAHS TABLE
        // NOTE: There is intentionally NO `juz` column here.
        // A Surah can span multiple Juz.
        $db->query("
            CREATE TABLE IF NOT EXISTS `#__hifdh_surahs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `number` INT NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `name_arabic` VARCHAR(100) NULL,
                `total_ayahs` INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 5. JUZ/SURAH PORTIONS
        // Stores exactly which Ayahs of a Surah belong to each Juz.
        $db->query("
            CREATE TABLE IF NOT EXISTS `#__hifdh_juz_surahs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `juz_id` INT NOT NULL,
                `surah_id` INT NOT NULL,
                `from_ayah` INT NOT NULL,
                `to_ayah` INT NOT NULL,
                UNIQUE KEY `uq_juz_surah_range` (`juz_id`, `surah_id`),
                INDEX `idx_juz` (`juz_id`),
                INDEX `idx_surah` (`surah_id`),
                FOREIGN KEY (`juz_id`) REFERENCES `#__hifdh_juz`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`surah_id`) REFERENCES `#__hifdh_surahs`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 6. PROGRESS RECORDS
        // `juz_id` is optional because progress can be recorded by Surah alone.
        $db->query("
            CREATE TABLE IF NOT EXISTS `#__hifdh_progress` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT NOT NULL,
                `surah_id` INT NOT NULL,
                `juz_id` INT NULL,
                `from_ayah` INT NOT NULL,
                `to_ayah` INT NOT NULL,
                `type` ENUM(
                    'new',
                    'revision',
                    'correction',
                    'qaida',
                    'tajweed',
                    'qiraah',
                    'listening'
                ) DEFAULT 'new' NOT NULL,
                `rating` ENUM('excellent','good','fair','weak','very_weak') NULL,
                `notes` TEXT NULL,
                `teacher_id` INT NOT NULL,
                `review_date` DATE NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_student` (`student_id`),
                INDEX `idx_surah` (`surah_id`),
                INDEX `idx_juz` (`juz_id`),
                INDEX `idx_teacher` (`teacher_id`),
                INDEX `idx_type` (`type`),
                INDEX `idx_review_date` (`review_date`),
                FOREIGN KEY (`student_id`) REFERENCES `#__hifdh_students`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`surah_id`) REFERENCES `#__hifdh_surahs`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`juz_id`) REFERENCES `#__hifdh_juz`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`teacher_id`) REFERENCES `#__hifdh_teachers`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // SEED DATA: 30 Juz
        $juz = [
            [1, "Juz 1", "الجزء الأول"],
            [2, "Juz 2", "الجزء الثاني"],
            [3, "Juz 3", "الجزء الثالث"],
            [4, "Juz 4", "الجزء الرابع"],
            [5, "Juz 5", "الجزء الخامس"],
            [6, "Juz 6", "الجزء السادس"],
            [7, "Juz 7", "الجزء السابع"],
            [8, "Juz 8", "الجزء الثامن"],
            [9, "Juz 9", "الجزء التاسع"],
            [10, "Juz 10", "الجزء العاشر"],
            [11, "Juz 11", "الجزء الحادي عشر"],
            [12, "Juz 12", "الجزء الثاني عشر"],
            [13, "Juz 13", "الجزء الثالث عشر"],
            [14, "Juz 14", "الجزء الرابع عشر"],
            [15, "Juz 15", "الجزء الخامس عشر"],
            [16, "Juz 16", "الجزء السادس عشر"],
            [17, "Juz 17", "الجزء السابع عشر"],
            [18, "Juz 18", "الجزء الثامن عشر"],
            [19, "Juz 19", "الجزء التاسع عشر"],
            [20, "Juz 20", "الجزء العشرون"],
            [21, "Juz 21", "الجزء الحادي والعشرون"],
            [22, "Juz 22", "الجزء الثاني والعشرون"],
            [23, "Juz 23", "الجزء الثالث والعشرون"],
            [24, "Juz 24", "الجزء الرابع والعشرون"],
            [25, "Juz 25", "الجزء الخامس والعشرون"],
            [26, "Juz 26", "الجزء السادس والعشرون"],
            [27, "Juz 27", "الجزء السابع والعشرون"],
            [28, "Juz 28", "الجزء الثامن والعشرون"],
            [29, "Juz 29", "الجزء التاسع والعشرون"],
            [30, "Juz 30", "الجزء الثلاثون"]
        ];

        $juzPlaceholders = implode(', ', array_fill(0, count($juz), '(?, ?, ?)'));
        $juzValues = [];

        foreach ($juz as $row) {
            foreach ($row as $value) {
                $juzValues[] = $value;
            }
        }

        $db->query(
            "INSERT IGNORE INTO `#__hifdh_juz`
             (`number`, `name`, `name_arabic`)
             VALUES " . $juzPlaceholders,
            $juzValues
        );

        // SEED DATA: All 114 Surahs
        $surahs = [
            [1, "Al-Fatihah", "الفاتحة", 7],
            [2, "Al-Baqarah", "البقرة", 286],
            [3, "Ali 'Imran", "آل عمران", 200],
            [4, "An-Nisa", "النساء", 176],
            [5, "Al-Ma'idah", "المائدة", 120],
            [6, "Al-An'am", "الأنعام", 165],
            [7, "Al-A'raf", "الأعراف", 206],
            [8, "Al-Anfal", "الأنفال", 75],
            [9, "At-Tawbah", "التوبة", 129],
            [10, "Yunus", "يونس", 109],
            [11, "Hud", "هود", 123],
            [12, "Yusuf", "يوسف", 111],
            [13, "Ar-Ra'd", "الرعد", 43],
            [14, "Ibrahim", "إبراهيم", 52],
            [15, "Al-Hijr", "الحجر", 99],
            [16, "An-Nahl", "النحل", 128],
            [17, "Al-Isra", "الإسراء", 111],
            [18, "Al-Kahf", "الكهف", 110],
            [19, "Maryam", "مريم", 98],
            [20, "Ta-Ha", "طه", 135],
            [21, "Al-Anbiya", "الأنبياء", 112],
            [22, "Al-Hajj", "الحج", 78],
            [23, "Al-Mu'minun", "المؤمنون", 118],
            [24, "An-Nur", "النور", 64],
            [25, "Al-Furqan", "الفرقان", 77],
            [26, "Ash-Shu'ara", "الشعراء", 227],
            [27, "An-Naml", "النمل", 93],
            [28, "Al-Qasas", "القصص", 88],
            [29, "Al-'Ankabut", "العنكبوت", 69],
            [30, "Ar-Rum", "الروم", 60],
            [31, "Luqman", "لقمان", 34],
            [32, "As-Sajdah", "السجدة", 30],
            [33, "Al-Ahzab", "الأحزاب", 73],
            [34, "Saba", "سبأ", 54],
            [35, "Fatir", "فاطر", 45],
            [36, "Ya-Sin", "يس", 83],
            [37, "As-Saffat", "الصافات", 182],
            [38, "Sad", "ص", 88],
            [39, "Az-Zumar", "الزمر", 75],
            [40, "Ghafir", "غافر", 85],
            [41, "Fussilat", "فصلت", 54],
            [42, "Ash-Shura", "الشورى", 53],
            [43, "Az-Zukhruf", "الزخرف", 89],
            [44, "Ad-Dukhan", "الدخان", 59],
            [45, "Al-Jathiyah", "الجاثية", 37],
            [46, "Al-Ahqaf", "الأحقاف", 35],
            [47, "Muhammad", "محمد", 38],
            [48, "Al-Fath", "الفتح", 29],
            [49, "Al-Hujurat", "الحجرات", 18],
            [50, "Qaf", "ق", 45],
            [51, "Adh-Dhariyat", "الذاريات", 60],
            [52, "At-Tur", "الطور", 49],
            [53, "An-Najm", "النجم", 62],
            [54, "Al-Qamar", "القمر", 55],
            [55, "Ar-Rahman", "الرحمن", 78],
            [56, "Al-Waqi'ah", "الواقعة", 96],
            [57, "Al-Hadid", "الحديد", 29],
            [58, "Al-Mujadilah", "المجادلة", 22],
            [59, "Al-Hashr", "الحشر", 24],
            [60, "Al-Mumtahanah", "الممتحنة", 13],
            [61, "As-Saff", "الصف", 14],
            [62, "Al-Jumu'ah", "الجمعة", 11],
            [63, "Al-Munafiqun", "المنافقون", 11],
            [64, "At-Taghabun", "التغابن", 18],
            [65, "At-Talaq", "الطلاق", 12],
            [66, "At-Tahrim", "التحريم", 12],
            [67, "Al-Mulk", "الملك", 30],
            [68, "Al-Qalam", "القلم", 52],
            [69, "Al-Haqqah", "الحاقة", 52],
            [70, "Al-Ma'arij", "المعارج", 44],
            [71, "Nuh", "نوح", 28],
            [72, "Al-Jinn", "الجن", 28],
            [73, "Al-Muzzammil", "المزمل", 20],
            [74, "Al-Muddaththir", "المدثر", 56],
            [75, "Al-Qiyamah", "القيامة", 40],
            [76, "Al-Insan", "الإنسان", 31],
            [77, "Al-Mursalat", "المرسلات", 50],
            [78, "An-Naba", "النبأ", 40],
            [79, "An-Nazi'at", "النازعات", 46],
            [80, "'Abasa", "عبس", 42],
            [81, "At-Takwir", "التكوير", 29],
            [82, "Al-Infitar", "الانفطار", 19],
            [83, "Al-Mutaffifin", "المطففين", 36],
            [84, "Al-Inshiqaq", "الانشقاق", 25],
            [85, "Al-Buruj", "البروج", 22],
            [86, "At-Tariq", "الطارق", 17],
            [87, "Al-A'la", "الأعلى", 19],
            [88, "Al-Ghashiyah", "الغاشية", 26],
            [89, "Al-Fajr", "الفجر", 30],
            [90, "Al-Balad", "البلد", 20],
            [91, "Ash-Shams", "الشمس", 15],
            [92, "Al-Layl", "الليل", 21],
            [93, "Ad-Duha", "الضحى", 11],
            [94, "Ash-Sharh", "الشرح", 8],
            [95, "At-Tin", "التين", 8],
            [96, "Al-'Alaq", "العلق", 19],
            [97, "Al-Qadr", "القدر", 5],
            [98, "Al-Bayyinah", "البينة", 8],
            [99, "Az-Zalzalah", "الزلزلة", 8],
            [100, "Al-'Adiyat", "العاديات", 11],
            [101, "Al-Qari'ah", "القارعة", 11],
            [102, "At-Takathur", "التكاثر", 8],
            [103, "Al-'Asr", "العصر", 3],
            [104, "Al-Humazah", "الهمزة", 9],
            [105, "Al-Fil", "الفيل", 5],
            [106, "Quraysh", "قريش", 4],
            [107, "Al-Ma'un", "الماعون", 7],
            [108, "Al-Kawthar", "الكوثر", 3],
            [109, "Al-Kafirun", "الكافرون", 6],
            [110, "An-Nasr", "النصر", 3],
            [111, "Al-Masad", "المسد", 5],
            [112, "Al-Ikhlas", "الإخلاص", 4],
            [113, "Al-Falaq", "الفلق", 5],
            [114, "An-Nas", "الناس", 6]
        ];

        $surahPlaceholders = implode(', ', array_fill(0, count($surahs), '(?, ?, ?, ?)'));
        $surahValues = [];

        foreach ($surahs as $row) {
            foreach ($row as $value) {
                $surahValues[] = $value;
            }
        }

        $db->query(
            "INSERT IGNORE INTO `#__hifdh_surahs`
             (`number`, `name`, `name_arabic`, `total_ayahs`)
             VALUES " . $surahPlaceholders,
            $surahValues
        );

        // SEED DATA: Exact Surah portions inside each Juz.
        // [juz_number, surah_number, from_ayah, to_ayah]
        $juzSurahs = [
            [1, 1, 1, 7],
            [1, 2, 1, 141],
            [2, 2, 142, 252],
            [3, 2, 253, 286],
            [3, 3, 1, 92],
            [4, 3, 93, 200],
            [4, 4, 1, 23],
            [5, 4, 24, 147],
            [6, 4, 148, 176],
            [6, 5, 1, 81],
            [7, 5, 82, 120],
            [7, 6, 1, 110],
            [8, 6, 111, 165],
            [8, 7, 1, 87],
            [9, 7, 88, 206],
            [9, 8, 1, 40],
            [10, 8, 41, 75],
            [10, 9, 1, 92],
            [11, 9, 93, 129],
            [11, 10, 1, 109],
            [11, 11, 1, 5],
            [12, 11, 6, 123],
            [12, 12, 1, 52],
            [13, 12, 53, 111],
            [13, 13, 1, 43],
            [13, 14, 1, 52],
            [14, 15, 1, 99],
            [14, 16, 1, 128],
            [15, 17, 1, 111],
            [15, 18, 1, 74],
            [16, 18, 75, 110],
            [16, 19, 1, 98],
            [16, 20, 1, 135],
            [17, 21, 1, 112],
            [17, 22, 1, 78],
            [18, 23, 1, 118],
            [18, 24, 1, 64],
            [18, 25, 1, 20],
            [19, 25, 21, 77],
            [19, 26, 1, 227],
            [19, 27, 1, 55],
            [20, 27, 56, 93],
            [20, 28, 1, 88],
            [20, 29, 1, 45],
            [21, 29, 46, 69],
            [21, 30, 1, 60],
            [21, 31, 1, 34],
            [21, 32, 1, 30],
            [21, 33, 1, 30],
            [22, 33, 31, 73],
            [22, 34, 1, 54],
            [22, 35, 1, 45],
            [22, 36, 1, 27],
            [23, 36, 28, 83],
            [23, 37, 1, 182],
            [23, 38, 1, 88],
            [23, 39, 1, 31],
            [24, 39, 32, 75],
            [24, 40, 1, 85],
            [24, 41, 1, 46],
            [25, 41, 47, 54],
            [25, 42, 1, 53],
            [25, 43, 1, 89],
            [25, 44, 1, 59],
            [25, 45, 1, 37],
            [26, 46, 1, 35],
            [26, 47, 1, 38],
            [26, 48, 1, 29],
            [26, 49, 1, 18],
            [26, 50, 1, 45],
            [26, 51, 1, 30],
            [27, 51, 31, 60],
            [27, 52, 1, 49],
            [27, 53, 1, 62],
            [27, 54, 1, 55],
            [27, 55, 1, 78],
            [27, 56, 1, 96],
            [27, 57, 1, 29],
            [28, 58, 1, 22],
            [28, 59, 1, 24],
            [28, 60, 1, 13],
            [28, 61, 1, 14],
            [28, 62, 1, 11],
            [28, 63, 1, 11],
            [28, 64, 1, 18],
            [28, 65, 1, 12],
            [28, 66, 1, 12],
            [29, 67, 1, 30],
            [29, 68, 1, 52],
            [29, 69, 1, 52],
            [29, 70, 1, 44],
            [29, 71, 1, 28],
            [29, 72, 1, 28],
            [29, 73, 1, 20],
            [29, 74, 1, 56],
            [29, 75, 1, 40],
            [29, 76, 1, 31],
            [29, 77, 1, 50],
            [30, 78, 1, 40],
            [30, 79, 1, 46],
            [30, 80, 1, 42],
            [30, 81, 1, 29],
            [30, 82, 1, 19],
            [30, 83, 1, 36],
            [30, 84, 1, 25],
            [30, 85, 1, 22],
            [30, 86, 1, 17],
            [30, 87, 1, 19],
            [30, 88, 1, 26],
            [30, 89, 1, 30],
            [30, 90, 1, 20],
            [30, 91, 1, 15],
            [30, 92, 1, 21],
            [30, 93, 1, 11],
            [30, 94, 1, 8],
            [30, 95, 1, 8],
            [30, 96, 1, 19],
            [30, 97, 1, 5],
            [30, 98, 1, 8],
            [30, 99, 1, 8],
            [30, 100, 1, 11],
            [30, 101, 1, 11],
            [30, 102, 1, 8],
            [30, 103, 1, 3],
            [30, 104, 1, 9],
            [30, 105, 1, 5],
            [30, 106, 1, 4],
            [30, 107, 1, 7],
            [30, 108, 1, 3],
            [30, 109, 1, 6],
            [30, 110, 1, 3],
            [30, 111, 1, 5],
            [30, 112, 1, 4],
            [30, 113, 1, 5],
            [30, 114, 1, 6]
        ];

        // Resolve the seeded numbers to database IDs.
        $juzMap = [];
        $rows = $db->query("SELECT `id`, `number` FROM `#__hifdh_juz`")->rows;

        foreach ($rows as $row) {
            $juzMap[(int) $row['number']] = (int) $row['id'];
        }

        $surahMap = [];
        $rows = $db->query("SELECT `id`, `number` FROM `#__hifdh_surahs`")->rows;

        foreach ($rows as $row) {
            $surahMap[(int) $row['number']] = (int) $row['id'];
        }

        $portionPlaceholders = [];
        $portionValues = [];

        foreach ($juzSurahs as [$juzNumber, $surahNumber, $fromAyah, $toAyah]) {
            if (!isset($juzMap[$juzNumber], $surahMap[$surahNumber])) {
                continue;
            }

            $portionPlaceholders[] = '(?, ?, ?, ?)';
            $portionValues[] = $juzMap[$juzNumber];
            $portionValues[] = $surahMap[$surahNumber];
            $portionValues[] = $fromAyah;
            $portionValues[] = $toAyah;
        }

        if ($portionPlaceholders) {
            $db->query(
                "INSERT IGNORE INTO `#__hifdh_juz_surahs`
                 (`juz_id`, `surah_id`, `from_ayah`, `to_ayah`)
                 VALUES " . implode(', ', $portionPlaceholders),
                $portionValues
            );
        }
    }

    public function uninstall(Registry $registry, Database $db): void
    {
        // Drop child/dependent tables first.
        $db->query("DROP TABLE IF EXISTS `#__hifdh_progress`;");
        $db->query("DROP TABLE IF EXISTS `#__hifdh_juz_surahs`;");
        $db->query("DROP TABLE IF EXISTS `#__hifdh_surahs`;");
        $db->query("DROP TABLE IF EXISTS `#__hifdh_juz`;");
        $db->query("DROP TABLE IF EXISTS `#__hifdh_students`;");
        $db->query("DROP TABLE IF EXISTS `#__hifdh_teachers`;");
    }
};