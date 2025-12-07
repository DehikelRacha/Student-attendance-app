<?php
// ============================================================================
// SCHOOL ATTENDANCE MANAGEMENT SYSTEM - SINGLE FILE SOLUTION
// ============================================================================
session_start();

// Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_attendance_system');
define('JSON_DIR', __DIR__ . '/data/');
define('LOG_FILE', __DIR__ . '/error.log');

// Create data directory if it doesn't exist
if (!file_exists(JSON_DIR)) {
    mkdir(JSON_DIR, 0777, true);
}

// Database Connection Function
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Database Error: " . $e->getMessage() . "\n", 3, LOG_FILE);
        return false;
    }
}

// Initialize Database (run once)
function initializeDatabase() {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        // Create tables
        $sql = "
        CREATE TABLE IF NOT EXISTS students (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id VARCHAR(20) UNIQUE NOT NULL,
            fullname VARCHAR(100) NOT NULL,
            group_name VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS courses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_code VARCHAR(20) UNIQUE NOT NULL,
            course_name VARCHAR(100) NOT NULL
        );
        
        CREATE TABLE IF NOT EXISTS professors (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL
        );
        
        CREATE TABLE IF NOT EXISTS attendance_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT,
            group_name VARCHAR(20),
            session_date DATE NOT NULL,
            opened_by INT,
            status ENUM('open', 'closed') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS attendance_records (
            id INT PRIMARY KEY AUTO_INCREMENT,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            status ENUM('present', 'absent') NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        INSERT IGNORE INTO courses (course_code, course_name) VALUES 
        ('CS101', 'Introduction to Programming'),
        ('CS102', 'Database Systems');
        
        INSERT IGNORE INTO professors (name, email) VALUES 
        ('Dr. Smith', 'smith@university.edu'),
        ('Dr. Johnson', 'johnson@university.edu');
        ";
        
        $conn->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Init DB Error: " . $e->getMessage() . "\n", 3, LOG_FILE);
        return false;
    }
}

// JSON Functions
function readJSON($filename) {
    $filepath = JSON_DIR . $filename;
    if (file_exists($filepath)) {
        $data = file_get_contents($filepath);
        return json_decode($data, true) ?: [];
    }
    return [];
}

function saveJSON($filename, $data) {
    $filepath = JSON_DIR . $filename;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filepath, $json);
}

// Handle Actions
$action = $_GET['action'] ?? 'home';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Initialize DB on first run
if (!isset($_SESSION['db_initialized'])) {
    initializeDatabase();
    $_SESSION['db_initialized'] = true;
}

// Process Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['form_type'] ?? '') {
        
        // Exercise 1: Add Student (JSON)
        case 'add_student_json':
            $student_id = trim($_POST['student_id']);
            $name = trim($_POST['name']);
            $group = trim($_POST['group']);
            
            if (empty($student_id) || empty($name) || empty($group)) {
                $_SESSION['message'] = "<div class='error'>All fields are required!</div>";
            } else {
                $students = readJSON('students.json');
                
                // Check duplicate
                foreach ($students as $s) {
                    if ($s['student_id'] == $student_id) {
                        $_SESSION['message'] = "<div class='error'>Student ID already exists!</div>";
                        header("Location: ?action=exercise1");
                        exit;
                    }
                }
                
                $students[] = [
                    'student_id' => $student_id,
                    'name' => $name,
                    'group' => $group,
                    'added_at' => date('Y-m-d H:i:s')
                ];
                
                if (saveJSON('students.json', $students)) {
                    $_SESSION['message'] = "<div class='success'>Student added successfully to JSON!</div>";
                }
            }
            header("Location: ?action=exercise1");
            exit;
            
        // Exercise 2: Take Attendance (JSON)
        case 'take_attendance_json':
            $today = date('Y-m-d');
            $attendanceFile = "attendance_$today.json";
            
            if (file_exists(JSON_DIR . $attendanceFile)) {
                $_SESSION['message'] = "<div class='warning'>Attendance for today already taken!</div>";
            } else {
                $students = readJSON('students.json');
                $attendance = [];
                
                foreach ($students as $student) {
                    $status = $_POST['status_' . $student['student_id']] ?? 'absent';
                    $attendance[] = [
                        'student_id' => $student['student_id'],
                        'name' => $student['name'],
                        'group' => $student['group'],
                        'status' => $status
                    ];
                }
                
                if (saveJSON($attendanceFile, $attendance)) {
                    $_SESSION['message'] = "<div class='success'>Attendance saved for $today!</div>";
                }
            }
            header("Location: ?action=exercise2");
            exit;
            
        // Exercise 4: Add Student (Database)
        case 'add_student_db':
            $conn = getDBConnection();
            if ($conn) {
                $student_id = trim($_POST['student_id_db']);
                $fullname = trim($_POST['fullname']);
                $group_name = trim($_POST['group_name']);
                
                $stmt = $conn->prepare("INSERT INTO students (student_id, fullname, group_name) VALUES (?, ?, ?)");
                if ($stmt->execute([$student_id, $fullname, $group_name])) {
                    $_SESSION['message'] = "<div class='success'>Student added to database!</div>";
                }
            }
            header("Location: ?action=exercise4");
            exit;
            
        // Exercise 5: Create Session
        case 'create_session':
            $conn = getDBConnection();
            if ($conn) {
                $course_id = $_POST['course_id'];
                $group_name = $_POST['group_name'];
                $opened_by = $_POST['professor_id'];
                $session_date = date('Y-m-d');
                
                $stmt = $conn->prepare("INSERT INTO attendance_sessions (course_id, group_name, session_date, opened_by) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$course_id, $group_name, $session_date, $opened_by])) {
                    $_SESSION['message'] = "<div class='success'>Session created! ID: " . $conn->lastInsertId() . "</div>";
                }
            }
            header("Location: ?action=exercise5");
            exit;
    }
}

// HTML Starts Here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Attendance System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background: #2c3e50; color: white; padding: 20px; border-radius: 10px 10px 0 0; margin-bottom: 20px; }
        header h1 { margin-bottom: 10px; }
        .nav { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav a { padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        .nav a:hover { background: #2980b9; }
        .content { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        button, .btn { background: #2ecc71; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: background 0.3s; text-decoration: none; display: inline-block; }
        button:hover, .btn:hover { background: #27ae60; }
        .btn-secondary { background: #3498db; }
        .btn-secondary:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
        tr:hover { background: #f8f9fa; }
        .radio-group { display: flex; gap: 20px; }
        .radio-option { display: flex; align-items: center; gap: 5px; }
        .exercise-title { color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #3498db; }
        .db-test { padding: 15px; background: #e8f4f8; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📚 School Attendance Management System</h1>
            <p>PHP Tutorial 3 - Complete Solution</p>
        </header>
        
        <div class="nav">
            <a href="?action=home">🏠 Home</a>
            <a href="?action=exercise1">📝 Exercise 1 (JSON)</a>
            <a href="?action=exercise2">✅ Exercise 2 (Attendance)</a>
            <a href="?action=exercise3">🔧 Exercise 3 (DB Config)</a>
            <a href="?action=exercise4">👥 Exercise 4 (Students DB)</a>
            <a href="?action=exercise5">📊 Exercise 5 (Sessions)</a>
        </div>
        
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>
        
        <div class="content">
            <?php 
            // Main content switch
            switch($action): 
                case 'home': default: 
            ?>
                <h2 class="exercise-title">🏠 Welcome to School Attendance System</h2>
                <p>This is a complete solution for PHP Tutorial 3 exercises.</p>
                
                <div class="db-test">
                    <h3>🔧 Database Connection Test</h3>
                    <?php
                    $conn = getDBConnection();
                    if ($conn):
                    ?>
                        <p class="success">✅ Database connection successful!</p>
                        <p>Database: <?php echo DB_NAME; ?></p>
                        <p>Host: <?php echo DB_HOST; ?></p>
                    <?php else: ?>
                        <p class="error">❌ Database connection failed. Please check your MySQL server.</p>
                        <p>Make sure MySQL is running and credentials in the code are correct.</p>
                    <?php endif; ?>
                </div>
                
                <h3>📁 Available JSON Files</h3>
                <?php
                $files = glob(JSON_DIR . '*.json');
                if ($files):
                ?>
                    <ul>
                        <?php foreach($files as $file): ?>
                            <li><?php echo basename($file); ?> (<?php echo round(filesize($file)/1024, 2); ?> KB)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No JSON files created yet.</p>
                <?php endif; ?>
            <?php break; ?>
            
            <?php case 'exercise1': ?>
                <h2 class="exercise-title">📝 Exercise 1: Add Student (JSON)</h2>
                <p>Add student information to students.json file.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="add_student_json">
                    
                    <div class="form-group">
                        <label for="student_id">Student ID:</label>
                        <input type="text" id="student_id" name="student_id" required placeholder="e.g., 101">
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Full Name:</label>
                        <input type="text" id="name" name="name" required placeholder="e.g., John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label for="group">Group:</label>
                        <input type="text" id="group" name="group" required placeholder="e.g., Group A">
                    </div>
                    
                    <button type="submit">Add Student to JSON</button>
                </form>
                
                <h3>📋 Existing Students (JSON)</h3>
                <?php
                $students = readJSON('students.json');
                if ($students):
                ?>
                    <table>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Group</th>
                            <th>Added At</th>
                        </tr>
                        <?php foreach($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['group']); ?></td>
                            <td><?php echo htmlspecialchars($student['added_at'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No students in JSON file yet.</p>
                <?php endif; ?>
            <?php break; ?>
            
            <?php case 'exercise2': ?>
                <h2 class="exercise-title">✅ Exercise 2: Take Attendance (JSON)</h2>
                <?php
                $today = date('Y-m-d');
                $attendanceFile = "attendance_$today.json";
                $attendanceTaken = file_exists(JSON_DIR . $attendanceFile);
                
                if ($attendanceTaken):
                ?>
                    <div class="warning">
                        ⚠️ Attendance for today (<?php echo $today; ?>) has already been taken.
                    </div>
                    
                    <h3>📊 Today's Attendance</h3>
                    <?php
                    $attendance = readJSON($attendanceFile);
                    if ($attendance):
                    ?>
                        <table>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Group</th>
                                <th>Status</th>
                            </tr>
                            <?php foreach($attendance as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($record['name']); ?></td>
                                <td><?php echo htmlspecialchars($record['group']); ?></td>
                                <td>
                                    <span style="color: <?php echo $record['status'] == 'present' ? '#27ae60' : '#e74c3c'; ?>; font-weight: bold;">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <p>Take attendance for today (<?php echo $today; ?>):</p>
                    
                    <?php
                    $students = readJSON('students.json');
                    if ($students):
                    ?>
                        <form method="POST" action="">
                            <input type="hidden" name="form_type" value="take_attendance_json">
                            
                            <table>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Group</th>
                                    <th>Present/Absent</th>
                                </tr>
                                <?php foreach($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['group']); ?></td>
                                    <td>
                                        <div class="radio-group">
                                            <div class="radio-option">
                                                <input type="radio" id="present_<?php echo $student['student_id']; ?>" 
                                                       name="status_<?php echo $student['student_id']; ?>" 
                                                       value="present" checked>
                                                <label for="present_<?php echo $student['student_id']; ?>">Present</label>
                                            </div>
                                            <div class="radio-option">
                                                <input type="radio" id="absent_<?php echo $student['student_id']; ?>" 
                                                       name="status_<?php echo $student['student_id']; ?>" 
                                                       value="absent">
                                                <label for="absent_<?php echo $student['student_id']; ?>">Absent</label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                            
                            <button type="submit" style="margin-top: 20px;">Save Attendance for Today</button>
                        </form>
                    <?php else: ?>
                        <div class="warning">
                            No students found. Please add students in Exercise 1 first.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php break; ?>
            
            <?php case 'exercise3': ?>
                <h2 class="exercise-title">🔧 Exercise 3: Database Configuration</h2>
                <p>Database connection settings and test.</p>
                
                <div class="form-group">
                    <label>Database Host:</label>
                    <input type="text" value="<?php echo DB_HOST; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Database Name:</label>
                    <input type="text" value="<?php echo DB_NAME; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" value="<?php echo DB_USER; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" value="<?php echo DB_PASS ? '••••••' : '(empty)'; ?>" readonly>
                </div>
                
                <h3>🛠️ Database Status</h3>
                <?php
                $conn = getDBConnection();
                if ($conn):
                    // Check if tables exist
                    $tables = ['students', 'courses', 'professors', 'attendance_sessions', 'attendance_records'];
                    echo "<p class='success'>✅ Connected to database successfully!</p>";
                    echo "<ul>";
                    foreach ($tables as $table) {
                        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
                        if ($stmt->rowCount() > 0) {
                            echo "<li>✅ Table '$table' exists</li>";
                        } else {
                            echo "<li>❌ Table '$table' missing</li>";
                        }
                    }
                    echo "</ul>";
                    
                    // Show table counts
                    echo "<h4>📊 Table Records Count:</h4>";
                    echo "<ul>";
                    foreach ($tables as $table) {
                        try {
                            $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
                            $result = $stmt->fetch();
                            echo "<li>$table: " . $result['count'] . " records</li>";
                        } catch (Exception $e) {
                            echo "<li>$table: Table doesn't exist</li>";
                        }
                    }
                    echo "</ul>";
                    
                    $conn = null;
                else:
                    echo "<p class='error'>❌ Database connection failed!</p>";
                    echo "<p>Please ensure:</p>";
                    echo "<ol>";
                    echo "<li>MySQL server is running</li>";
                    echo "<li>Database '".DB_NAME."' exists</li>";
                    echo "<li>Username and password are correct</li>";
                    echo "</ol>";
                endif;
                ?>
            <?php break; ?>
            
            <?php case 'exercise4': ?>
                <h2 class="exercise-title">👥 Exercise 4: Student Management (Database)</h2>
                <p>Add and manage students in MySQL database.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="add_student_db">
                    
                    <div class="form-group">
                        <label for="student_id_db">Student ID:</label>
                        <input type="text" id="student_id_db" name="student_id_db" required placeholder="e.g., S101">
                    </div>
                    
                    <div class="form-group">
                        <label for="fullname">Full Name:</label>
                        <input type="text" id="fullname" name="fullname" required placeholder="e.g., Jane Smith">
                    </div>
                    
                    <div class="form-group">
                        <label for="group_name">Group:</label>
                        <select id="group_name" name="group_name" required>
                            <option value="">Select Group</option>
                            <option value="Group A">Group A</option>
                            <option value="Group B">Group B</option>
                            <option value="Group C">Group C</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <button type="submit">Add Student to Database</button>
                </form>
                
                <h3>📋 Students in Database</h3>
                <?php
                $conn = getDBConnection();
                if ($conn):
                    $stmt = $conn->query("SELECT * FROM students ORDER BY created_at DESC");
                    $students = $stmt->fetchAll();
                    
                    if ($students):
                ?>
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Group</th>
                            <th>Created At</th>
                        </tr>
                        <?php foreach($students as $student): ?>
                        <tr>
                            <td><?php echo $student['id']; ?></td>
                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($student['group_name']); ?></td>
                            <td><?php echo $student['created_at']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No students in database yet.</p>
                <?php endif; ?>
                <?php endif; ?>
            <?php break; ?>
            
            <?php case 'exercise5': ?>
                <h2 class="exercise-title">📊 Exercise 5: Attendance Sessions</h2>
                <p>Create and manage attendance sessions.</p>
                
                <h3>➕ Create New Session</h3>
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="create_session">
                    
                    <div class="form-group">
                        <label for="course_id">Course:</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php
                            $conn = getDBConnection();
                            if ($conn):
                                $stmt = $conn->query("SELECT * FROM courses");
                                while ($course = $stmt->fetch()):
                            ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                </option>
                            <?php 
                                endwhile;
                            endif;
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="group_name">Group:</label>
                        <select id="group_name" name="group_name" required>
                            <option value="">Select Group</option>
                            <option value="Group A">Group A</option>
                            <option value="Group B">Group B</option>
                            <option value="Group C">Group C</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="professor_id">Professor:</label>
                        <select id="professor_id" name="professor_id" required>
                            <option value="">Select Professor</option>
                            <?php
                            $conn = getDBConnection();
                            if ($conn):
                                $stmt = $conn->query("SELECT * FROM professors");
                                while ($prof = $stmt->fetch()):
                            ?>
                                <option value="<?php echo $prof['id']; ?>">
                                    <?php echo $prof['name']; ?>
                                </option>
                            <?php 
                                endwhile;
                            endif;
                            ?>
                        </select>
                    </div>
                    
                    <button type="submit">Create Session</button>
                </form>
                
                <h3>📋 Existing Sessions</h3>
                <?php
                $conn = getDBConnection();
                if ($conn):
                    $stmt = $conn->query("
                        SELECT s.*, c.course_code, c.course_name, p.name as professor_name 
                        FROM attendance_sessions s
                        LEFT JOIN courses c ON s.course_id = c.id
                        LEFT JOIN professors p ON s.opened_by = p.id
                        ORDER BY s.created_at DESC
                    ");
                    $sessions = $stmt->fetchAll();
                    
                    if ($sessions):
                ?>
                    <table>
                        <tr>
                            <th>Session ID</th>
                            <th>Course</th>
                            <th>Group</th>
                            <th>Date</th>
                            <th>Professor</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                        <?php foreach($sessions as $session): ?>
                        <tr>
                            <td><?php echo $session['id']; ?></td>
                            <td><?php echo $session['course_code'] ?? 'N/A'; ?></td>
                            <td><?php echo $session['group_name']; ?></td>
                            <td><?php echo $session['session_date']; ?></td>
                            <td><?php echo $session['professor_name'] ?? 'N/A'; ?></td>
                            <td>
                                <span style="color: <?php echo $session['status'] == 'open' ? '#27ae60' : '#e74c3c'; ?>; font-weight: bold;">
                                    <?php echo ucfirst($session['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $session['created_at']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No sessions created yet.</p>
                <?php endif; ?>
                
                <h3>🔒 Close Session (Manual SQL)</h3>
                <p>To close a session, run this SQL in phpMyAdmin or MySQL:</p>
                <pre style="background: #f4f4f4; padding: 10px; border-radius: 5px;">
UPDATE attendance_sessions SET status = 'closed' WHERE id = [SESSION_ID];
                </pre>
                <?php endif; ?>
            <?php break; ?>
            
            <?php endswitch; ?>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; color: #7f8c8d; font-size: 14px;">
            <p>School Attendance Management System | PHP Tutorial 3 | All Exercises Complete</p>
        </footer>
    </div>
</body>
</html>