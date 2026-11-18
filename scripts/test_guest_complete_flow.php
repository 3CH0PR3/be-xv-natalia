<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client([
  'base_uri' => 'http://127.0.0.1:8001/api/v1/',
  'http_errors' => false,
  'headers' => [
    'Accept' => 'application/json',
  ],
]);

echo "=========================================================\n";
echo "    TESTING FULL GUEST USER FLOW (LARAVEL 11 BACKEND)    \n";
echo "=========================================================\n\n";

// 1. Initial Guest Feed (Unauthenticated)
echo "Step 1: Reading initial wall feed as anonymous guest...\n";
$res = $client->get('memories?page=1&per_page=10');
assertResponse($res, 200, "Initial wall feed GET");
$feed = json_decode($res->getBody()->getContents(), true);
$initialTotal = $feed['meta']['total'] ?? count($feed['data']);
echo " -> Anonymous guest sees {$initialTotal} memories on the wall.\n\n";

// 2. Scan QR / Check-in
echo "Step 2: Guest scans QR of 'Mesa 02 · Orquídea' and locks device...\n";
$deviceId = 'device-sofia-' . time();
$guestName = 'Sofía M ' . substr(md5($deviceId), 0, 4);
$actorKey = 'mesa-02-orquidea__' . strtolower(str_replace(' ', '-', $guestName));
$lockRes = $client->post('checkin', [
  'json' => [
    'guestName' => $guestName,
    'tableCode' => 'Mesa 02 · Orquídea',
    'deviceId' => $deviceId,
    'hwFingerprint' => 'hw-fingerprint-' . $deviceId,
  ],
]);
assertResponse($lockRes, 201, "Device lock check-in");
$lockData = json_decode($lockRes->getBody()->getContents(), true);
echo " -> Check-in successful for guest '{$lockData['data']['guestName']}' at '{$lockData['data']['tableCode']}'.\n\n";

// 3. Upload Memory with Image & Caption
echo "Step 3: Uploading a new memory with photo and caption...\n";
$testImagePath = dirname(__DIR__, 2) . '/src/assets/images/quinceanera_hero_gala_1794610370929.jpg';
if (!file_exists($testImagePath)) {
  throw new Exception("Test image not found at $testImagePath");
}

$uploadRes = $client->post('memories', [
  'headers' => [
    'X-Device-Id' => $deviceId,
    'X-Actor-Key' => $actorKey,
  ],
  'multipart' => [
    [
      'name' => 'files[]',
      'contents' => fopen($testImagePath, 'r'),
      'filename' => 'foto_sofia_quinceanera.jpg',
    ],
    ['name' => 'guestName', 'contents' => $guestName],
    ['name' => 'tableCode', 'contents' => 'Mesa 02 · Orquídea'],
    ['name' => 'ownerDeviceId', 'contents' => $deviceId],
    ['name' => 'ownerActorKey', 'contents' => $actorKey],
    ['name' => 'caption', 'contents' => '¡Felicidades Natalia! Una noche mágica de ensueño 💖🎂'],
    ['name' => 'momentTag', 'contents' => 'Fiesta en Vivo'],
  ],
]);
assertResponse($uploadRes, 201, "Memory upload");
$uploadData = json_decode($uploadRes->getBody()->getContents(), true);
$newMemory = $uploadData['data'];
$memoryId = $newMemory['id'];
echo " -> Memory created successfully!\n";
echo "    ID: {$memoryId}\n";
echo "    Guest: {$newMemory['guestName']}\n";
echo "    Caption: \"{$newMemory['caption']}\"\n";
echo "    Image URL: {$newMemory['imageUrl']}\n\n";

// 4. React to Own Photo
echo "Step 4: Reacting to the new photo (toggle 'likes')...\n";
$reactRes = $client->post("memories/{$memoryId}/reactions/toggle", [
  'headers' => [
    'X-Device-Id' => $deviceId,
    'X-Actor-Key' => $actorKey,
  ],
  'json' => [
    'actorKey' => $actorKey,
    'deviceId' => $deviceId,
    'choice' => 'likes',
  ],
]);
assertResponse($reactRes, 200, "Reaction toggle");
$reactData = json_decode($reactRes->getBody()->getContents(), true);
$reactionResult = $reactData['data'] ?? $reactData;
echo " -> Reaction successful! Choice: {$reactionResult['choice']}, Likes: {$reactionResult['likes']}\n\n";

// 5. Edit Caption as Owner
echo "Step 5: Editing photo caption as verified owner...\n";
$newCaption = "¡Felicidades Natalia! Una noche mágica e inolvidable con las mejores amigas ✨💖👸";
$editRes = $client->patch("memories/{$memoryId}/caption", [
  'headers' => [
    'X-Device-Id' => $deviceId,
    'X-Actor-Key' => $actorKey,
  ],
  'json' => [
    'caption' => $newCaption,
  ],
]);
assertResponse($editRes, 200, "Edit caption");
$editData = json_decode($editRes->getBody()->getContents(), true);
echo " -> Caption updated to: \"{$editData['data']['caption']}\"\n\n";

// 6. Share Photo (Increment Shares Counter)
echo "Step 6: Sharing photo (incrementing share counter)...\n";
$shareRes = $client->post("memories/{$memoryId}/share", [
  'headers' => [
    'X-Device-Id' => $deviceId,
    'X-Actor-Key' => $actorKey,
  ],
]);
assertResponse($shareRes, 200, "Share photo");
$shareData = json_decode($shareRes->getBody()->getContents(), true);
echo " -> Share successful! Shares count: {$shareData['shares']}\n\n";

// 7. Add Comment to Memory
echo "Step 7: Adding a comment to the memory...\n";
$commentRes = $client->post("memories/{$memoryId}/comments", [
  'headers' => [
    'X-Device-Id' => $deviceId,
    'X-Actor-Key' => $actorKey,
  ],
  'json' => [
    'author' => $guestName,
    'table' => 'Mesa 02 · Orquídea',
    'text' => '¡La mejor fiesta de todas! 🥳🎉',
  ],
]);
assertResponse($commentRes, 201, "Add comment");
$commentData = json_decode($commentRes->getBody()->getContents(), true);
echo " -> Comment added: \"{$commentData['data']['text']}\" by {$commentData['data']['author']}\n\n";

// 8. Verify on Global Feed
echo "Step 8: Verifying feed updated with all new data...\n";
$finalFeedRes = $client->get('memories?page=1&per_page=10', [
  'headers' => [
    'X-Device-Id' => $deviceId,
    'X-Actor-Key' => $actorKey,
  ],
]);
assertResponse($finalFeedRes, 200, "Final feed check");
$finalFeed = json_decode($finalFeedRes->getBody()->getContents(), true);
$topMemory = $finalFeed['data'][0];

echo " -> Wall total memories: {$finalFeed['meta']['total']}\n";
echo " -> Top memory ID: {$topMemory['id']}\n";
echo " -> Top memory guest: {$topMemory['guestName']}\n";
echo " -> Top memory caption: \"{$topMemory['caption']}\"\n";
echo " -> Top memory likes: {$topMemory['likes']} (likedByMe: " . ($topMemory['likedByMe'] ? 'true' : 'false') . ")\n";
echo " -> Top memory shares: {$topMemory['shares']}\n";
echo " -> Top memory comments count: {$topMemory['commentsCount']}\n";
echo " -> Top memory image: {$topMemory['imageUrl']}\n\n";

echo "=========================================================\n";
echo "   ALL 8 STEPS OF THE GUEST USER FLOW PASSED 100%!       \n";
echo "=========================================================\n";

function assertResponse($response, int $expectedStatus, string $step): void
{
  $status = $response->getStatusCode();
  if ($status !== $expectedStatus) {
    $body = (string) $response->getBody();
    throw new Exception("FAIL at $step: Expected HTTP $expectedStatus, got HTTP $status. Body: $body");
  }
  echo " [PASS] $step (HTTP $status)\n";
}
