<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$dir = __DIR__ . '/backups';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $backups = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'png') {
                $monthName = str_replace(['place_', '.png'], '', $file);
                $backups[] = [
                    "month" => $monthName,
                    "url" => 'backups/' . $file
                ];
            }
        }
    }
    usort($backups, function($a, $b) { return strcmp($b['month'], $a['month']); });
    echo json_encode($backups);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (isset($input['image']) && isset($input['month'])) {
        $imgData = $input['image'];
        $month = $input['month'];
        $imgData = str_replace('data:image/png;base64,', '', $imgData);
        $imgData = str_replace(' ', '+', $imgData);
        $decodedData = base64_decode($imgData);

        if (!is_dir($dir)) { 
            mkdir($dir, 0755, true); 
        }
        
        $safeMonth = preg_replace('/[^a-zA-Z0-9_-]/', '', $month);
        $filePath = $dir . '/place_' . $safeMonth . '.png';

        if (file_put_contents($filePath, $decodedData)) {
            echo json_encode(["status" => "success"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Server storage failed"]);
        }
    }
    exit;
}
?>