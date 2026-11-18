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

echo "Testing Host Comment Deletion...\n";

// 1. Get a comment from memory mem-showcase-01
$res = $client->get('memories/mem-showcase-01');
$data = json_decode($res->getBody()->getContents(), true);
$comments = $data['data']['comments'] ?? [];
if (empty($comments)) {
    echo "No comments found on mem-showcase-01. Adding one first...\n";
    $addRes = $client->post('memories/mem-showcase-01/comments', [
        'json' => [
            'author' => 'Invitado Test',
            'table' => 'Mesa 01 · Principal',
            'text' => 'Comentario de prueba para ser eliminado por la anfitriona.',
        ],
    ]);
    $newComment = json_decode($addRes->getBody()->getContents(), true)['data'];
    $commentId = $newComment['id'];
} else {
    $commentId = $comments[0]['id'];
}

echo "Target Comment ID: $commentId\n";

// 2. Try deleting WITHOUT host pin and without active device lock
$unauthorizedDelete = $client->delete("memories/mem-showcase-01/comments/{$commentId}");
echo "Without Host PIN -> Status: " . $unauthorizedDelete->getStatusCode() . " (Expected 403)\n";

// 3. Try deleting WITH X-Host-Pin: 1515 (as Anfitriona)
$hostDelete = $client->delete("memories/mem-showcase-01/comments/{$commentId}", [
    'headers' => [
        'X-Host-Pin' => '1515',
    ],
]);
$hostBody = (string) $hostDelete->getBody();
echo "WITH Host PIN (Anfitriona) -> Status: " . $hostDelete->getStatusCode() . " (Expected 200)\n";
echo "Response Body: $hostBody\n";

if ($hostDelete->getStatusCode() === 200) {
    echo "SUCCESS: Host successfully deleted the comment!\n";
} else {
    echo "FAILURE: Could not delete comment as host.\n";
    exit(1);
}
