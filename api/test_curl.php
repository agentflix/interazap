<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get user and create token
$user = App\Models\User::first();
if (! $user) {
    echo 'No users found'."\n";
    exit(1);
}

echo "User: {$user->email}"."\n";
echo "Tenant: {$user->tenant_id}"."\n";

$token = $user->createToken('curl-test')->plainTextToken;
echo "Token: {$token}"."\n";

// Get a recent ticket with an instance
$ticket = Domain\Chat\Models\ChatTicket::whereNotNull('instance_id')
    ->whereNotNull('phone')
    ->latest()
    ->first();

if ($ticket) {
    echo "Ticket ID: {$ticket->id}"."\n";
    echo "Phone: {$ticket->phone}"."\n";
    echo "Instance ID: {$ticket->instance_id}"."\n";

    $instance = $ticket->instance;
    if ($instance) {
        echo "Provider: {$instance->provider}"."\n";
        echo 'Instance Token: '.($instance->webhook_token ? 'SET' : 'NOT SET')."\n";
    }
} else {
    echo 'No tickets found'."\n";
}

// Check failed messages
$failedMessages = Domain\Chat\Models\ChatMessage::where('status', 'failed')
    ->where('direction', 'outgoing')
    ->latest()
    ->take(5)
    ->get(['id', 'status', 'error_message', 'type', 'file_url', 'created_at']);

echo "\n--- Last 5 failed outgoing messages ---\n";
foreach ($failedMessages as $msg) {
    echo "ID: {$msg->id} | Type: {$msg->type} | Error: {$msg->error_message} | Created: {$msg->created_at}"."\n";
}

// Check pending/sent media messages
$mediaMessages = Domain\Chat\Models\ChatMessage::whereIn('type', ['file', 'image', 'video', 'document', 'audio'])
    ->where('direction', 'outgoing')
    ->latest()
    ->take(5)
    ->get(['id', 'status', 'type', 'file_url', 'error_message', 'created_at']);

echo "\n--- Last 5 outgoing media messages ---\n";
foreach ($mediaMessages as $msg) {
    echo "ID: {$msg->id} | Type: {$msg->type} | Status: {$msg->status} | Error: {$msg->error_message} | URL: ".substr((string) $msg->file_url, 0, 80)." | Created: {$msg->created_at}"."\n";
}

// Check incoming media messages without local files
$incomingMedia = Domain\Chat\Models\ChatMessage::whereIn('type', ['file', 'image', 'video', 'document', 'audio'])
    ->where('direction', 'incoming')
    ->latest()
    ->take(5)
    ->get(['id', 'status', 'type', 'file_url', 'mime_type', 'created_at']);

echo "\n--- Last 5 incoming media messages ---\n";
foreach ($incomingMedia as $msg) {
    $isLocal = $msg->file_url && ! str_contains((string) $msg->file_url, 'http');
    echo "ID: {$msg->id} | Type: {$msg->type} | MIME: {$msg->mime_type} | Local: ".($isLocal ? 'YES' : 'NO').' | URL: '.substr((string) $msg->file_url, 0, 100)." | Created: {$msg->created_at}"."\n";
}

// Check failed jobs
echo "\n--- Failed jobs (last 5) ---\n";
$failedJobs = DB::table('failed_jobs')->latest()->take(5)->get(['uuid', 'payload', 'exception', 'failed_at']);
foreach ($failedJobs as $job) {
    $payload = json_decode($job->payload, true);
    $jobClass = $payload['displayName'] ?? 'unknown';
    $errorLines = explode("\n", $job->exception);
    $firstError = $errorLines[0] ?? 'unknown';
    echo "Job: {$jobClass} | Failed: {$job->failed_at} | Error: ".substr($firstError, 0, 120)."\n";
}

// Check Horizon pending jobs
echo "\n--- Horizon queue sizes ---\n";
$queues = ['default', 'media', 'ai', 'sentiment'];
foreach ($queues as $q) {
    $size = Illuminate\Support\Facades\Queue::size($q);
    echo "Queue '{$q}': {$size} pending jobs"."\n";
}
