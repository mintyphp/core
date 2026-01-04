# Research: Atomic File Write Operations in PHP for MintyPHP Debugger

**Date**: 2025-12-01  
**Context**: Filesystem storage for debugger with concurrent request handling  
**PHP Version**: 8.0+  
**Platforms**: Linux, macOS, Windows

---

## Executive Summary

**Recommended Approach**: **Write-then-rename pattern** with proper error handling.

**Rationale**:
- `rename()` is atomic on all POSIX systems (Linux, macOS) and Windows
- More reliable than `LOCK_EX` for preventing partial reads
- Better handles concurrent writes from multiple PHP processes
- Standard pattern used by production systems (Composer, package managers)

---

## 1. Write-Then-Rename Pattern

### How It Works
1. Write data to temporary file with unique name
2. Use `rename()` to atomically replace target file
3. `rename()` is atomic at filesystem level - either succeeds completely or fails completely
4. Readers never see partial data

### Atomicity Guarantees by Platform

#### Linux/Unix/macOS (POSIX)
- ✅ **`rename()` is atomic** when source and destination are on same filesystem
- POSIX standard guarantees atomicity: "If newpath exists, it will be atomically replaced"
- No intermediate state visible to other processes
- Works across NFS if on same filesystem

#### Windows
- ✅ **`rename()` is atomic** since Windows Vista/Server 2008
- Uses `MoveFileEx()` with `MOVEFILE_REPLACE_EXISTING` flag internally
- Atomic replacement guaranteed by Windows kernel
- Works on NTFS, FAT32, exFAT

### Temp File Naming Strategy

**Recommended Pattern**: `{target}.{uniqid}.tmp`

```php
$targetFile = '/path/to/data.json';
$tempFile = $targetFile . '.' . uniqid('', true) . '.tmp';
```

**Why `uniqid('', true)`**:
- Second parameter adds more entropy (microseconds)
- Includes process ID in generation
- Format: `{hex_timestamp}.{microseconds}{process_id}`
- Collision probability: ~1 in millions even with concurrent requests

**Alternative**: Use `sys_get_temp_dir()` for temp files
```php
$tempFile = tempnam(sys_get_temp_dir(), 'debugger_');
```
⚠️ **Caution**: `tempnam()` creates file in different directory, may not be atomic if cross-filesystem

### Error Handling

**Failure Scenarios**:

1. **Temp file write fails**
   - Disk full
   - Permission denied
   - Directory doesn't exist
   - **Action**: Return false, log error, don't crash app

2. **`rename()` fails**
   - Target directory doesn't exist
   - Permission denied on target
   - Cross-filesystem operation (rare in same app directory)
   - **Action**: Clean up temp file, return false, log error

3. **Cleanup failures**
   - Temp file can't be deleted
   - **Action**: Log warning but don't fail operation (orphaned files handled by cleanup)

### Implementation

```php
/**
 * Atomically write data to file using write-then-rename pattern.
 * 
 * @param string $filePath Target file path
 * @param string $data Data to write
 * @return bool True on success, false on failure
 */
function atomicWrite(string $filePath, string $data): bool
{
    // Generate unique temp file name in same directory as target
    $dir = dirname($filePath);
    $tempFile = $dir . '/' . basename($filePath) . '.' . uniqid('', true) . '.tmp';
    
    // Write data to temp file
    $bytesWritten = @file_put_contents($tempFile, $data, LOCK_EX);
    if ($bytesWritten === false) {
        // Write failed - disk full, permissions, etc.
        return false;
    }
    
    // Atomically replace target with temp file
    $renamed = @rename($tempFile, $filePath);
    if (!$renamed) {
        // Rename failed - clean up temp file
        @unlink($tempFile);
        return false;
    }
    
    return true;
}
```

**Key Points**:
- Uses `@` suppressor for errors (debugger context - must not crash app)
- Temp file in same directory ensures same filesystem (atomic rename)
- `LOCK_EX` on temp file prevents issues if multiple processes somehow get same uniqid (defense in depth)
- Returns boolean for simple error handling
- Caller decides whether to log errors

---

## 2. file_put_contents with LOCK_EX

### How It Works
```php
file_put_contents($file, $data, LOCK_EX);
```

### Limitations

❌ **LOCK_EX is NOT sufficient for atomicity**:

1. **Not atomic on write**:
   - File is truncated before data is written
   - Reader can open file during write and see empty/partial content
   - Lock only prevents simultaneous writes, not reads

2. **Platform differences**:
   - Linux: Advisory locks only (processes can ignore)
   - Windows: Mandatory locks (better, but still not atomic for readers)
   - NFS: Locks may not work reliably

3. **Concurrent reader problem**:
   ```php
   // Writer (Process A)
   file_put_contents('data.json', $json, LOCK_EX);
   // File is truncated, then data written (NOT atomic)
   
   // Reader (Process B) - concurrent
   $data = file_get_contents('data.json');
   // May read empty file or partial JSON!
   ```

### When LOCK_EX Is Sufficient

✅ **Use LOCK_EX alone when**:
- Single writer, no concurrent readers during write
- Appending to log files (with `FILE_APPEND` flag)
- Readers also use proper locking

**Example - Append to log**:
```php
// Safe for append-only
file_put_contents('debug.log', $entry, FILE_APPEND | LOCK_EX);
```

### Performance

- `LOCK_EX`: Very fast (~microseconds) but unreliable for atomic replacement
- Write-then-rename: Slightly slower (~milliseconds) but reliable atomic replacement

**Verdict**: Write-then-rename is negligible overhead for debugger use case.

---

## 3. Failure Modes & Recovery

### Disk Full

**Scenario**: Filesystem has no space left.

**Behavior**:
- `file_put_contents()` returns `false`
- Partial temp file may be created
- `rename()` typically succeeds (just updates directory entry)

**Handling**:
```php
$bytesWritten = @file_put_contents($tempFile, $data, LOCK_EX);
if ($bytesWritten === false || $bytesWritten !== strlen($data)) {
    // Disk full or write error
    @unlink($tempFile);
    return false;
}
```

### Wrong Permissions

**Scenario**: Directory is not writable, or file exists with restrictive permissions.

**Behavior**:
- `file_put_contents()` fails immediately
- `rename()` fails if target directory not writable

**Handling**:
```php
if (!is_writable($dir)) {
    // Cannot write to directory
    return false;
}

// Attempt write...
```

**Prevention**: Create directories with proper permissions (0755 or 0777).

### Directory Doesn't Exist

**Scenario**: Parent directory structure missing.

**Behavior**:
- `file_put_contents()` fails with "No such file or directory"

**Handling**: Create directory before writing (see section 5).

### Orphaned Temp Files

**Scenario**: Process dies between write and rename, leaving `.tmp` files.

**Cleanup Strategy**:

1. **Periodic cleanup**: Delete `.tmp` files older than threshold
   ```php
   function cleanupOrphanedTempFiles(string $dir, int $maxAgeSeconds = 3600): void
   {
       $pattern = $dir . '/*.tmp';
       foreach (glob($pattern) as $file) {
           if (time() - filemtime($file) > $maxAgeSeconds) {
               @unlink($file);
           }
       }
   }
   ```

2. **On-demand cleanup**: Clean up at startup or before each write
   ```php
   // Clean old temp files before writing
   cleanupOrphanedTempFiles($debugDir, 3600); // 1 hour old
   ```

3. **Ignore**: Temp files are harmless and will be overwritten
   - Recommended for debugger: minimize overhead, periodic cleanup sufficient

---

## 4. Best Practices for Debugger Context

### Recommended Approach

✅ **Use write-then-rename pattern**:
- Most reliable atomic replacement
- Works across all platforms
- Industry standard (Composer, npm, etc.)

### Error Handling Strategy

**Principle**: Debugger MUST NOT crash application.

```php
function atomicWrite(string $filePath, string $data): bool
{
    try {
        $dir = dirname($filePath);
        $tempFile = $dir . '/' . basename($filePath) . '.' . uniqid('', true) . '.tmp';
        
        $bytesWritten = @file_put_contents($tempFile, $data, LOCK_EX);
        if ($bytesWritten === false) {
            return false;
        }
        
        if (!@rename($tempFile, $filePath)) {
            @unlink($tempFile);
            return false;
        }
        
        return true;
    } catch (\Throwable $e) {
        // Catch ANY error - debugger must never crash app
        return false;
    }
}
```

**Logging Strategy**:
- Return `false` on error
- Caller can optionally log (but should not throw)
- Silent failure is acceptable for debugger

### Graceful Degradation

```php
public function saveRequest(string $uuid, array $data): void
{
    $filePath = $this->getRequestFilePath($uuid);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if (!$this->atomicWrite($filePath, $json)) {
        // Failed to save - degrade gracefully
        // Option 1: Store in memory (fallback)
        $this->inMemoryFallback[$uuid] = $data;
        
        // Option 2: Just skip (acceptable for debugger)
        return;
    }
}
```

---

## 5. Directory Creation

### Safe Recursive Directory Creation

**Problem**: Multiple processes may try to create same directory simultaneously.

**Solution**: Use `mkdir()` with recursive flag and check if directory exists.

```php
/**
 * Safely create directory with recursive flag.
 * Handles race condition where another process creates directory concurrently.
 * 
 * @param string $path Directory path to create
 * @param int $permissions Directory permissions (default: 0755)
 * @return bool True on success or if directory already exists
 */
function ensureDirectoryExists(string $path, int $permissions = 0755): bool
{
    // Already exists - success
    if (is_dir($path)) {
        return true;
    }
    
    // Try to create directory recursively
    $created = @mkdir($path, $permissions, true);
    
    // Success
    if ($created) {
        return true;
    }
    
    // Failed, but check if another process created it (race condition)
    if (is_dir($path)) {
        return true;
    }
    
    // Actually failed
    return false;
}
```

### Race Condition Handling

**Scenario**: Two processes call `mkdir()` simultaneously.

**Behavior**:
- First process: `mkdir()` returns `true`
- Second process: `mkdir()` returns `false` with "File exists" error

**Solution**: Check `is_dir()` after failed `mkdir()`:
- If directory exists, treat as success (another process created it)
- Otherwise, actual failure

### Permissions

**Recommended**:
- `0755` (rwxr-xr-x): Owner can write, others can read/execute
- `0777` (rwxrwxrwx): Everyone can write (use with caution, may be needed for shared hosting)

**Umask consideration**:
- `mkdir()` permissions are affected by umask
- `0755` with umask `022` results in `0755`
- `0777` with umask `022` results in `0755`

**Platform differences**:
- Unix/Linux: Permissions work as expected
- Windows: Permissions are largely ignored (all files world-writable by default)

---

## 6. Complete Implementation Example

### Atomic Write Function

```php
<?php

namespace MintyPHP\Core;

class DebuggerFileSystem
{
    /**
     * Atomically write data to file using write-then-rename pattern.
     * 
     * This method ensures that concurrent readers never see partial data.
     * If the write fails for any reason, the original file (if it exists)
     * remains unchanged.
     * 
     * @param string $filePath Target file path
     * @param string $data Data to write
     * @return bool True on success, false on failure
     */
    public static function atomicWrite(string $filePath, string $data): bool
    {
        try {
            $dir = dirname($filePath);
            
            // Ensure directory exists
            if (!self::ensureDirectoryExists($dir)) {
                return false;
            }
            
            // Generate unique temp file in same directory as target
            // Same directory ensures same filesystem (atomic rename)
            $tempFile = $dir . '/' . basename($filePath) . '.' . uniqid('', true) . '.tmp';
            
            // Write data to temp file with exclusive lock
            $bytesWritten = @file_put_contents($tempFile, $data, LOCK_EX);
            
            // Check for write failure
            if ($bytesWritten === false) {
                return false;
            }
            
            // Verify all data was written (disk full check)
            if ($bytesWritten !== strlen($data)) {
                @unlink($tempFile);
                return false;
            }
            
            // Atomically replace target with temp file
            $renamed = @rename($tempFile, $filePath);
            
            if (!$renamed) {
                // Rename failed - clean up temp file
                @unlink($tempFile);
                return false;
            }
            
            return true;
            
        } catch (\Throwable $e) {
            // Catch ANY error - debugger must never crash application
            // Clean up temp file if it exists
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            return false;
        }
    }
    
    /**
     * Safely create directory with recursive flag.
     * 
     * Handles race condition where multiple processes may try to create
     * the same directory simultaneously.
     * 
     * @param string $path Directory path to create
     * @param int $permissions Directory permissions (default: 0755)
     * @return bool True on success or if directory already exists
     */
    public static function ensureDirectoryExists(string $path, int $permissions = 0755): bool
    {
        try {
            // Already exists - success
            if (is_dir($path)) {
                return true;
            }
            
            // Try to create directory recursively
            $created = @mkdir($path, $permissions, true);
            
            // Success
            if ($created) {
                return true;
            }
            
            // Failed, but check if another process created it (race condition)
            if (is_dir($path)) {
                return true;
            }
            
            // Actually failed
            return false;
            
        } catch (\Throwable $e) {
            // Catch any error - return false
            return false;
        }
    }
    
    /**
     * Safely read file contents.
     * 
     * @param string $filePath File path to read
     * @return string|null File contents or null on failure
     */
    public static function read(string $filePath): ?string
    {
        try {
            if (!file_exists($filePath)) {
                return null;
            }
            
            $contents = @file_get_contents($filePath);
            return $contents !== false ? $contents : null;
            
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Safely delete file.
     * 
     * @param string $filePath File path to delete
     * @return bool True on success or if file doesn't exist
     */
    public static function delete(string $filePath): bool
    {
        try {
            if (!file_exists($filePath)) {
                return true;
            }
            
            return @unlink($filePath);
            
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * Clean up orphaned temporary files.
     * 
     * Removes .tmp files older than specified age.
     * Safe to call periodically.
     * 
     * @param string $directory Directory to clean
     * @param int $maxAgeSeconds Maximum age of temp files (default: 1 hour)
     * @return int Number of files deleted
     */
    public static function cleanupOrphanedTempFiles(string $directory, int $maxAgeSeconds = 3600): int
    {
        try {
            if (!is_dir($directory)) {
                return 0;
            }
            
            $deleted = 0;
            $pattern = rtrim($directory, '/') . '/*.tmp';
            
            foreach (glob($pattern) as $file) {
                if (time() - filemtime($file) > $maxAgeSeconds) {
                    if (@unlink($file)) {
                        $deleted++;
                    }
                }
            }
            
            return $deleted;
            
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
```

### Usage Example

```php
<?php

namespace MintyPHP\Core;

class Debugger
{
    private static string $storagePath = '/tmp/debugger';
    
    /**
     * Save request data atomically.
     */
    private static function saveRequestData(string $uuid, array $data): bool
    {
        $filePath = self::$storagePath . '/requests/' . $uuid . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return DebuggerFileSystem::atomicWrite($filePath, $json);
    }
    
    /**
     * Update history file atomically.
     */
    private static function updateHistory(string $sessionId, string $uuid): bool
    {
        $historyFile = self::$storagePath . '/session-' . $sessionId . '.json';
        
        // Read existing history
        $historyJson = DebuggerFileSystem::read($historyFile);
        $history = $historyJson ? json_decode($historyJson, true) : [];
        
        // Add new UUID at front
        array_unshift($history, $uuid);
        
        // Limit history size
        $history = array_slice($history, 0, 10);
        
        // Write atomically
        $json = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return DebuggerFileSystem::atomicWrite($historyFile, $json);
    }
    
    /**
     * Periodic cleanup of old temp files.
     */
    public static function cleanup(): void
    {
        DebuggerFileSystem::cleanupOrphanedTempFiles(
            self::$storagePath . '/requests',
            3600 // 1 hour
        );
    }
}
```

---

## 7. Comparison Matrix

| Aspect | Write-then-Rename | LOCK_EX Only |
|--------|------------------|--------------|
| **Atomicity** | ✅ Guaranteed | ❌ Not atomic for readers |
| **Concurrent Readers** | ✅ Never see partial data | ❌ May see empty/partial file |
| **Concurrent Writers** | ✅ Serialized by filesystem | ✅ Serialized by lock |
| **Cross-Platform** | ✅ Linux, macOS, Windows | ⚠️ Platform-dependent behavior |
| **NFS Support** | ✅ Works (same filesystem) | ❌ Locks may not work |
| **Performance** | ~1-2ms per write | ~0.5ms per write |
| **Complexity** | Medium (temp file + rename) | Low (single call) |
| **Orphaned Files** | ⚠️ Possible (cleanup needed) | ✅ No temp files |
| **Industry Standard** | ✅ Composer, npm, etc. | ❌ Not used for atomic replacement |
| **Recommendation** | ✅ **USE THIS** | ❌ Not sufficient |

---

## 8. Testing Recommendations

### Test Cases

1. **Concurrent Writes**: Simulate 10+ concurrent PHP processes writing to same file
2. **Concurrent Read During Write**: Reader should never see partial JSON
3. **Disk Full**: Should return `false`, not crash
4. **Permission Denied**: Should return `false`, not crash
5. **Missing Directory**: Should create directory automatically
6. **Race Condition**: Two processes create same directory simultaneously
7. **Large Files**: Write 1MB+ JSON to verify no partial writes
8. **Cross-Platform**: Test on Linux and Windows (if available)

### Stress Test Script

```php
<?php
// Spawn multiple concurrent processes
for ($i = 0; $i < 10; $i++) {
    $pid = pcntl_fork();
    if ($pid == 0) {
        // Child process
        $data = json_encode(['request' => $i, 'time' => microtime(true)]);
        DebuggerFileSystem::atomicWrite('/tmp/test.json', $data);
        exit(0);
    }
}

// Wait for all children
while (pcntl_waitpid(0, $status) != -1);

// Verify file is valid JSON (not corrupted)
$contents = file_get_contents('/tmp/test.json');
$decoded = json_decode($contents, true);
assert($decoded !== null, 'File corrupted!');
```

---

## 9. Recommended Solution Summary

### For MintyPHP Debugger

1. **Use `DebuggerFileSystem::atomicWrite()`** for all file writes
   - Implements write-then-rename pattern
   - Handles all error cases gracefully
   - Returns boolean for simple error handling

2. **Use `DebuggerFileSystem::ensureDirectoryExists()`** before writing
   - Creates nested directories safely
   - Handles concurrent directory creation
   - Returns boolean for simple error handling

3. **Error Handling Strategy**:
   - All methods return `bool` (success/failure)
   - No exceptions thrown (debugger must not crash app)
   - Silent failure is acceptable
   - Optional: Log errors for debugging, but don't expose to user

4. **Cleanup Strategy**:
   - Call `cleanupOrphanedTempFiles()` periodically (optional)
   - Set max age to 1 hour (3600 seconds)
   - Run on first request of each session, or via cron

5. **Directory Structure**:
   ```
   /tmp/
   ├── requests/
   │   ├── 550e8400-e29b-41d4-a716-446655440000.json
   │   ├── 660e8400-e29b-41d4-a716-446655440001.json
   │   └── *.tmp (orphaned temp files)
   ├── session-abc123.json
   └── session-def456.json
   ```

---

## 10. References & Further Reading

- **POSIX `rename()` specification**: Guarantees atomicity on same filesystem
- **PHP Manual - `rename()`**: https://www.php.net/manual/en/function.rename.php
- **PHP Manual - `file_put_contents()`**: https://www.php.net/manual/en/function.file-put-contents.php
- **Composer's atomic write implementation**: Uses write-then-rename pattern
- **Linux `rename(2)` man page**: "If newpath already exists, it will be atomically replaced"
- **Windows `MoveFileEx()` documentation**: Atomic replacement with `MOVEFILE_REPLACE_EXISTING`

---

## Appendix: Alternative Approaches (Not Recommended)

### Using `flock()` Directly

```php
$fp = fopen($file, 'w');
flock($fp, LOCK_EX);
fwrite($fp, $data);
flock($fp, LOCK_UN);
fclose($fp);
```

**Issues**:
- File is truncated on open (readers see empty file)
- More verbose than write-then-rename
- No advantage over `file_put_contents(..., LOCK_EX)`

### Using Database

**Pros**:
- Transactions provide atomicity
- Built-in concurrency handling

**Cons**:
- Requires database setup
- Overkill for debugger
- Performance overhead
- Not suitable for "filesystem storage" requirement

### Using Serialized PHP

```php
file_put_contents($file, serialize($data));
```

**Issues**:
- Less portable than JSON
- Not human-readable
- Still needs atomic write pattern
