<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../auth/login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $date = $_POST['date'];
    $status = $_POST['status'];

    // Verify that the teacher is assigned to this course
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $teacher_id]);
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("INSERT INTO attendance (student_id, course_id, date, status) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$student_id, $course_id, $date, $status]);
            $success_message = 'Attendance marked successfully';
        } catch(PDOException $e) {
            $error_message = 'Failed to mark attendance';
        }
    } else {
        $error_message = 'You are not authorized to mark attendance for this course';
    }
}

// Fetch teacher's courses
$stmt = $pdo->prepare("SELECT id, course_name FROM courses WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll();

// Fetch students enrolled in teacher's courses
$enrolled_students = [];
if (!empty($courses)) {
    $course_ids = array_column($courses, 'id');
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    $sql = "SELECT DISTINCT u.id, u.full_name, e.course_id 
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
    <title>Mark Attendance</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    
</head>
<body>
    <div class="container">
        <h1>Mark Attendance</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="course_id">Select Course:</label>
                <select name="course_id" id="course_id" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="student_id">Select Student:</label>
                <select name="student_id" id="student_id" required>
                    <?php foreach ($enrolled_students as $student): ?>
                        <option value="<?php echo $student['id']; ?>">
                            <?php echo htmlspecialchars($student['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="date">Date:</label>
                <input type="date" name="date" id="date" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" required>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                </select>
            </div>

            <button type="submit" name="mark_attendance" class="btn btn-primary">Mark Attendance</button>
        </form>

        <div class="links">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="grades.php">Manage Grades</a>
            <a href="student-details.php">View Student Details</a>
        </div>
    </div>
</body>
</html>