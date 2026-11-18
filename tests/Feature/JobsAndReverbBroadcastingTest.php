<?php

namespace Tests\Feature;

use App\Events\CommentCreated;
use App\Events\CommentLikeUpdated;
use App\Events\MemoryCreated;
use App\Events\MemoryReactionUpdated;
use App\Events\MemoryShared;
use App\Events\PartyCelebrated;
use App\Events\TablesUpdated;
use App\Jobs\ProcessCommentJob;
use App\Jobs\ProcessCommentLikeJob;
use App\Jobs\ProcessLikeJob;
use App\Jobs\ProcessMemoryJob;
use App\Jobs\ProcessPartyReactionJob;
use App\Jobs\ProcessShareJob;
use App\Jobs\ProcessTableOccupancyJob;
use App\Models\Event;
use App\Models\EventTable;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryReaction;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobsAndReverbBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_all_events_implement_should_broadcast_now(): void
    {
        $this->assertInstanceOf(ShouldBroadcastNow::class, new MemoryCreated('XV-NATALIA', ['id' => 'mem-1']));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new MemoryReactionUpdated('XV-NATALIA', 'mem-1', 1, 0, 0, 'act', 'likes'));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentCreated('XV-NATALIA', 'mem-1', ['id' => 'c-1'], 1));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentLikeUpdated('XV-NATALIA', 'mem-1', 'c-1', 1, 'act', true));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new MemoryShared('XV-NATALIA', 'mem-1', 5));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new PartyCelebrated('XV-NATALIA', 3));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new TablesUpdated('XV-NATALIA', []));
    }

    public function test_all_events_broadcast_to_correct_channel_and_event_name(): void
    {
        $rx = new MemoryReactionUpdated('XV-NATALIA', 'mem-1', 1, 0, 0, 'act', 'likes');
        $this->assertSame('event.XV-NATALIA', $rx->broadcastOn()[0]->name);
        $this->assertSame('memory.reaction.updated', $rx->broadcastAs());

        $cc = new CommentCreated('XV-NATALIA', 'mem-1', ['id' => 'c-1'], 1);
        $this->assertSame('event.XV-NATALIA', $cc->broadcastOn()[0]->name);
        $this->assertSame('comment.created', $cc->broadcastAs());

        $cl = new CommentLikeUpdated('XV-NATALIA', 'mem-1', 'c-1', 1, 'act', true);
        $this->assertSame('event.XV-NATALIA', $cl->broadcastOn()[0]->name);
        $this->assertSame('comment.liked', $cl->broadcastAs());

        $ms = new MemoryShared('XV-NATALIA', 'mem-1', 5);
        $this->assertSame('event.XV-NATALIA', $ms->broadcastOn()[0]->name);
        $this->assertSame('memory.shared', $ms->broadcastAs());

        $pc = new PartyCelebrated('XV-NATALIA', 3);
        $this->assertSame('event.XV-NATALIA', $pc->broadcastOn()[0]->name);
        $this->assertSame('party.celebrated', $pc->broadcastAs());

        $tu = new TablesUpdated('XV-NATALIA', []);
        $this->assertSame('event.XV-NATALIA', $tu->broadcastOn()[0]->name);
        $this->assertSame('tables.updated', $tu->broadcastAs());
    }

    public function test_process_party_reaction_job_executes_and_broadcasts(): void
    {
        EventFacade::fake([PartyCelebrated::class]);

        $event = Event::byCode('XV-NATALIA')->first();

        // 1. Toggle on
        $job1 = new ProcessPartyReactionJob($event->id, 'actor-test-1', 'dev-1');
        $job1->handle();

        $event->refresh();
        $this->assertSame(1, $event->party_celebrations_count);
        EventFacade::assertDispatched(PartyCelebrated::class, function (PartyCelebrated $e) {
            return $e->eventCode === 'XV-NATALIA' && $e->count === 1;
        });

        // 2. Toggle off
        $job2 = new ProcessPartyReactionJob($event->id, 'actor-test-1', 'dev-1');
        $job2->handle();

        $event->refresh();
        $this->assertSame(0, $event->party_celebrations_count);
        EventFacade::assertDispatched(PartyCelebrated::class, function (PartyCelebrated $e) {
            return $e->eventCode === 'XV-NATALIA' && $e->count === 0;
        });
    }

    public function test_process_like_job_executes_and_broadcasts(): void
    {
        EventFacade::fake([MemoryReactionUpdated::class]);

        $event = Event::byCode('XV-NATALIA')->first();
        $table = EventTable::where('event_id', $event->id)->first();
        $memory = Memory::create([
            'external_id' => 'mem-test-like-1',
            'event_id' => $event->id,
            'event_table_id' => $table->id,
            'guest_name' => 'Maria',
            'table_code' => $table->code,
            'media_type' => 'image',
            'timestamp_ms' => now()->getTimestampMs(),
            'status' => 'approved',
        ]);

        MemoryReaction::create([
            'memory_id' => $memory->id,
            'actor_key' => 'actor-m-1',
            'device_id' => 'dev-m-1',
            'choice' => 'likes',
        ]);

        $job = new ProcessLikeJob($memory->id, 'actor-m-1', 'likes');
        $job->handle();

        $memory->refresh();
        $this->assertSame(1, $memory->likes_count);

        EventFacade::assertDispatched(MemoryReactionUpdated::class, function (MemoryReactionUpdated $e) {
            return $e->memoryId === 'mem-test-like-1' && $e->likes === 1 && $e->choice === 'likes';
        });
    }

    public function test_process_comment_job_executes_and_broadcasts(): void
    {
        EventFacade::fake([CommentCreated::class]);

        $event = Event::byCode('XV-NATALIA')->first();
        $table = EventTable::where('event_id', $event->id)->first();
        $memory = Memory::create([
            'external_id' => 'mem-test-comment-1',
            'event_id' => $event->id,
            'event_table_id' => $table->id,
            'guest_name' => 'Lucia',
            'table_code' => $table->code,
            'media_type' => 'image',
            'timestamp_ms' => now()->getTimestampMs(),
            'status' => 'approved',
        ]);

        $comment = MemoryComment::create([
            'external_id' => 'c-test-1',
            'memory_id' => $memory->id,
            'author' => 'Lucia',
            'table_label' => $table->code,
            'text' => 'Hermoso momento!',
            'likes_count' => 0,
        ]);

        $job = new ProcessCommentJob($comment->id);
        $job->handle();

        $memory->refresh();
        $this->assertSame(1, $memory->comments_count);

        EventFacade::assertDispatched(CommentCreated::class, function (CommentCreated $e) {
            return $e->memoryId === 'mem-test-comment-1' && $e->commentsCount === 1;
        });
    }

    public function test_process_comment_like_job_executes_and_broadcasts(): void
    {
        EventFacade::fake([CommentLikeUpdated::class]);

        $event = Event::byCode('XV-NATALIA')->first();
        $table = EventTable::where('event_id', $event->id)->first();
        $memory = Memory::create([
            'external_id' => 'mem-test-clike-1',
            'event_id' => $event->id,
            'event_table_id' => $table->id,
            'guest_name' => 'Mateo',
            'table_code' => $table->code,
            'media_type' => 'image',
            'timestamp_ms' => now()->getTimestampMs(),
            'status' => 'approved',
        ]);

        $comment = MemoryComment::create([
            'external_id' => 'c-test-2',
            'memory_id' => $memory->id,
            'author' => 'Mateo',
            'table_label' => $table->code,
            'text' => 'Gran fiesta!',
            'likes_count' => 0,
        ]);

        $job = new ProcessCommentLikeJob($comment->id, 'actor-liker-1', 1, true);
        $job->handle();

        EventFacade::assertDispatched(CommentLikeUpdated::class, function (CommentLikeUpdated $e) {
            return $e->commentId === 'c-test-2' && $e->likes === 1 && $e->liked === true;
        });
    }

    public function test_process_share_job_executes_and_broadcasts(): void
    {
        EventFacade::fake([MemoryShared::class]);

        $event = Event::byCode('XV-NATALIA')->first();
        $table = EventTable::where('event_id', $event->id)->first();
        $memory = Memory::create([
            'external_id' => 'mem-test-share-1',
            'event_id' => $event->id,
            'event_table_id' => $table->id,
            'guest_name' => 'Sara',
            'table_code' => $table->code,
            'media_type' => 'image',
            'timestamp_ms' => now()->getTimestampMs(),
            'status' => 'approved',
        ]);

        $job = new ProcessShareJob($memory->id);
        $job->handle();

        $memory->refresh();
        $this->assertSame(1, $memory->shares_count);

        EventFacade::assertDispatched(MemoryShared::class, function (MemoryShared $e) {
            return $e->memoryId === 'mem-test-share-1' && $e->shares === 1;
        });
    }

    public function test_process_table_occupancy_job_broadcasts(): void
    {
        EventFacade::fake([TablesUpdated::class]);

        $event = Event::byCode('XV-NATALIA')->first();

        $job = new ProcessTableOccupancyJob($event->id);
        $job->handle();

        EventFacade::assertDispatched(TablesUpdated::class, function (TablesUpdated $e) {
            return $e->eventCode === 'XV-NATALIA' && count($e->tables) === 6;
        });
    }

    public function test_process_memory_job_broadcasts(): void
    {
        EventFacade::fake([MemoryCreated::class]);

        $event = Event::byCode('XV-NATALIA')->first();
        $table = EventTable::forEvent($event->id)->first();

        $memory = Memory::create([
            'event_id' => $event->id,
            'event_table_id' => $table->id,
            'external_id' => 'mem-test-create-job-1',
            'guest_name' => 'Carlos',
            'table_code' => $table->code,
            'media_type' => 'image',
            'timestamp_ms' => now()->getTimestampMs(),
            'status' => 'approved',
        ]);

        $job = new ProcessMemoryJob($memory->id);
        $job->handle();

        EventFacade::assertDispatched(MemoryCreated::class, function (MemoryCreated $e) {
            return $e->eventCode === 'XV-NATALIA' && $e->memory['id'] === 'mem-test-create-job-1';
        });
    }
}
