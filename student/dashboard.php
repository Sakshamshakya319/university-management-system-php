<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Handle course enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    $course_id = $_POST['course_id'];
    
    // Check if already enrolled
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
        try {
            $stmt->execute([$student_id, $course_id]);
            $success_message = 'Successfully enrolled in the course';
        } catch(PDOException $e) {
            $error_message = 'Failed to enroll in the course';
        }
    } else {
        $error_message = 'You are already enrolled in this course';
    }
}

// Fetch enrolled courses
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as teacher_name 
    FROM courses c 
    LEFT JOIN users u ON c.teacher_id = u.id
    INNER JOIN enrollments e ON c.id = e.course_id 
    WHERE e.student_id = ?");
$stmt->execute([$student_id]);
$enrolled_courses = $stmt->fetchAll();

// Get course IDs for the enrolled courses
$course_ids = array_column($enrolled_courses, 'id');

// Fetch upcoming exams for enrolled courses (limited to 3)
$upcoming_exams = [];
if (!empty($course_ids)) {
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT es.*, c.course_name, s.semester_name
        FROM exam_schedules es
        INNER JOIN courses c ON es.course_id = c.id
        INNER JOIN semesters s ON es.semester_id = s.id
        WHERE es.course_id IN ($placeholders)
        AND es.exam_date >= CURDATE()
        ORDER BY es.exam_date ASC
        LIMIT 3");
    $stmt->execute($course_ids);
    $upcoming_exams = $stmt->fetchAll();
}

// Fetch recent announcements for enrolled courses (limited to 3)
$recent_announcements = [];
if (!empty($course_ids)) {
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT a.*, c.course_name, u.full_name as author_name
        FROM announcements a
        INNER JOIN courses c ON a.course_id = c.id
        INNER JOIN users u ON a.author_id = u.id
        WHERE a.course_id IN ($placeholders)
        AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
        ORDER BY a.priority DESC, a.publish_date DESC
        LIMIT 3");
    $stmt->execute($course_ids);
    $recent_announcements = $stmt->fetchAll();
}
// Fetch available courses for enrollment
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as teacher_name 
    FROM courses c 
    LEFT JOIN users u ON c.teacher_id = u.id
    WHERE c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)");
$stmt->execute([$student_id]);
$available_courses = $stmt->fetchAll();

// Fetch attendance records
$stmt = $pdo->prepare("
    SELECT a.*, c.course_name 
    FROM attendance a 
    INNER JOIN courses c ON a.course_id = c.id 
    WHERE a.student_id = ? 
    ORDER BY a.date DESC");
$stmt->execute([$student_id]);
$attendance_records = $stmt->fetchAll();

// Fetch grades
$stmt = $pdo->prepare("
    SELECT g.*, c.course_name 
    FROM grades g 
    INNER JOIN courses c ON g.course_id = c.id 
    WHERE g.student_id = ? 
    ORDER BY g.submission_date DESC");
$stmt->execute([$student_id]);
$grades = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - University Management System</title>
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
            <a class="navbar-brand" href="#">Student Portal</a>
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
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Student Dashboard</h1>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <!-- Upcoming Exams Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Upcoming Exams</h5>
                                <a href="exams.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($upcoming_exams) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course</th>
                                                    <th>Type</th>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Venue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($upcoming_exams as $exam): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($exam['course_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($exam['exam_type']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($exam['exam_date'])); ?></td>
                                                        <td><?php echo date('h:i A', strtotime($exam['start_time'])) . ' - ' . date('h:i A', strtotime($exam['end_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($exam['venue']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No upcoming exams scheduled.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Announcements Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Announcements</h5>
                                <a href="exams.php#announcements" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_announcements) > 0): ?>
                                    <div class="list-group">
                                        <?php foreach ($recent_announcements as $announcement): ?>
                                            <div class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($announcement['publish_date'])); ?>
                                                    </small>
                                                </div>
                                                <p class="mb-1"><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 100) . (strlen($announcement['content']) > 100 ? '...' : ''))); ?></p>
                                                <small class="text-muted">
                                                    Course: <?php echo htmlspecialchars($announcement['course_name']); ?> | 
                                                    By: <?php echo htmlspecialchars($announcement['author_name']); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No recent announcements.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Continue with the rest of the dashboard content -->
                <section id="courses" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">My Courses</h2>
                    <div class="row">
                        <?php foreach ($enrolled_courses as $course): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card course-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                                        <p class="card-text"><?php echo htmlspecialchars($course['description']); ?></p>
                                        <p class="card-text"><small class="text-muted">Instructor: <?php echo htmlspecialchars($course['teacher_name']); ?></small></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Available Courses for Enrollment -->
                    <h3 class="mt-5 mb-4">Available Courses</h3>
                    <div class="row">
                        <?php foreach ($available_courses as $course): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card course-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                                        <p class="card-text"><?php echo htmlspecialchars($course['description']); ?></p>
                                        <p class="card-text"><small class="text-muted">Instructor: <?php echo htmlspecialchars($course['teacher_name']); ?></small></p>
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" name="enroll" class="btn btn-primary">Enroll</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Attendance Section -->
                <section id="attendance" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">Attendance Records</h2>
                    <div class="table-responsive">
                        <table class="table table-striped" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($record['date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $record['status'] === 'present' ? 'success' : 
                                                    ($record['status'] === 'absent' ? 'danger' : 'warning');
                                            ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Grades Section -->
                <section id="grades" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">Grades</h2>
                    <div class="table-responsive">
                        <table class="table table-striped" id="gradesTable">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Assignment</th>
                                    <th>Grade</th>
                                    <th>Max Grade</th>
                                    <th>Percentage</th>
                                    <th>Submission Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $grade): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($grade['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($grade['assignment_name']); ?></td>
                                        <td><?php echo $grade['grade']; ?></td>
                                        <td><?php echo $grade['max_grade']; ?></td>
                                        <td>
                                            <?php 
                                            $percentage = ($grade['grade'] / $grade['max_grade']) * 100;
                                            echo number_format($percentage, 1) . '%';
                                            ?>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($grade['submission_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables@1.10.18/media/js/jquery.dataTables.min.js"></script>
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
    <script>
        $(document).ready(function() {
            $('#attendanceTable').DataTable();
            $('#gradesTable').DataTable();
        });
    </script>
</body>
</html>