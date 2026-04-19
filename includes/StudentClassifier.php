<?php
// file: includes/StudentClassifier.php

class StudentClassifier {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get student classification (New Student or Old Student)
     */
    public function getStudentClassification($student_id) {
        try {
            $query = "SELECT 
                        CASE 
                            WHEN COUNT(*) > 0 THEN 'Old Student'
                            ELSE 'New Student'
                        END as type,
                        MIN(created_at) as first_enrollment,
                        COUNT(*) as total_enrollments
                      FROM enrollments 
                      WHERE student_id = :student_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':student_id' => $student_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return [
                    'type' => 'New Student',
                    'first_enrollment' => null,
                    'total_enrollments' => 0
                ];
            }
            
            return $result;
            
        } catch(PDOException $e) {
            error_log("Error in getStudentClassification: " . $e->getMessage());
            return [
                'type' => 'New Student',
                'first_enrollment' => null,
                'total_enrollments' => 0
            ];
        }
    }
    
    /**
     * Get student type badge with color
     */
    public function getStudentTypeBadge($student_id) {
        try {
            $query = "SELECT COUNT(*) as enrollment_count 
                      FROM enrollments 
                      WHERE student_id = :student_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':student_id' => $student_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($result && $result['enrollment_count'] > 0) {
                return [
                    'type' => 'Old Student',
                    'color' => '#10b981',
                    'icon' => 'fa-undo-alt'
                ];
            } else {
                return [
                    'type' => 'New Student',
                    'color' => '#f59e0b',
                    'icon' => 'fa-star'
                ];
            }
            
        } catch(PDOException $e) {
            error_log("Error in getStudentTypeBadge: " . $e->getMessage());
            return [
                'type' => 'New Student',
                'color' => '#f59e0b',
                'icon' => 'fa-star'
            ];
        }
    }
    
    /**
     * Get complete enrollment history for a student
     */
    public function getEnrollmentHistory($student_id) {
        try {
            $query = "SELECT 
                        e.*,
                        gl.grade_name,
                        s.section_name,
                        sy.year_name as school_year_name,
                        sy.start_year,
                        sy.end_year,
                        CASE 
                            WHEN e.status = 'Pending' THEN 'Pending'
                            WHEN e.status = 'Enrolled' THEN 'Enrolled'
                            WHEN e.status = 'Approved' THEN 'Approved'
                            ELSE e.status
                        END as status
                      FROM enrollments e
                      LEFT JOIN grade_levels gl ON e.grade_id = gl.id
                      LEFT JOIN sections s ON e.section_id = s.id
                      LEFT JOIN school_years sy ON e.school_year_id = sy.id
                      WHERE e.student_id = :student_id
                      ORDER BY e.id DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':student_id' => $student_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no results, try a simpler query without school_years table
            if (empty($results)) {
                $query = "SELECT 
                            e.*,
                            gl.grade_name,
                            s.section_name,
                            e.school_year as school_year_name
                          FROM enrollments e
                          LEFT JOIN grade_levels gl ON e.grade_id = gl.id
                          LEFT JOIN sections s ON e.section_id = s.id
                          WHERE e.student_id = :student_id
                          ORDER BY e.id DESC";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute([':student_id' => $student_id]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $results;
            
        } catch(PDOException $e) {
            error_log("Error in getEnrollmentHistory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if student has active enrollment
     */
    public function hasActiveEnrollment($student_id) {
        try {
            $query = "SELECT COUNT(*) as count 
                      FROM enrollments 
                      WHERE student_id = :student_id 
                      AND status IN ('Enrolled', 'Approved', 'Pending')
                      ORDER BY id DESC 
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':student_id' => $student_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
            
        } catch(PDOException $e) {
            error_log("Error in hasActiveEnrollment: " . $e->getMessage());
            return false;
        }
    }
}
?>