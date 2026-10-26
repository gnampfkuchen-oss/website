<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$dir = __DIR__ . '/backups';

// AUFGABE 1: Liste der vorhandenen Backups ausgeben (GET-Anfrage)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $backups = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'png') {
                // Extrahiert den Monatsnamen aus "place_2026-10.png"
                $monthName = str_replace(['place_', '.png'], '', $file);
                $backups[] = [
                    "month" => $monthName,
                    "url" => 'backups/' . $file
                ];
            }
        }
    }
    // Neueste Backups zuerst anzeigen
    usort($backups, function($a, $b) {
        return strcmp($b['month'], $a['month']);
    });
    echo json_encode($backups);
    exit;
}

// AUFGABE 2: Neues Monats-Backup abspeichern (POST-Anfrage)
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
            echo json_encode(["status" => "success", "message" => "Backup gespeichert"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Schreibfehler auf dem Server"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Ungültige Daten"]);
    }
    exit;
}
?>
