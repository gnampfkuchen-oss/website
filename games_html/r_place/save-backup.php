<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Actions");

$dir = __DIR__ . '/backups';
$mainCanvas = __DIR__ . '/canvas.png';

function ensureCanvasExists($path) {
    if (!file_exists($path)) {
        $img = imagecreatetruecolor(1024, 1024);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        imagepng($img, $path);
        imagedestroy($img);
    }
}

ensureCanvasExists($mainCanvas);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'load_live') {
        $type = pathinfo($mainCanvas, PATHINFO_EXTENSION);
        $data = file_get_contents($mainCanvas);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        echo json_encode(["image" => $base64]);
        exit;
    }

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
    
    if (isset($input['action']) && $input['action'] === 'paint' && isset($input['x']) && isset($input['y']) && isset($input['color'])) {
        $x = intval($input['x']);
        $y = intval($input['y']);
        $colorHex = $input['color'];

        if (preg_match('/^#?([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})$/i', $colorHex, $parts)) {
            $r = hexdec($parts[1]);
            $g = hexdec($parts[2]);
            $b = hexdec($parts[3]);

            $img = imagecreatefrompng($mainCanvas);
            $allocatedColor = imagecolorallocate($img, $r, $g, $b);
            imagesetpixel($img, $x, $y, $allocatedColor);
            imagepng($img, $mainCanvas);
            imagedestroy($img);

            echo json_encode(["status" => "success"]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid color format"]);
        }
        exit;
    }

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
        exit;
    }
}
?>