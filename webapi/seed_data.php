<?php
// Simple starter data — run this once to populate your tables/files
$stats = [
  'today' => 12,
  'week' => 89,
  'total' => 456,
  'loginsToday' => 3,
  'locations' => ['New York, NY', 'Los Angeles, CA', 'London, UK', 'Sydney, AU']
];
$activity = [
  ['time' => '2025-11-30 14:23:45', 'coachName' => 'Krue Dog', 'type' => 'login', 'details' => 'Admin login', 'location' => 'New York, NY'],
  ['time' => '2025-11-30 13:15:22', 'coachName' => 'Wolf Coach', 'type' => 'profile_update', 'details' => 'Updated bio', 'location' => 'Los Angeles, CA'],
  ['time' => '2025-11-30 12:45:10', 'coachName' => 'Lion Specialist', 'type' => 'image_upload', 'details' => 'Added before/after photo', 'location' => 'London, UK']
];

// Save to JSON files (your backend can read these)
file_put_contents('stats.json', json_encode($stats));
file_put_contents('activity.json', json_encode($activity));

echo "Starter data seeded! Refresh dashboard.";
?>