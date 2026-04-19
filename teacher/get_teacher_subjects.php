<?php
session_start();
include("../config/database.php");

// Check if user is teacher
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user']['id'];
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

$subjects = [];

if($section_id > 0) {
    $query = "
        SELECT DISTINCT 
            cs.subject_id as id,
            sub.subject_name
        FROM class_schedules cs
        JOIN subjects sub ON cs.subject_id = sub.id
        WHERE cs.teacher_id = :teacher_id 
        AND cs.section_id = :section_id
        AND cs.status = 'active'
        ORDER BY sub.subject_name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':teacher_id' => $teacher_id,
        ':section_id' => $section_id
    ]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($subjects);
?>