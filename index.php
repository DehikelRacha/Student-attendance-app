<?php
/* ===============================
   CONFIG + DB CONNECTION (Ex 3)
================================ */
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "");
define("DB_NAME", "awp");

function db_connect() {
    try {
        return new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        file_put_contents("db_errors.log", $e->getMessage(), FILE_APPEND);
        return null;
    }
}

$conn = db_connect();

/* ===============================
   EXERCISE 1 — ADD STUDENT (JSON)
================================ */
$msg1 = "";
if (isset($_POST["add_json"])) {
    $id    = trim($_POST["student_id"]);
    $name  = trim($_POST["name"]);
    $group = trim($_POST["group"]);

    if ($id && $name && $group && ctype_digit($id)) {
        $file = "students.json";
        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        $data[] = ["student_id"=>$id,"name"=>$name,"group"=>$group];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        $msg1 = "Student added to JSON.";
    } else {
        $msg1 = "Invalid input.";
    }
}

/* ===============================
   EXERCISE 2 — ATTENDANCE (JSON)
================================ */
$msg2 = "";
$date = date("Y-m-d");
$attFile = "attendance_$date.json";
$students = file_exists("students.json") ? json_decode(file_get_contents("students.json"), true) : [];

if (isset($_POST["take_attendance"])) {
    if (file_exists($attFile)) {
        $msg2 = "Attendance already taken today.";
    } else {
        $arr = [];
        foreach ($_POST["status"] as $id=>$st) {
            $arr[] = ["student_id"=>$id,"status"=>$st];
        }
        file_put_contents($attFile, json_encode($arr, JSON_PRETTY_PRINT));
        $msg2 = "Attendance saved.";
    }
}

/* ===============================
   EXERCISE 4 — STUDENTS DB CRUD
================================ */
$msg4 = "";
if ($conn && isset($_POST["add_db"])) {
    $stmt = $conn->prepare(
        "INSERT INTO students(fullname, matricule, group_id) VALUES (?,?,?)"
    );
    $stmt->execute([$_POST["fullname"],$_POST["matricule"],$_POST["group_db"]]);
    $msg4 = "Student added to database.";
}

$db_students = [];
if ($conn) {
    $db_students = $conn->query("SELECT * FROM students")->fetchAll(PDO::FETCH_ASSOC);
}

/* ===============================
   EXERCISE 5 — ATTENDANCE SESSION
================================ */
$msg5 = "";
if ($conn && isset($_POST["create_session"])) {
    $stmt = $conn->prepare(
     "INSERT INTO attendance_sessions
     (course_id,group_id,date,opened_by,status)
     VALUES (?,?,?,?,?)"
    );
    $stmt->execute([
        $_POST["course"],$_POST["group_session"],
        date("Y-m-d"),$_POST["prof"],"open"
    ]);
    $msg5 = "Session created.";
}

if ($conn && isset($_GET["close"])) {
    $stmt = $conn->prepare("UPDATE attendance_sessions SET status='closed' WHERE id=?");
    $stmt->execute([$_GET["close"]]);
    $msg5 = "Session closed.";
}
?>

<!DOCTYPE html>
<html>
<body>

<h2>Exercise 1 — Add Student (JSON)</h2>
<form method="post">
ID <input name="student_id">
Name <input name="name">
Group <input name="group">
<button name="add_json">Add</button>
</form>
<?= $msg1 ?><hr>

<h2>Exercise 2 — Take Attendance</h2>
<form method="post">
<?php foreach ($students as $s): ?>
<?= $s["name"] ?>
<select name="status[<?= $s["student_id"] ?>]">
<option value="present">Present</option>
<option value="absent">Absent</option>
</select><br>
<?php endforeach; ?>
<button name="take_attendance">Save Attendance</button>
</form>
<?= $msg2 ?><hr>
<h2>Exercise 3 — DB Test</h2>
<?= $conn ? "Connection successful" : "Connection failed" ?><hr>

<h2>Exercise 4 — Students DB</h2>
<form method="post">
Full name <input name="fullname">
Matricule <input name="matricule">
Group <input name="group_db">
<button name="add_db">Add</button>
</form>
<?= $msg4 ?>

<ul>
<?php foreach ($db_students as $s): ?>
<li><?= $s["fullname"] ?> (<?= $s["matricule"] ?>)</li>
<?php endforeach; ?>
</ul>
<hr>

<h2>Exercise 5 — Attendance Session</h2>
<form method="post">
Course <input name="course">
Group <input name="group_session">
Professor <input name="prof">
<button name="create_session">Create session</button>
</form>
<?= $msg5 ?>

</body>
</html>