<?php
// get_student_details.php

include('../partials-front/constantsss.php'); 

header('Content-Type: application/json');

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$response = [
    'extracurricular' => [],
    'disciplinary' => []
];

if ($student_id > 0) {
    // --- 1. Fetch Extracurricular Records (7 Columns) ---
    $sql_extra = "
        SELECT 
            a.title AS activity_title, 
            a.category,                          /* <-- ADDED: Category */
            e.level, 
            e.rank_position, 
            e.percent,                           /* <-- ADDED: Percent */
            e.remarks, 
            DATE_FORMAT(e.date_participated, '%M %d, %Y') as date_participated 
        FROM participations e               
        JOIN activities a ON e.activity_id = a.id 
        WHERE e.student_id = ? 
        ORDER BY date_participated DESC
    ";
    
    if ($stmt_extra = $conn->prepare($sql_extra)) {
        $stmt_extra->bind_param("i", $student_id);
        $stmt_extra->execute();
        $result_extra = $stmt_extra->get_result();

        while ($row = $result_extra->fetch_assoc()) {
            // Format percent to 2 decimal places (or as needed)
            $row['percent'] = number_format((float)$row['percent'], 2);
            $response['extracurricular'][] = $row;
        }
        $stmt_extra->close();
    }

    // --- 2. Fetch Disciplinary Records (No change needed) ---
    $sql_disc = "
        SELECT 
            offense_type, 
            description, 
            action_taken, 
            severity, 
            DATE_FORMAT(date_reported, '%M %d, %Y') as date_reported 
        FROM discipline_records             
        WHERE student_id = ? 
        ORDER BY date_reported DESC
    ";
    
    if ($stmt_disc = $conn->prepare($sql_disc)) {
        $stmt_disc->bind_param("i", $student_id);
        $stmt_disc->execute();
        $result_disc = $stmt_disc->get_result();

        while ($row = $result_disc->fetch_assoc()) {
            $response['disciplinary'][] = $row;
        }
        $stmt_disc->close();
    }
}

$conn->close();

echo json_encode($response);
?>