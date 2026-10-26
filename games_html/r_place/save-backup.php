<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$dir = __DIR__ . '/backups';
$liveCanvasFile = __DIR__ . '/canvas.png';

// Initialize a blank 1024x1024 white canvas file if it doesn't exist
if (!file_exists($liveCanvasFile)) {
    $img = imagecreatetruecolor(1024, 1024);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    imagepng($img, $liveCanvasFile);
    imagedestroy($img);
}

// 1. Fetch available backups (GET)
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

// 2. Process actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (isset($input['action'])) {
        // ACTION A: Draw single pixel onto the live canvas file
        if ($input['action'] === 'draw' && isset($input['x']) && isset($input['y']) && isset($input['color'])) {
            $x = intval($input['x']);
            $y = intval($input['y']);
            $hexColor = $input['color']; // expected format "#ffffff"
            
            if ($x >= 0 && $x < 1024 && $y >= 0 && $y < 1024 && preg_match('/^#[a-fA-F0-9]{6}$/', $hexColor)) {
                // Parse Hex Color
                $r = hexdec(substr($hexColor, 1, 2));
                $g = hexdec(substr($hexColor, 3, 2));
                $b = hexdec(substr($hexColor, 5, 2));
                
                // Modify existing server image file
                $img = imagecreatefrompng($liveCanvasFile);
                if ($img) {
                    $colorAlloc = imagecolorallocate($img, $r, $g, $b);
                    imagesetpixel($img, $x, $y, $colorAlloc);
                    imagepng($img, $liveCanvasFile);
                    imagedestroy($img);
                    
                    echo json_encode(["status" => "success"]);
                    exit;
                }
            }
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid drawing payload"]);
            exit;
        }
        
        // ACTION B: Handle dynamic monthly backup snapshot archiving
        if ($input['action'] === 'archive' && isset($input['image']) && isset($input['month'])) {
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
                echo json_encode(["status" => "error", "message" => "Archiving failed"]);
            }
            exit;
        }
    }
    
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Unknown action"]);
    exit;
}
?>