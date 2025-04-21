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
    $status = $_POST['status'];
    $date = $_POST['date'];

    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, course_id, date, status) VALUES (?, ?, ?, ?)");
    try {
        $stmt->execute([$student_id, $course_id, $date, $status]);
        $success_message = 'Attendance marked successfully';
    } catch(PDOException $e) {
        $error_message = 'Failed to mark attendance';
    }
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grade'])) {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $assignment_name = $_POST['assignment_name'];
    $grade = $_POST['grade'];
    $max_grade = $_POST['max_grade'];

    $stmt = $pdo->prepare("INSERT INTO grades (student_id, course_id, assignment_name, grade, max_grade) VALUES (?, ?, ?, ?, ?)");
    try {
        $stmt->execute([$student_id, $course_id, $assignment_name, $grade, $max_grade]);
        $success_message = 'Grade submitted successfully';
    } catch(PDOException $e) {
        $error_message = 'Failed to submit grade';
    }
}

// Fetch teacher's courses
$stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll();

// Fetch enrolled students for each course
$course_students = [];
foreach ($courses as $course) {
    $stmt = $pdo->prepare("
        SELECT u.*, e.enrollment_date 
        FROM users u 
        INNER JOIN enrollments e ON u.id = e.student_id 
        WHERE e.course_id = ? 
        AND u.role = 'student'");
    $stmt->execute([$course['id']]);
    $course_students[$course['id']] = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - University Management System</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables@1.10.18/media/css/jquery.dataTables.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 10px 20px;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
        }
        .main-content {
            padding: 20px;
        }
        .course-card {
            transition: transform 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Teacher Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#courses">
                                <i class="fas fa-book me-2"></i>My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#attendance">
                                <i class="fas fa-calendar-check me-2"></i>Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#grades">
                                <i class="fas fa-graduation-cap me-2"></i>Grades
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exams.php">
                                <i class="fas fa-file-alt me-2"></i>Exams & Announcements
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4 main-content">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success mt-3"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger mt-3"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Courses Section -->
                <section id="courses" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">My Courses</h2>
                    <div class="row">
                        <?php foreach ($courses as $course): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card course-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                                        <p class="card-text"><?php echo htmlspecialchars($course['description']); ?></p>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Enrolled Students: <?php echo count($course_students[$course['id']]); ?>
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Attendance Section -->
                <section id="attendance" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">Mark Attendance</h2>
                    <?php foreach ($courses as $course): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-4">
                                    <div class="row align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label">Student</label>
                                            <select name="student_id" class="form-select" required>
                                                <?php foreach ($course_students[$course['id']] as $student): ?>
                                                    <option value="<?php echo $student['id']; ?>">
                                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Date</label>
                                            <input type="date" name="date" class="form-control" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select" required>
                                                <option value="present">Present</option>
                                                <option value="absent">Absent</option>
                                                <option value="late">Late</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" name="mark_attendance" class="btn btn-primary">Mark Attendance</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <!-- Grades Section -->
                <section id="grades" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">Submit Grades</h2>
                    <?php foreach ($courses as $course): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-4">
                                    <div class="row align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label">Student</label>
                                            <select name="student_id" class="form-select" required>
                                                <?php foreach ($course_students[$course['id']] as $student): ?>
                                                    <option value="<?php echo $student['id']; ?>">
                                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Assignment Name</label>
                                            <input type="text" name="assignment_name" class="form-control" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Grade</label>
                                            <input type="number" name="grade" class="form-control" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Max Grade</label>
                                            <input type="number" name="max_grade" class="form-control" required>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" name="submit_grade" class="btn btn-primary">Submit Grade</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables@1.10.18/media/js/jquery.dataTables.min.js"></script>
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
</body>
</html>