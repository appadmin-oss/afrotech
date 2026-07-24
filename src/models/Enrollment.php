<?php

class Enrollment {
    public static function enroll(int $studentId, int $courseId): bool {
        if (!Database::available()) return false;
        Database::exec(
            "INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)",
            [$studentId, $courseId]
        );
        return true;
    }

    /** Courses a student is enrolled in, joined with the catalog row. */
    public static function forStudent(int $studentId): array {
        if (!Database::available()) return [];
        return Database::all(
            "SELECT e.*, c.title, c.slug, c.track_slug, c.weeks, c.level, c.summary
               FROM enrollments e
               JOIN courses c ON c.id = e.course_id
              WHERE e.student_id = ?
              ORDER BY e.enrolled_at DESC",
            [$studentId]
        );
    }

    public static function isEnrolled(int $studentId, int $courseId): bool {
        if (!Database::available()) return false;
        return (bool) Database::scalar(
            "SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1",
            [$studentId, $courseId]
        );
    }

    public static function setProgress(int $enrollId, int $progress): void {
        if (!Database::available()) return;
        $progress = max(0, min(100, $progress));
        $status = $progress >= 100 ? 'completed' : 'active';
        Database::exec("UPDATE enrollments SET progress = ?, status = ? WHERE id = ?", [$progress, $status, $enrollId]);
    }
}
