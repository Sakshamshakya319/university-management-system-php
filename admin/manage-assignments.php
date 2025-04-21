<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle course assignment and bulk enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_course'])) {
        $course_id = $_POST['course_id'];
        $teacher_id = $_POST['teacher_id'];
        $student_ids = $_POST['student_ids'] ?? [];
        $department_id = $_POST['department_id'] ?? null;

        try {
            $pdo->beginTransaction();

            // Verify course exists
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            if ($stmt->rowCount() === 0) {
                throw new PDOException('Course not found');
            }

            // Verify teacher exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher'");
            $stmt->execute([$teacher_id]);
            if ($stmt->rowCount() === 0) {
                throw new PDOException('Teacher not found or invalid');
            }

            // Update course teacher
            $stmt = $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?");
            $stmt->execute([$teacher_id, $course_id]);
            
            // Store existing enrollments for comparison
            $stmt = $pdo->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $existing_enrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Remove existing enrollments for this course
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?");
            $stmt->execute([$course_id]);

            $new_enrollments = [];

            // Enroll selected students
            if (!empty($student_ids)) {
                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, NOW())");
                foreach ($student_ids as $student_id) {
                    // Verify student exists
                    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
                    $check_stmt->execute([$student_id]);
                    if ($check_stmt->rowCount() > 0) {
                        $stmt->execute([$student_id, $course_id]);
                        $new_enrollments[] = $student_id;
                    }
                }
            }

            // Bulk enroll students from selected department
            if ($department_id) {
                // Verify department exists
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
                $stmt->execute([$department_id]);
                if ($stmt->rowCount() === 0) {
                    throw new PDOException('Department not found');
                }

                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date)
                                     SELECT id, ?, NOW()
                                     FROM users
                                     WHERE role = 'student' AND department_id = ?
                                     AND id NOT IN (SELECT student_id FROM enrollments WHERE course_id = ?)");
                $stmt->execute([$course_id, $department_id, $course_id]);

                // Get newly enrolled students from department
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'student' AND department_id = ?");
                $stmt->execute([$department_id]);
                $dept_students = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $new_enrollments = array_merge($new_enrollments, $dept_students);
            }

            $pdo->commit();
            $added_count = count(array_diff($new_enrollments, $existing_enrollments));
            $removed_count = count(array_diff($existing_enrollments, $new_enrollments));
            $success_message = sprintf(
                'Course assignments updated successfully. %d student(s) added, %d student(s) removed.',
                $added_count,
                $removed_count
            );
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error_message = 'Failed to update course assignments: ' . $e->getMessage();
        }
    }
}

// Fetch all courses with enrollment counts
$stmt = $pdo->prepare("SELECT c.*, u.full_name as teacher_name, 
                       (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as enrolled_count
                       FROM courses c 
                       LEFT JOIN users u ON c.teacher_id = u.id");
$stmt->execute();
$courses = $stmt->fetchAll();

// Fetch all teachers
$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'teacher'");
$stmt->execute();
$teachers = $stmt->fetchAll();

// Fetch all departments
$stmt = $pdo->prepare("SELECT * FROM departments ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll();

// Fetch all students with their departments and enrollment counts
$stmt = $pdo->prepare("SELECT u.id, u.full_name, d.id as dept_id, d.department_name,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id) as course_count
                       FROM users u 
                       LEFT JOIN departments d ON u.department_id = d.id 
                       WHERE u.role = 'student'
                       ORDER BY d.department_name, u.full_name");
$stmt->execute();
$students = $stmt->fetchAll();

// Fetch current enrollments
$enrollments = [];
$stmt = $pdo->prepare("SELECT course_id, student_id FROM enrollments");
$stmt->execute();
while ($row = $stmt->fetch()) {
    $enrollments[$row['course_id']][] = $row['student_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Course Assignments</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Manage Course Assignments</h1>

        <?php if (isset($success_message)): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="course-assignments">
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if (isset($success_message)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php foreach ($courses as $course): ?>
                <div class="course-card">
                    <h2><?php echo htmlspecialchars($course['course_name']); ?></h2>
                    <p><strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
                    <p><strong>Current Teacher:</strong> <?php echo htmlspecialchars($course['teacher_name'] ?? 'Not Assigned'); ?></p>
                    <p><strong>Enrolled Students:</strong> <?php echo $course['enrolled_count']; ?></p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                        
                        <div class="form-group">
                            <label for="teacher_<?php echo $course['id']; ?>">Assign Teacher:</label>
                            <select name="teacher_id" id="teacher_<?php echo $course['id']; ?>" required>
                                <option value="">Select Teacher...</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($course['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Bulk Enroll by Department:</label>
                            <select name="department_id" class="form-control mb-3" onchange="toggleStudentList(this, '<?php echo $course['id']; ?>')">
                                <option value="">Select Department...</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?> 
                                        (<?php echo count(array_filter($students, function($s) use ($dept) { return $s['dept_id'] == $dept['id']; })); ?> students)
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label>Or Select Individual Students:</label>
                            <div class="student-list" style="max-height: 300px; overflow-y: auto;">
                                <?php 
                                $current_dept = null;
                                foreach ($students as $student): 
                                    if ($current_dept !== $student['department_name']): 
                                        if ($current_dept !== null) echo '</div>';
                                        $current_dept = $student['department_name'];
                                ?>
                                        <h6 class="mt-3"><?php echo htmlspecialchars($student['department_name'] ?? 'No Department'); ?></h6>
                                        <div class="pl-3">
                                <?php endif; ?>
                                    <label class="checkbox-label d-block">
                                        <input type="checkbox" 
                                               name="student_ids[]" 
                                               value="<?php echo $student['id']; ?>"
                                               <?php echo (isset($enrollments[$course['id']]) && in_array($student['id'], $enrollments[$course['id']])) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo $student['course_count']; ?> courses)
                                    </label>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="assign_course" class="btn btn-primary">Update Assignments</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="links">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="courses.php">Manage Courses</a>
        </div>
    </div>

    <script>
    function toggleStudentList(select, courseId) {
        const studentList = document.querySelector(`#student-list-${courseId}`);
        const checkboxes = studentList.querySelectorAll('input[type="checkbox"]');
        const selectedDeptId = select.value;

        checkboxes.forEach(checkbox => {
            const studentItem = checkbox.closest('.student-item');
            const deptId = studentItem.getAttribute('data-dept-id');
            
            if (!selectedDeptId || deptId === selectedDeptId) {
                studentItem.style.display = '';
                checkbox.checked = selectedDeptId ? true : false;
            } else {
                studentItem.style.display = 'none';
                checkbox.checked = false;
            }
        });
    }
    </script>
</body>
</html>