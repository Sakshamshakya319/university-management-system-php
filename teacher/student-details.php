<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../auth/login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Fetch teacher's courses
$stmt = $pdo->prepare("SELECT id, course_name FROM courses WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll();

// Get student details if student_id is provided
$student_details = null;
$attendance_records = [];
$grade_records = [];
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    
    // Fetch student basic information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$student_id]);
    $student_details = $stmt->fetch();

    if ($student_details) {
        // Fetch attendance records
        $sql = "SELECT a.*, c.course_name 
                FROM attendance a 
                JOIN courses c ON a.course_id = c.id 
                WHERE a.student_id = ? AND c.teacher_id = ? 
                ORDER BY a.date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $teacher_id]);
        $attendance_records = $stmt->fetchAll();

        // Fetch grade records
        $sql = "SELECT g.*, c.course_name 
                FROM grades g 
                JOIN courses c ON g.course_id = c.id 
                WHERE g.student_id = ? AND c.teacher_id = ? 
                ORDER BY g.submission_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $teacher_id]);
        $grade_records = $stmt->fetchAll();
    }
}

// Fetch all students enrolled in teacher's courses
$enrolled_students = [];
if (!empty($courses)) {
    $course_ids = array_column($courses, 'id');
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    $sql = "SELECT DISTINCT u.id, u.full_name 
            FROM users u 
            JOIN enrollments e ON u.id = e.student_id 
            WHERE e.course_id IN ($placeholders) AND u.role = 'student'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($course_ids);
    $enrolled_students = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details</title>
    <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Student Details</h1>

        <form method="GET" action="" class="search-form">
            <div class="form-group">
                <label for="student_id">Select Student:</label>
                <select name="student_id" id="student_id" onchange="this.form.submit()">
                    <option value="">Choose a student...</option>
                    <?php foreach ($enrolled_students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" <?php echo (isset($_GET['student_id']) && $_GET['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($student_details): ?>
            <div class="student-info">
                <h2>Student Information</h2>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($student_details['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($student_details['email']); ?></p>
            </div>

            <div class="attendance-section">
                <h2>Attendance Records</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo $record['date']; ?></td>
                                <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                                <td><?php echo ucfirst($record['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="grades-section">
                <h2>Grade Records</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Assignment</th>
                            <th>Grade</th>
                            <th>Max Grade</th>
                            <th>Submission Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grade_records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['assignment_name']); ?></td>
                                <td><?php echo $record['grade']; ?></td>
                                <td><?php echo $record['max_grade']; ?></td>
                                <td><?php echo $record['submission_date']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="links">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="attendance.php">Mark Attendance</a>
            <a href="grades.php">Manage Grades</a>
        </div>
    </div>
</body>
</html>