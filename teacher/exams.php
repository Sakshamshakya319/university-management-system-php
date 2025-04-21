<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../auth/login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Handle exam schedule creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $course_id = $_POST['course_id'];
    $semester_id = $_POST['semester_id'];
    $exam_type = $_POST['exam_type'];
    $exam_date = $_POST['exam_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $venue = $_POST['venue'];
    $max_marks = $_POST['max_marks'];

    // Verify that the teacher is assigned to this course
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $teacher_id]);
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("INSERT INTO exam_schedules (course_id, semester_id, exam_type, exam_date, start_time, end_time, venue, max_marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$course_id, $semester_id, $exam_type, $exam_date, $start_time, $end_time, $venue, $max_marks]);
            $success_message = 'Exam schedule created successfully';
        } catch(PDOException $e) {
            $error_message = 'Failed to create exam schedule: ' . $e->getMessage();
        }
    } else {
        $error_message = 'You are not authorized to create exams for this course';
    }
}

// Handle announcement creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $course_id = $_POST['course_id'];
    $priority = $_POST['priority'];
    $expiry_date = $_POST['expiry_date'] ?: null;

    // Verify that the teacher is assigned to this course
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $teacher_id]);
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, author_id, course_id, priority, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$title, $content, $teacher_id, $course_id, $priority, $expiry_date]);
            $success_message = 'Announcement created successfully';
        } catch(PDOException $e) {
            $error_message = 'Failed to create announcement: ' . $e->getMessage();
        }
    } else {
        $error_message = 'You are not authorized to create announcements for this course';
    }
}

// Fetch teacher's courses
$stmt = $pdo->prepare("SELECT id, course_name FROM courses WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll();

// Fetch active semesters
$stmt = $pdo->prepare("SELECT id, semester_name FROM semesters WHERE status IN ('upcoming', 'active')");
$stmt->execute();
$semesters = $stmt->fetchAll();

// Fetch existing exam schedules for teacher's courses
$stmt = $pdo->prepare("
    SELECT es.*, c.course_name, s.semester_name 
    FROM exam_schedules es
    INNER JOIN courses c ON es.course_id = c.id
    INNER JOIN semesters s ON es.semester_id = s.id
    WHERE c.teacher_id = ?
    ORDER BY es.exam_date DESC");
$stmt->execute([$teacher_id]);
$exams = $stmt->fetchAll();

// Fetch existing announcements created by the teacher
$stmt = $pdo->prepare("
    SELECT a.*, c.course_name 
    FROM announcements a
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.author_id = ?
    ORDER BY a.publish_date DESC");
$stmt->execute([$teacher_id]);
$announcements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams & Announcements - University Management System</title>
    <link rel="stylesheet" href="./assets/css/style.css">
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
                            <a class="nav-link" href="attendance.php">
                                <i class="fas fa-calendar-check me-2"></i>Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="grades.php">
                                <i class="fas fa-graduation-cap me-2"></i>Grades
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
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success mt-3"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger mt-3"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Exam Schedules Section -->
                <section id="exam-schedules" class="mb-5">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-4">
                        <h2>Exam Schedules</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">
                            <i class="fas fa-plus me-2"></i>Create New Exam
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Course</th>
                                    <th>Semester</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Venue</th>
                                    <th>Max Marks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($exams) > 0): ?>
                                    <?php foreach ($exams as $exam): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['semester_name']); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($exam['exam_type'])); ?></td>
                                            <td><?php echo htmlspecialchars($exam['exam_date']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['start_time']) . ' - ' . htmlspecialchars($exam['end_time']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['venue']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['max_marks']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No exam schedules found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Announcements Section -->
                <section id="announcements" class="mb-5">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-4">
                        <h2>Announcements</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                            <i class="fas fa-plus me-2"></i>Create New Announcement
                        </button>
                    </div>

                    <div class="row">
                        <?php if (count($announcements) > 0): ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
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
            </main>
        </div>
    </div>

    <!-- Create Exam Modal -->
    <div class="modal fade" id="createExamModal" tabindex="-1" aria-labelledby="createExamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createExamModalLabel">Create New Exam Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="semester_id" class="form-label">Semester</label>
                                <select class="form-select" id="semester_id" name="semester_id" required>
                                    <option value="">Select Semester</option>
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester['id']; ?>">
                                            <?php echo htmlspecialchars($semester['semester_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="exam_type" class="form-label">Exam Type</label>
                                <select class="form-select" id="exam_type" name="exam_type" required>
                                    <option value="midterm">Midterm</option>
                                    <option value="final">Final</option>
                                    <option value="quiz">Quiz</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="exam_date" class="form-label">Exam Date</label>
                                <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="venue" class="form-label">Venue</label>
                                <input type="text" class="form-control" id="venue" name="venue" required>
                            </div>
                            <div class="col-md-6">
                                <label for="max_marks" class="form-label">Maximum Marks</label>
                                <input type="number" step="0.01" class="form-control" id="max_marks" name="max_marks" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="create_exam" class="btn btn-primary">Create Exam Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Announcement Modal -->
    <div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-labelledby="createAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAnnouncementModalLabel">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="course_id" class="form-label">Course (Optional)</label>
                                <select class="form-select" id="course_id" name="course_id">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="create_announcement" class="btn btn-primary">Create Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>