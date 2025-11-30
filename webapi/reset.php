<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die(json_encode(['error'=>'POST only']));

$visitsFile = __DIR__ . '/visits.json';
$activityFile = __DIR__ . '/activity_log.json';

file_put_contents($visitsFile, json_encode([
    'total'=>0,'today'=>0,'week'=>0,
    'last_day'=>'','last_week'=>'',
    'ips'=>[],'locations'=>[],'last_update'=>time()
], JSON_PRETTY_PRINT));

file_put_contents($activityFile, "[]");

echo json_encode(['success'=>true,'message'=>'All data reset']);