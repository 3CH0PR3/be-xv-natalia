<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\Node\Stmt\If_;

class NestedIfVisitor extends NodeVisitorAbstract
{
    public array $nestedIfs = [];
    private int $ifDepth = 0;
    private string $currentFile = '';

    public function setFile(string $file): void
    {
        $this->currentFile = $file;
    }

    public function enterNode(Node $node)
    {
        // Notice: entering an anonymous function or class resets function-scope if tracking
        if ($node instanceof If_) {
            if ($this->ifDepth > 0) {
                $this->nestedIfs[] = basename($this->currentFile) . " at line " . $node->getStartLine();
            }
            $this->ifDepth++;
        }
        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof If_) {
            $this->ifDepth = max(0, $this->ifDepth - 1);
        }
        return null;
    }
}

$files = [
    __DIR__ . '/../app/Actions/DeviceLock/CheckinGuestAction.php',
    __DIR__ . '/../app/Actions/DeviceLock/ExpelGuestAction.php',
    __DIR__ . '/../app/Actions/Event/TogglePartyCelebrateAction.php',
    __DIR__ . '/../app/Actions/Moderation/PurgeEventDataAction.php',
    __DIR__ . '/../app/Events/CommentLikeUpdated.php',
    __DIR__ . '/../app/Events/TableOccupancyUpdated.php',
    __DIR__ . '/../app/Events/TablesUpdated.php',
    __DIR__ . '/../app/Http/Controllers/Api/V1/AppReleaseController.php',
    __DIR__ . '/../app/Http/Controllers/Api/V1/DeviceLockController.php',
    __DIR__ . '/../app/Http/Controllers/Api/V1/EventStateController.php',
    __DIR__ . '/../app/Http/Controllers/Api/V1/MemoryCommentController.php',
    __DIR__ . '/../app/Http/Controllers/Api/V1/MemoryController.php',
    __DIR__ . '/../app/Http/Controllers/Api/V1/ModerationController.php',
    __DIR__ . '/../app/Http/Controllers/Api/V1/TableController.php',
    __DIR__ . '/../app/Http/Controllers/Api/V1/TableGuestController.php',
    __DIR__ . '/../app/Http/Middleware/VerifyHostPinMiddleware.php',
    __DIR__ . '/../app/Http/Requests/Api/V1/ExpelGuestRequest.php',
    __DIR__ . '/../app/Http/Resources/Api/V1/MemoryCollection.php',
    __DIR__ . '/../app/Jobs/ProcessCommentJob.php',
    __DIR__ . '/../app/Jobs/ProcessCommentLikeJob.php',
    __DIR__ . '/../app/Jobs/ProcessLikeJob.php',
    __DIR__ . '/../app/Jobs/ProcessPartyReactionJob.php',
    __DIR__ . '/../app/Jobs/ProcessShareJob.php',
    __DIR__ . '/../app/Jobs/ProcessTableOccupancyJob.php',
    __DIR__ . '/../app/Services/ModerationService.php',
    __DIR__ . '/../routes/apis/tables/api-tables.php',
    __DIR__ . '/../tests/Feature/JobsAndReverbBroadcastingTest.php',
    __DIR__ . '/../tests/Feature/TableGuestAndCapacityTest.php',
];

$parser = (new ParserFactory())->createForHostVersion();
$allNested = [];

foreach ($files as $file) {
    if (!file_exists($file)) {
        continue;
    }
    $code = file_get_contents($file);
    try {
        $stmts = $parser->parse($code);
        $traverser = new NodeTraverser();
        $visitor = new NestedIfVisitor();
        $visitor->setFile($file);
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        foreach ($visitor->nestedIfs as $item) {
            $allNested[] = $item;
        }
    } catch (\Throwable $e) {
        echo "Error parsing $file: " . $e->getMessage() . "\n";
    }
}

if (empty($allNested)) {
    echo "AST VERIFICATION PASSED: Absolutely 0 nested IF statements in any modified or created PHP file!\n";
    exit(0);
} else {
    echo "AST VERIFICATION FAILED - Found nested IF statements:\n";
    foreach ($allNested as $violation) {
        echo "  - $violation\n";
    }
    exit(1);
}
