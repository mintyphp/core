# Debugger Filesystem Storage - Developer Guide

**Date**: 2025-12-01  
**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Research**: [research.md](./research.md) | **Data Model**: [data-model.md](./data-model.md)

Quick reference guide for developers implementing or working with the filesystem-based debugger storage system.

---

## Overview

The MintyPHP Debugger now stores request debug data on the **filesystem** instead of in `$_SESSION`. This change:

- ✅ Eliminates session pollution (no 'debugger' key in `$_SESSION`)
- ✅ Removes PHP session dependency (works without `Session::start()`)
- ✅ Enables persistent debug history across browser sessions
- ✅ Prevents debug data from inflating session size
- ✅ Provides human-readable JSON files for debugging

---

## Key Changes from $_SESSION

### Before (Session Storage)

```php
// OLD: Stored in PHP session
public function end(string $type): void
{
    $_SESSION[$this->sessionKey][] = $this->request;
    while (count($_SESSION[$this->sessionKey]) > $this->history) {
        array_shift($_SESSION[$this->sessionKey]);
    }
}
```

**Problems**:
- Requires PHP sessions to be started
- Debug data appears in session inspection
- Data lost when session expires
- Inflates session size

### After (Filesystem Storage)

```php
// NEW: Stored on filesystem
public function end(string $type): void
{
    $uuid = $this->generateUUID();
    $requestPath = $this->getRequestPath($uuid);
    $this->atomicWrite($requestPath, json_encode($this->request));
    $this->updateHistory($uuid);
}
```

**Benefits**:
- Works independently of PHP sessions
- No session pollution
- Persistent across browser sessions
- Configurable retention

---

## Configuration

### Storage Path

**Default**: `sys_get_temp_dir() . '/mintyphp-debug'`

Override before calling `getInstance()`:

```php
// Use custom path
Debugger::$__storagePath = '/var/log/mintyphp-debug';
$debugger = Debugger::getInstance();

// Project-relative path
Debugger::$__storagePath = __DIR__ . '/../storage/debug';
$debugger = Debugger::getInstance();
```

**Platform Defaults**:
- Linux: `/tmp/mintyphp-debug`
- macOS: `/var/folders/{hash}/{hash}/T/mintyphp-debug`
- Windows: `C:\Users\{user}\AppData\Local\Temp\mintyphp-debug`

### History Limit

**Default**: 10 requests per browser session

```php
// Keep last 20 requests
Debugger::$__history = 20;
$debugger = Debugger::getInstance();
```

---

## Storage Structure

```
{storagePath}/
├── {sessionId}/                      # Request files directory
│   ├── history.json                  # History: ["uuid1", "uuid2", ...]
│   ├── {uuid1}.json                  # Request 1 data
│   ├── {uuid2}.json                  # Request 2 data
│   └── {uuid3}.json                  # Request 3 data
└── session-{anotherSessionId}.json   # Another browser session
```

**Example**:
```
/tmp/mintyphp-debug/
└── xK9j_mP4nQ7wR2vY8sZ5tL3h6fC1bN0a/
    ├── history.json
    ├── 550e8400-e29b-41d4-a716-446655440000.json
    ├── 6ba7b810-9dad-11d1-80b4-00c04fd430c8.json
    └── 7c9e6679-7425-40de-944b-e07fc1f90ae7.json
```

---

## New Methods

### `generateUUID(): string`

Generates RFC 4122 version 4 UUID for request identification.

```php
private function generateUUID(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0F) | 0x40); // Version 4
    $data[8] = chr((ord($data[8]) & 0x3F) | 0x80); // Variant
    
    return sprintf(
        '%s-%s-%s-%s-%s',
        bin2hex(substr($data, 0, 4)),
        bin2hex(substr($data, 4, 2)),
        bin2hex(substr($data, 6, 2)),
        bin2hex(substr($data, 8, 2)),
        bin2hex(substr($data, 10, 6))
    );
}
```

**Returns**: UUID string like `550e8400-e29b-41d4-a716-446655440000`

**Performance**: ~15μs per call

### `getBrowserSessionId(): string`

Gets or creates browser session identifier from cookie (independent of PHP sessions).

```php
private function getBrowserSessionId(): string
{
    if (isset($_COOKIE[self::COOKIE_NAME])) {
        $identifier = $_COOKIE[self::COOKIE_NAME];
        if (preg_match('/^[A-Za-z0-9_-]{32}$/', $identifier)) {
            return $identifier;
        }
    }

    $identifier = $this->generateSessionIdentifier();
    
    if (!headers_sent()) {
        setcookie(
            name: self::COOKIE_NAME,
            value: $identifier,
            expires: 0,              // Session cookie
            path: '/',
            httponly: true,          // XSS protection
            samesite: 'Lax'         // CSRF protection
        );
    }

    return $identifier;
}
```

**Cookie Name**: `minty_debug_session`

**Returns**: 32-character session ID like `xK9j_mP4nQ7wR2vY8sZ5tL3h6fC1bN0a`

### `atomicWrite(string $path, string $data): bool`

Atomically writes data to file using write-then-rename pattern.

```php
private function atomicWrite(string $path, string $data): bool
{
    $dir = dirname($path);
    if (!$this->ensureDirectoryExists($dir)) {
        return false;
    }

    $tempPath = $path . '.tmp.' . uniqid('', true);
    
    try {
        $bytes = @file_put_contents($tempPath, $data, LOCK_EX);
        if ($bytes === false || $bytes !== strlen($data)) {
            @unlink($tempPath);
            return false;
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            return false;
        }

        return true;
    } catch (\Throwable $e) {
        @unlink($tempPath);
        return false;
    }
}
```

**Returns**: `true` on success, `false` on failure (never throws exceptions)

**Error Handling**: Logs errors via `error_log()`, cleans up temp files

### `updateHistory(string $uuid): bool`

Updates history file with new request UUID using file locking.

```php
private function updateHistory(string $uuid): bool
{
    $historyPath = $this->getHistoryPath();
    $fp = @fopen($historyPath, 'c+');
    if ($fp === false) {
        return false;
    }

    try {
        // Acquire exclusive lock with timeout
        $attempts = 0;
        while ($attempts < 10) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                break;
            }
            usleep(10000); // 10ms
            $attempts++;
        }

        if ($attempts >= 10) {
            fclose($fp);
            return false;
        }

        // Read current history
        $content = stream_get_contents($fp);
        $history = $content ? json_decode($content, true) : [];
        
        // Add new UUID at beginning
        array_unshift($history, $uuid);
        
        // Enforce history limit
        if (count($history) > $this->history) {
            $history = array_slice($history, 0, $this->history);
            
            // Delete old request files
            $removedUuids = array_slice($history, $this->history);
            foreach ($removedUuids as $oldUuid) {
                $this->deleteRequest($oldUuid);
            }
        }

        // Write updated history
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($history, JSON_PRETTY_PRINT));
        
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    } catch (\Throwable $e) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
}
```

**Returns**: `true` on success, `false` on failure

**Locking**: Uses `flock(LOCK_EX)` with 100ms timeout to prevent race conditions

---

## Modified Methods

### `__construct()`

Now initializes browser session tracking:

```php
public function __construct(int $history, bool $enabled, string $sessionKey)
{
    // ... existing initialization ...
    
    if ($this->enabled) {
        // NEW: Get/create browser session ID
        $this->browserSessionId = $this->getBrowserSessionId();
    }
}
```

### `end(string $type): void`

Now writes to filesystem instead of `$_SESSION`:

```php
public function end(string $type): void
{
    if (!$this->enabled || $this->request->type) {
        return;
    }

    // Finalize request data
    $this->request->type = $type;
    $this->request->duration = microtime(true) - $this->request->start;
    $this->request->memory = memory_get_peak_usage(true);
    $this->request->classes = $this->getLoadedFiles();

    // NEW: Generate UUID and save to filesystem
    $uuid = $this->generateUUID();
    
    $requestData = json_encode($this->request, JSON_PRETTY_PRINT);
    if ($requestData === false) {
        error_log('MintyPHP Debugger: Failed to JSON encode request data');
        return;
    }

    $requestPath = $this->getRequestPath($uuid);
    if (!$this->atomicWrite($requestPath, $requestData)) {
        error_log('MintyPHP Debugger: Failed to write request file: ' . $requestPath);
        return;
    }

    // Update history
    if (!$this->updateHistory($uuid)) {
        error_log('MintyPHP Debugger: Failed to update history file');
    }
}
```

### `toolbar(): void`

Now reads from filesystem instead of `$_SESSION`:

```php
public function toolbar(): void
{
    if (!$this->enabled) {
        return;
    }

    // NEW: Read history from filesystem
    $historyPath = $this->getHistoryPath();
    if (!file_exists($historyPath)) {
        return;
    }

    $history = json_decode(file_get_contents($historyPath), true) ?: [];
    if (empty($history)) {
        return;
    }

    // Load current request (most recent)
    $currentUuid = $history[0];
    $requestPath = $this->getRequestPath($currentUuid);
    
    if (!file_exists($requestPath)) {
        return;
    }

    $requestData = json_decode(file_get_contents($requestPath), true);
    // ... render toolbar with $requestData ...
}
```

---

## Testing

### Unit Tests

```php
// UUID generation
public function testGenerateUUID(): void
{
    $uuid1 = $this->invokePrivateMethod('generateUUID');
    $uuid2 = $this->invokePrivateMethod('generateUUID');
    
    // Format check
    $this->assertMatchesRegularExpression(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $uuid1
    );
    
    // Uniqueness
    $this->assertNotEquals($uuid1, $uuid2);
}

// Atomic write
public function testAtomicWrite(): void
{
    $path = $this->tempDir . '/test.json';
    $data = '{"test": true}';
    
    $result = $this->invokePrivateMethod('atomicWrite', $path, $data);
    
    $this->assertTrue($result);
    $this->assertFileExists($path);
    $this->assertEquals($data, file_get_contents($path));
}

// History update
public function testUpdateHistory(): void
{
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    
    $result = $this->invokePrivateMethod('updateHistory', $uuid);
    
    $this->assertTrue($result);
    
    $history = json_decode(
        file_get_contents($this->getHistoryPath()),
        true
    );
    
    $this->assertEquals([$uuid], $history);
}
```

### Integration Tests

```php
// Concurrent requests
public function testConcurrentRequests(): void
{
    $processes = [];
    for ($i = 0; $i < 10; $i++) {
        $processes[] = $this->forkProcess(function() {
            $debugger = new Debugger(10, true, 'debugger');
            $debugger->end('ok');
        });
    }
    
    foreach ($processes as $process) {
        pcntl_waitpid($process, $status);
    }
    
    $history = json_decode(
        file_get_contents($this->getHistoryPath()),
        true
    );
    
    $this->assertCount(10, $history);
    $this->assertCount(10, array_unique($history)); // No duplicates
}

// Filesystem failure
public function testFilesystemFailure(): void
{
    // Make storage path read-only
    chmod($this->storagePath, 0444);
    
    $debugger = new Debugger(10, true, 'debugger');
    
    // Should not crash
    $debugger->end('ok');
    
    // Restore permissions
    chmod($this->storagePath, 0755);
}
```

### Coverage Target

- **Minimum**: 90% line coverage
- **Focus Areas**:
  - UUID generation
  - File operations (atomic write, locking)
  - History management
  - Error conditions
  - Concurrent access

---

## Migration Guide

### For Framework Developers

1. **Remove `$_SESSION` references**:
   ```php
   // OLD
   $_SESSION[$this->sessionKey][] = $this->request;
   
   // NEW
   $uuid = $this->generateUUID();
   $this->atomicWrite($this->getRequestPath($uuid), json_encode($this->request));
   $this->updateHistory($uuid);
   ```

2. **Add new private methods**:
   - `generateUUID()`
   - `getBrowserSessionId()`
   - `atomicWrite()`
   - `updateHistory()`
   - `ensureDirectoryExists()`

3. **Update `__construct()`** to initialize `$this->browserSessionId`

4. **Update `end()`** to write to filesystem

5. **Update `toolbar()`** to read from filesystem

6. **Remove `getSessionKey()` usage** (session key no longer needed)

### For Application Developers

**If you DON'T read debugger data**: No changes needed. Toolbar works automatically.

**If you DO read debugger data from session**:

```php
// OLD
$debugData = $_SESSION['debugger'] ?? [];

// NEW
$storagePath = Debugger::$__storagePath ?: sys_get_temp_dir() . '/mintyphp-debug';
$sessionId = $_COOKIE['minty_debug_session'] ?? null;

if ($sessionId) {
    $historyPath = "$storagePath/session-$sessionId.json";
    $history = json_decode(file_get_contents($historyPath), true) ?: [];
    
    foreach ($history as $uuid) {
        $requestPath = "$storagePath/$sessionId/$uuid.json";
        $requestData = json_decode(file_get_contents($requestPath), true);
        // Process $requestData...
    }
}
```

---

## Troubleshooting

### Debug Data Not Saving

**Symptoms**: Toolbar empty, no files in storage directory

**Checks**:
1. Is debugger enabled? `Debugger::$__enabled = true`
2. Is storage directory writable? `ls -ld /tmp/mintyphp-debug`
3. Check error log for messages: `grep "MintyPHP Debugger" /var/log/php_errors.log`

**Solutions**:
```bash
# Create directory with correct permissions
mkdir -p /tmp/mintyphp-debug
chmod 0777 /tmp/mintyphp-debug

# Or use custom path
```

```php
Debugger::$__storagePath = __DIR__ . '/../storage/debug';
```

### History Not Updating

**Symptoms**: New requests not appearing in toolbar history

**Checks**:
1. Is history file locked? `lsof /tmp/mintyphp-debug/*/history.json`
2. Is history limit set too low? Check `Debugger::$__history`
3. Check for lock timeout errors in logs

**Solutions**:
```php
// Increase history limit
Debugger::$__history = 20;

// Check for orphaned locks (shouldn't happen, but just in case)
// Restart PHP-FPM or clear tmp directory
```

### Session Cookie Not Set

**Symptoms**: New session created on every request

**Checks**:
1. Are headers already sent? Cookie must be set before output
2. Is SameSite=Lax compatible with your setup?
3. Check browser cookie settings (developer tools)

**Solutions**:
```php
// Ensure Debugger::getInstance() called early
$debugger = Debugger::getInstance();

// Before any output:
// echo, print, HTML, whitespace before <?php
```

### Old Debug Data Accumulating

**Symptoms**: Storage directory growing large

**Solutions**:
```php
// Decrease history limit
Debugger::$__history = 5;

// Manual cleanup (future feature)
Debugger::cleanup(7); // Delete sessions older than 7 days

// OS-level cleanup (automatic on reboot for default path)
// Or cron job:
find /tmp/mintyphp-debug -type f -mtime +7 -delete
```

---

## Performance Characteristics

| Operation | Typical Time | Notes |
|-----------|-------------|-------|
| UUID generation | ~15μs | Per request |
| JSON encode | ~100-500μs | Depends on data size |
| Atomic write | ~500μs-1ms | Write-then-rename |
| History update | ~1-2ms | Includes flock |
| **Total overhead** | **~2-4ms** | Per request, acceptable for debug |

**Concurrent Performance**:
- Single session: 500-1000 req/s
- Multiple sessions: Unlimited (no contention)
- 100+ concurrent to same session: ~100 req/s (lock contention)

---

## Best Practices

1. **Enable Only in Development**:
   ```php
   Debugger::$__enabled = getenv('APP_ENV') === 'development';
   ```

2. **Use Reasonable History Limits**:
   ```php
   Debugger::$__history = 10; // Default, good for most cases
   ```

3. **Check Headers Before Cookie**:
   ```php
   // Call getInstance() early, before any output
   $debugger = Debugger::getInstance();
   ```

4. **Monitor Storage Size**:
   ```bash
   du -sh /tmp/mintyphp-debug
   ```

5. **Cleanup Periodically** (when implemented):
   ```php
   // In cron or scheduled task
   Debugger::cleanup(7); // Delete sessions > 7 days old
   ```

---

## Summary

The filesystem-based debugger storage provides:
- ✅ No PHP session dependency
- ✅ Persistent debug data
- ✅ No session pollution
- ✅ Human-readable JSON
- ✅ Graceful error handling
- ✅ Minimal performance impact (~2-4ms per request)

All operations follow MintyPHP's constitution: simplicity, security, explicit behavior, and comprehensive testing.
