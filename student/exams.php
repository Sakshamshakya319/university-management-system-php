<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch enrolled courses for the student
$stmt = $pdo->prepare("
    SELECT c.id, c.course_name 
    FROM courses c 
    INNER JOIN enrollments e ON c.id = e.course_id 
    WHERE e.student_id = ?");
$stmt->execute([$student_id]);
$enrolled_courses = $stmt->fetchAll();

// Get course IDs for the enrolled courses
$course_ids = array_column($enrolled_courses, 'id');

// Fetch exam schedules for enrolled courses
$exams = [];
if (!empty($course_ids)) {
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT es.*, c.course_name, s.semester_name, u.full_name as teacher_name
        FROM exam_schedules es
        INNER JOIN courses c ON es.course_id = c.id
        INNER JOIN semesters s ON es.semester_id = s.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE es.course_id IN ($placeholders)
        ORDER BY es.exam_date ASC");
    $stmt->execute($course_ids);
    $exams = $stmt->fetchAll();
}

// Fetch announcements for enrolled courses
$announcements = [];
if (!empty($course_ids)) {
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT a.*, c.course_name, u.full_name as author_name
        FROM announcements a
        INNER JOIN courses c ON a.course_id = c.id
        INNER JOIN users u ON a.author_id = u.id
        WHERE a.course_id IN ($placeholders)
        AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
        ORDER BY a.priority DESC, a.publish_date DESC");
    $stmt->execute($course_ids);
    $announcements = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams & Announcements - University Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .priority-high {
            border-left: 5px solid #dc3545;
        }
        .priority-medium {
            border-left: 5px solid #ffc107;
        }
        .priority-low {
            border-left: 5px solid #0dcaf0;
        }
        .exam-card {
            transition: transform 0.3s;
        }
        .exam-card:hover {
            transform: translateY(-5px);
        }
        .countdown {
            font-weight: bold;
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="exams.php">
                                <i class="fas fa-file-alt me-2"></i>Exams & Announcements
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4 main-content">
                <!-- Announcements Section -->
                <section id="announcements" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">Announcements</h2>
                    <div class="row">
                        <?php if (count($announcements) > 0): ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 priority-<?php echo htmlspecialchars($announcement['priority']); ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                            <span class="badge bg-<?php 
                                                echo $announcement['priority'] === 'high' ? 'danger' : 
                                                    ($announcement['priority'] === 'medium' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst(htmlspecialchars($announcement['priority'])); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    Course: <?php echo htmlspecialchars($announcement['course_name'] ?? 'All Courses'); ?>
                                                </small>
                                            </p>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    By: <?php echo htmlspecialchars($announcement['author_name']); ?>
                                                </small>
                                            </p>
                                        </div>
                                        <div class="card-footer text-muted">
                                            <div class="d-flex justify-content-between">
                                                <span>Published: <?php echo date('M d, Y', strtotime($announcement['publish_date'])); ?></span>
                                                <?php if ($announcement['expiry_date']): ?>
                                                    <span>Expires: <?php echo date('M d, Y', strtotime($announcement['expiry_date'])); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">No announcements found</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Exam Schedules Section -->
                <section id="exam-schedules" class="mb-5">
                    <h2 class="border-bottom pb-2 mb-4">Upcoming Exams</h2>
                    <div class="row">
                        <?php if (count($exams) > 0): ?>
                            <?php foreach ($exams as $exam): ?>
                                <?php 
                                    $exam_date = strtotime($exam['exam_date']);
                                    $today = strtotime(date('Y-m-d'));
                                    $days_remaining = floor(($exam_date - $today) / (60 * 60 * 24));
                                    $badge_class = $days_remaining <= 3 ? 'danger' : ($days_remaining <= 7 ? 'warning' : 'info');
                                ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card exam-card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($exam['course_name']); ?></h5>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo ucfirst(htmlspecialchars($exam['exam_type'])); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text">Semester: <?php echo htmlspecialchars($exam['semester_name']); ?></p>
                                            <p class="card-text">Date: <?php echo date('F d, Y', strtotime($exam['exam_date'])); ?></p>
                                            <p class="card-text">Time: <?php echo date('h:i A', strtotime($exam['start_time'])) . ' - ' . date('h:i A', strtotime($exam['end_time'])); ?></p>
                                            <p class="card-text">Venue: <?php echo htmlspecialchars($exam['venue']); ?></p>
                                            <p class="card-text">Max Marks: <?php echo htmlspecialchars($exam['max_marks']); ?></p>
                                            <?php if ($days_remaining >= 0): ?>
                                                <div class="alert alert-<?php echo $badge_class; ?> mt-3">
                                                    <span class="countdown"><?php echo $days_remaining; ?></span> days remaining
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-secondary mt-3">Exam completed</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">No upcoming exams found</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>