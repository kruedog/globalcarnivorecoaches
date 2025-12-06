<?php
// Stateless logout — client clears its own localStorage
header('Content-Type: application/json');
echo json_encode(['success' => true]);
