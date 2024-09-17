// <?php
// session_start();  
// if (isset($_SESSION['username'])) {  
    
//     exit();  
// }  

// header('Content-Type: application/json');

// $filename = 'students_results.txt';
// $students = [];

// if (file_exists($filename)) {
//     $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
//     foreach ($lines as $line) {
//         $data = explode(',', $line);
//         if (count($data) >= 7) {
//             $students[] = [
//                 'username' => trim($data[0]),
//                 'password' => trim($data[1]),
//                 'scores' => [
//                     'BIO' => intval(trim($data[2])),
//                     'CHEM' => intval(trim($data[3])),
//                     'ENG' => intval(trim($data[4])),
//                     'MTH' => intval(trim($data[5])),
//                     'PHY' => intval(trim($data[6]))
//                 ]
//             ];
//         }
//     }
    
//     echo json_encode($students);
// } else {
//     echo json_encode(['error' => 'File not found']);
// }
