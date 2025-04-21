<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../auth/login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grade'])) {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $assignment_name = $_POST['assignment_name'];
    $grade = $_POST['grade'];
    $max_grade = $_POST['max_grade'];

    // Verify that the teacher is assigned to this course
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $teacher_id]);
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("INSERT INTO grades (student_id, course_id, assignment_name, grade, max_grade) VALUES (?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$student_id, $course_id, $assignment_name, $grade, $max_grade]);
            $success_message = 'Grade submitted successfully';
        } catch(PDOException $e) {
            $error_message = 'Failed to submit grade';
        }
    } else {
        $error_message = 'You are not authorized to grade this course';
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

// Fetch existing grades
$grades = [];
if (!empty($courses)) {
    $sql = "SELECT g.*, u.full_name as student_name, c.course_name 
            FROM grades g 
            JOIN users u ON g.student_id = u.id 
            JOIN courses c ON g.course_id = c.id 
            WHERE c.teacher_id = ? 
            ORDER BY g.submission_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$teacher_id]);
    $grades = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades</title>
    <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Manage Grades</h1>
        
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
                <label for="assignment_name">Assignment Name:</label>
                <input type="text" name="assignment_name" id="assignment_name" required>
            </div>

            <div class="form-group">
                <label for="grade">Grade:</label>
                <input type="number" name="grade" id="grade" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="max_grade">Maximum Grade:</label>
                <input type="number" name="max_grade" id="max_grade" step="0.01" required>
            </div>

            <button type="submit" name="submit_grade" class="btn btn-primary">Submit Grade</button>
        </form>

        <h2>Recent Grades</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Assignment</th>
                    <th>Grade</th>
                    <th>Max Grade</th>
                    <th>Submission Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $grade): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($grade['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($grade['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($grade['assignment_name']); ?></td>
                        <td><?php echo $grade['grade']; ?></td>
                        <td><?php echo $grade['max_grade']; ?></td>
                        <td><?php echo $grade['submission_date']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="links">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="attendance.php">Mark Attendance</a>
            <a href="student-details.php">View Student Details</a>
        </div>
    </div>
</body>
</html>