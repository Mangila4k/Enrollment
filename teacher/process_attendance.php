<?php
/**
 * Process Teacher Attendance via QR Code Scan
 * Handles both Time In and Time Out with the same QR code
 */

// Set Philippine Timezone
date_default_timezone_set('Asia/Manila');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access. Please login as teacher.'
    ]);
    exit();
}

$teacher_id = $_SESSION['user']['id'];
$token = isset($_GET['token']) ? $_GET['token'] : '';

if(empty($token)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid QR code. Token is missing.'
    ]);
    exit();
}

// Get current Philippine time
$current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
$current_time_str = $current_time->format('H:i:s');
$current_date = $current_time->format('Y-m-d');

try {
    // Check if there's an existing attendance record for today with this token
    $stmt = $conn->prepare("
        SELECT * FROM teacher_attendance 
        WHERE teacher_id = ? 
        AND qr_token = ?
        AND date = CURDATE()
        LIMIT 1
    ");
    $stmt->execute([$teacher_id, $token]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$attendance) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid QR code. Please generate a new QR code.'
        ]);
        exit();
    }
    
    // Check if session is still active (not expired)
    if($attendance['expires_at'] <= date('Y-m-d H:i:s')) {
        echo json_encode([
            'success' => false, 
            'message' => 'QR code has expired. Please generate a new QR code.'
        ]);
        exit();
    }
    
    // Check if time_in is already recorded
    $time_in_recorded = ($attendance['time_in'] !== null && $attendance['time_in'] != '00:00:00');
    $time_out_recorded = ($attendance['time_out'] !== null && $attendance['time_out'] != '00:00:00');
    
    if($time_in_recorded && $time_out_recorded) {
        echo json_encode([
            'success' => false, 
            'message' => 'You have already completed your attendance for today (both Time In and Time Out recorded).'
        ]);
        exit();
    }
    
    if(!$time_in_recorded) {
        // FIRST SCAN: Record TIME IN
        $cutoff_time = '08:00:00';
        $status = ($current_time_str > $cutoff_time) ? 'Late' : 'Present';
        
        $update = $conn->prepare("
            UPDATE teacher_attendance 
            SET time_in = ?, 
                status = ?
            WHERE id = ?
        ");
        $update->execute([$current_time_str, $status, $attendance['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Time In recorded successfully at " . $current_time->format('h:i A') . ". Status: " . $status . ". Scan the SAME QR code again for Time Out.",
            'time_in' => $current_time->format('h:i A'),
            'status' => $status,
            'action' => 'time_in'
        ]);
    } 
    elseif(!$time_out_recorded) {
        // SECOND SCAN: Record TIME OUT (using the SAME QR code)
        $update = $conn->prepare("
            UPDATE teacher_attendance 
            SET time_out = ?, 
                session_status = 'completed'
            WHERE id = ?
        ");
        $update->execute([$current_time_str, $attendance['id']]);
        
        // Calculate hours worked
        $time_in_obj = new DateTime($attendance['time_in']);
        $time_out_obj = new DateTime($current_time_str);
        $interval = $time_in_obj->diff($time_out_obj);
        $hours_worked = $interval->format('%h hours %i minutes');
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Time Out recorded successfully at " . $current_time->format('h:i A') . ". Total hours worked: {$hours_worked}. Attendance completed!",
            'time_out' => $current_time->format('h:i A'),
            'hours_worked' => $hours_worked,
            'action' => 'time_out'
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Attendance Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred. Please try again.'
    ]);
}
?>