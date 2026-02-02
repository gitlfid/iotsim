<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$userId = $_SESSION['user_id'];
header('Content-Type: application/json');

// DELETE NOTIFICATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $notifId = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notifId, $userId);
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// GET NOTIFICATIONS
$sql = "SELECT * FROM notifications WHERE user_id = '$userId' ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($sql);

$data = [];
while($row = $result->fetch_assoc()) {
    // Format waktu relative (e.g., "5 min ago")
    $timeAgo = 'Just now';
    $ts = strtotime($row['created_at']);
    $diff = time() - $ts;
    if ($diff < 60) $timeAgo = 'Just now';
    elseif ($diff < 3600) $timeAgo = floor($diff/60) . ' min ago';
    elseif ($diff < 86400) $timeAgo = floor($diff/3600) . ' hours ago';
    else $timeAgo = date('d M Y', $ts);

    $row['time_ago'] = $timeAgo;
    $data[] = $row;
}

echo json_encode($data);
?>