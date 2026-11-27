<?php

namespace Tests\Feature;

use App\Http\Resources\Api\V1\EventTableResource;
use App\Models\DeviceLock;
use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use App\Models\MemoryMediaItem;
use App\Models\UploadOperation;
use App\Models\UploadOperationFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Tests\TestCase;

class CloudflareR2StorageTest extends TestCase
{
  use RefreshDatabase;

  public function test_r2_disk_uses_flysystem_aws_s3_v3_and_public_cdn_urls(): void
  {
    $this->assertTrue(class_exists(AwsS3V3Adapter::class));

    config([
      'xv.media_disk' => 'r2',
      'filesystems.disks.r2' => [
        'driver' => 's3',
        'key' => 'test-r2-key',
        'secret' => 'test-r2-secret',
        'region' => 'auto',
        'bucket' => 'xv-natalia-storage',
        'endpoint' => 'https://example-account.r2.cloudflarestorage.com',
        'use_path_style_endpoint' => false,
        'url' => 'https://storage.xv-natalia-isabela.com',
        'visibility' => 'public',
        'throw' => true,
      ],
    ]);

    $disk = Storage::disk('r2');
    $this->assertSame('https://storage.xv-natalia-isabela.com/memories/XV-NATALIA/test.mp4', $disk->url('memories/XV-NATALIA/test.mp4'));

    $event = Event::create([
      'code' => 'XV-NATALIA',
      'name' => 'Natalia Isabella',
      'status' => 'active',
    ]);

    $table = EventTable::create([
      'event_id' => $event->id,
      'slug' => 'mesa-01',
      'number' => '01',
      'code' => 'Mesa 1',
      'capacity' => 10,
      'qr_token' => 'qrt_testtoken123456',
      'qr_image_path' => 'qrs/XV-NATALIA/qr-mesa-01.png',
      'qr_svg_path' => 'qrs/XV-NATALIA/qr-mesa-01.svg',
      'access_url' => 'https://xv-natalia-isabela.com/acceso?mesa=mesa-01&token=qrt_testtoken123456',
    ]);

    $request = Request::create('https://api.xv-natalia-isabela.com/api/v1/tables', 'GET');
    app()->instance('request', $request);

    $tablePayload = (new EventTableResource($table))->resolve($request);
    $this->assertStringStartsWith(
      'https://storage.xv-natalia-isabela.com/qrs/XV-NATALIA/qr-mesa-01.png?v=',
      $tablePayload['qrImageUrl']
    );

    $memory = Memory::create([
      'external_id' => 'xv-nat-r2-1',
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'guest_name' => 'Maria Perez',
      'table_code' => 'Mesa 1',
      'moment_tag' => 'Fiesta',
      'caption' => 'Prueba R2',
      'media_type' => 'video',
      'status' => Memory::STATUS_APPROVED,
      'timestamp_ms' => 1795750000000,
    ]);

    $mediaItem = MemoryMediaItem::create([
      'memory_id' => $memory->id,
      'external_id' => 'm-1',
      'media_blob_key' => '',
      'media_type' => 'video',
      'disk' => 'r2',
      'file_path' => 'memories/XV-NATALIA/xv-nat-r2-1/clip.mp4',
      'poster_path' => 'memories/XV-NATALIA/xv-nat-r2-1/posters/m-1.jpg',
      'mime_type' => 'video/mp4',
      'byte_size' => 1024,
      'sort_order' => 0,
    ]);

    $urls = $mediaItem->publicUrls();
    $this->assertSame('https://storage.xv-natalia-isabela.com/memories/XV-NATALIA/xv-nat-r2-1/clip.mp4', $urls['imageUrl']);
    $this->assertSame('https://storage.xv-natalia-isabela.com/memories/XV-NATALIA/xv-nat-r2-1/posters/m-1.jpg', $urls['posterUrl']);

    $op = UploadOperation::create([
      'operation_key' => 'uop-r2-test-1',
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'memory_external_id' => 'xv-nat-r2-1',
      'guest_name' => 'Maria Perez',
      'table_code' => 'Mesa 1',
      'moment_tag' => 'Fiesta',
      'caption' => '',
      'request_origin' => 'https://xv-natalia-isabela.com',
      'status' => UploadOperation::STATUS_COMPLETED,
      'total_files' => 1,
      'uploaded_files' => 1,
      'completed_files' => 1,
      'failed_files' => 0,
    ]);

    $opFile = UploadOperationFile::create([
      'upload_operation_id' => $op->id,
      'file_key' => 'm-1',
      'original_name' => 'clip.mp4',
      'mime_type' => 'video/mp4',
      'media_type' => 'video',
      'byte_size' => 1024,
      'uploaded_bytes' => 1024,
      'chunk_size' => 1024,
      'total_chunks' => 1,
      'sort_order' => 0,
      'status' => UploadOperationFile::STATUS_COMPLETED,
      'disk' => 'r2',
      'final_path' => 'memories/XV-NATALIA/xv-nat-r2-1/clip.mp4',
    ]);

    $this->assertSame(
      'https://storage.xv-natalia-isabela.com/memories/XV-NATALIA/xv-nat-r2-1/clip.mp4',
      $opFile->resolvePublicUrl($opFile->final_path, 'https://xv-natalia-isabela.com')
    );

    $lock = DeviceLock::create([
      'event_id' => $event->id,
      'event_table_id' => $table->id,
      'device_id' => 'dev-r2-1',
      'actor_key' => 'mesa-1__maria-perez',
      'guest_name' => 'Maria Perez',
      'guest_name_slug' => 'maria-perez',
      'avatar_path' => 'profiles/XV-NATALIA/avatars/a.jpg',
    ]);

    $this->assertStringStartsWith(
      'https://storage.xv-natalia-isabela.com/profiles/XV-NATALIA/avatars/a.jpg',
      $lock->avatar_url
    );
  }
}
