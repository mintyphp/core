# Research: Default Storage Location and Configuration Strategy

**Date**: 2025-12-01  
**Feature**: 001-debugger-filesystem  
**Focus**: Storage location, configuration pattern, directory management

## Executive Summary

**Recommended Approach**: Use `sys_get_temp_dir() + '/mintyphp-debug'` as default with configurable override via `Debugger::$__storagePath`.

**Key Benefits**:
- Works cross-platform out of the box
- Zero configuration required for most developers
- Automatic cleanup by OS on most systems
- No permission issues (temp dir is writable by design)
- Follows PHP ecosystem conventions

---

## 1. Default Path Options Analysis

### Option A: `sys_get_temp_dir() + '/mintyphp-debug'` ✅ RECOMMENDED

**Path Examples**:
- Linux: `/tmp/mintyphp-debug`
- macOS: `/var/folders/xh/4d8y2wv50ld2r8nqfz9j8y4c0000gn/T/mintyphp-debug`
- Windows: `C:\Users\username\AppData\Local\Temp\mintyphp-debug`

**Pros**:
- ✅ Cross-platform compatible by design
- ✅ Always writable (temp directory guaranteed to be writable)
- ✅ OS handles cleanup on most systems (Linux/macOS clear /tmp on reboot)
- ✅ Standard PHP convention (`sys_get_temp_dir()` exists for this purpose)
- ✅ No permission issues
- ✅ Works in containerized environments
- ✅ No git tracking concerns (external to project)

**Cons**:
- ❌ May be cleared on reboot (actually a feature for ephemeral debug data)
- ❌ Not easily discoverable for manual inspection
- ❌ Shared across multiple projects (mitigated by project-specific subdirs if needed)

**Use Cases**:
- Default for 95% of development scenarios
- CI/CD environments
- Docker containers
- Shared hosting (where project directory may be read-only)

---

### Option B: Project-relative `.cache/debug` or `storage/debug`

**Path Examples**:
- `/home/maurits/projects/mintyphp/core/.cache/debug`
- `/var/www/myapp/storage/debug`

**Pros**:
- ✅ Easy to discover and manually inspect
- ✅ Project-isolated (no collision with other projects)
- ✅ Survives reboots
- ✅ Can be version controlled (if needed, though not recommended)

**Cons**:
- ❌ Requires project directory to be writable
- ❌ Permission issues in production-like environments
- ❌ Must add to `.gitignore`
- ❌ Doesn't work if project is on read-only mount
- ❌ Not cross-platform without path separator handling
- ❌ Different PHP projects use different conventions (.cache vs storage vs var)

**Use Cases**:
- Projects with existing cache/storage directory conventions
- Long-term debug data retention needs
- When developers want to inspect data between reboots

---

### Option C: User home directory `~/.mintyphp/debug`

**Path Examples**:
- Linux: `/home/username/.mintyphp/debug`
- macOS: `/Users/username/.mintyphp/debug`
- Windows: `C:\Users\username\.mintyphp\debug`

**Pros**:
- ✅ User-specific (no permission issues)
- ✅ Persists across reboots
- ✅ Project-independent
- ✅ Good for CLI tools and global utilities

**Cons**:
- ❌ `$_SERVER['HOME']` or `getenv('HOME')` not always available in web context
- ❌ Windows requires `USERPROFILE` instead of `HOME`
- ❌ Shared across projects (potential collision/confusion)
- ❌ Harder to clean up (user may not know to look there)
- ❌ Overkill for ephemeral debug data

**Use Cases**:
- CLI tools
- Global development utilities
- User preferences/configuration (not temporary debug data)

---

## 2. Directory Creation Strategy

### Recommended Approach: **Lazy Creation on First Write**

```php
class Debugger
{
    public static string $__storagePath = '';  // Empty = use default
    
    private ?string $resolvedStoragePath = null;
    
    /**
     * Get the storage path, creating directory if needed
     */
    private function getStoragePath(): string
    {
        if ($this->resolvedStoragePath !== null) {
            return $this->resolvedStoragePath;
        }
        
        // Use configured path or default to temp dir
        $basePath = static::$__storagePath;
        if ($basePath === '') {
            $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mintyphp-debug';
        }
        
        // Create directory if it doesn't exist
        if (!is_dir($basePath)) {
            // Create with 0700 permissions (owner only - contains debug data)
            if (!@mkdir($basePath, 0700, true)) {
                // Fallback: try without explicit permissions
                if (!@mkdir($basePath, 0777, true)) {
                    error_log("MintyPHP Debugger: Failed to create storage directory: $basePath");
                    // Return null path - caller must handle gracefully
                    $this->resolvedStoragePath = '';
                    return '';
                }
            }
        }
        
        // Verify directory is writable
        if (!is_writable($basePath)) {
            error_log("MintyPHP Debugger: Storage directory not writable: $basePath");
            $this->resolvedStoragePath = '';
            return '';
        }
        
        $this->resolvedStoragePath = $basePath;
        return $basePath;
    }
}
```

### Permission Strategy

**Recommended: 0700 (rwx------)**
- Owner can read, write, execute (list directory)
- Group and others have no access
- Secure - debug data may contain sensitive information

**Fallback: 0777 (rwxrwxrwx)**
- Used only if 0700 fails (some environments restrict permission setting)
- Ensures maximum compatibility
- Acceptable for temp directory usage

### Error Handling

**Philosophy: Graceful Degradation**

```php
private function saveRequest(string $uuid, DebuggerRequest $request): void
{
    $storagePath = $this->getStoragePath();
    if ($storagePath === '') {
        // Directory creation failed - degrade gracefully
        // Data will not persist, but application continues
        error_log("MintyPHP Debugger: Cannot persist debug data (storage path unavailable)");
        return;
    }
    
    $filename = $storagePath . DIRECTORY_SEPARATOR . "$uuid.json";
    $json = json_encode($request, JSON_PRETTY_PRINT);
    
    // Atomic write: temp file + rename
    $tempFile = $filename . '.tmp.' . getmypid();
    if (@file_put_contents($tempFile, $json, LOCK_EX) === false) {
        error_log("MintyPHP Debugger: Failed to write debug data: $filename");
        @unlink($tempFile);
        return;
    }
    
    if (!@rename($tempFile, $filename)) {
        error_log("MintyPHP Debugger: Failed to rename debug data: $tempFile -> $filename");
        @unlink($tempFile);
        return;
    }
}
```

**Key Principles**:
1. Never throw exceptions (debugger must not crash application)
2. Log errors via `error_log()` for developer awareness
3. Return early on failure, don't attempt to continue
4. Clean up partial writes (temp files)
5. Use `@` suppression for file operations, check return values

---

## 3. Cleanup Policies

### Recommended Strategy: **Built-in History Limit + Manual Cleanup**

#### Built-in: Respect `$__history` Limit

When adding new request to history, automatically delete oldest files:

```php
private function addToHistory(string $sessionId, string $uuid): void
{
    $storagePath = $this->getStoragePath();
    if ($storagePath === '') {
        return;
    }
    
    $historyFile = $storagePath . DIRECTORY_SEPARATOR . "session-$sessionId.json";
    
    // Read existing history with file lock
    $history = $this->readHistory($sessionId);
    
    // Add new UUID at beginning
    array_unshift($history, $uuid);
    
    // Enforce history limit
    if (count($history) > $this->history) {
        $toDelete = array_slice($history, $this->history);
        $history = array_slice($history, 0, $this->history);
        
        // Delete old request files
        foreach ($toDelete as $oldUuid) {
            $oldFile = $storagePath . DIRECTORY_SEPARATOR . "$oldUuid.json";
            @unlink($oldFile);
        }
    }
    
    // Write updated history atomically
    $this->writeHistory($sessionId, $history);
}
```

#### Manual Cleanup: Utility Method

Provide static method for manual cleanup (can be called from cron, deployment scripts):

```php
/**
 * Clean up debug data older than specified age
 * 
 * @param int $maxAge Maximum age in seconds (default 7 days)
 * @return int Number of files deleted
 */
public static function cleanup(int $maxAge = 604800): int
{
    $storagePath = static::$__storagePath;
    if ($storagePath === '') {
        $storagePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mintyphp-debug';
    }
    
    if (!is_dir($storagePath)) {
        return 0;
    }
    
    $deleted = 0;
    $cutoff = time() - $maxAge;
    
    foreach (scandir($storagePath) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filepath = $storagePath . DIRECTORY_SEPARATOR . $file;
        if (is_file($filepath) && filemtime($filepath) < $cutoff) {
            if (@unlink($filepath)) {
                $deleted++;
            }
        }
    }
    
    return $deleted;
}
```

#### OS-level Cleanup

**Automatic** (no code required):
- Linux/macOS: `/tmp` is often cleared on reboot or by systemd/tmpwatch
- Windows: Temp directory is periodically cleaned by Disk Cleanup

This is actually a **feature** for debug data - ephemeral by nature.

#### No __destruct Cleanup

**DO NOT** clean up in `__destruct()`:
- May run after response is sent (timing issues)
- May not run at all if process is killed
- Cannot do proper file locking in destructor
- Wrong place for I/O operations

---

## 4. Cross-Platform Compatibility

### Path Handling

**Use `DIRECTORY_SEPARATOR` constant**:

```php
// ✅ Correct - works on all platforms
$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mintyphp-debug';
$file = $path . DIRECTORY_SEPARATOR . "$uuid.json";

// ❌ Wrong - hard-coded separator
$path = sys_get_temp_dir() . '/mintyphp-debug';
```

### Platform-Specific Paths

**sys_get_temp_dir() returns**:

| Platform | Typical Path |
|----------|--------------|
| Linux | `/tmp` |
| macOS | `/var/folders/{random}/T` |
| Windows | `C:\Users\{user}\AppData\Local\Temp` |

**All handled automatically by PHP.**

### File Operations

Use platform-agnostic PHP functions:
- `is_dir()`, `is_writable()`, `is_file()`
- `mkdir()`, `unlink()`, `rename()`
- `file_put_contents()`, `file_get_contents()`
- `scandir()`, `filemtime()`

Avoid shell commands (`rm`, `del`, etc.).

### Line Endings

Not relevant for JSON data (no line ending sensitivity).

---

## 5. Configuration Pattern

### Recommended Pattern: Static Property with Smart Default

```php
class Debugger
{
    /**
     * Storage directory path for debug data files
     * 
     * When empty (default), uses sys_get_temp_dir() + '/mintyphp-debug'
     * Set to custom path to override (e.g., '/var/www/app/.cache/debug')
     * 
     * Directory will be created automatically with 0700 permissions if needed.
     * If creation fails, debugger degrades gracefully without persisting data.
     */
    public static string $__storagePath = '';
    
    /**
     * Number of requests to keep in history per session
     */
    public static int $__history = 10;
    
    /**
     * Whether debugger is enabled
     */
    public static bool $__enabled = false;
}
```

### Usage Examples

**Default (no configuration needed)**:
```php
// In bootstrap/index.php
Debugger::$__enabled = true;
// Uses sys_get_temp_dir() . '/mintyphp-debug' automatically
```

**Custom path**:
```php
// In bootstrap/index.php
Debugger::$__enabled = true;
Debugger::$__storagePath = __DIR__ . '/../storage/debug';
// Uses project-relative path
```

**Environment-specific**:
```php
// In bootstrap/index.php
Debugger::$__enabled = ($_ENV['APP_DEBUG'] ?? false);
Debugger::$__storagePath = $_ENV['DEBUG_STORAGE_PATH'] ?? '';
// Configurable via environment variables
```

### Configuration Flow

```
1. User sets Debugger::$__storagePath (optional)
   ├─ If empty: use sys_get_temp_dir() + '/mintyphp-debug'
   └─ If set: use configured path

2. On first write operation:
   ├─ Resolve path (apply default if needed)
   ├─ Create directory if not exists (0700 permissions)
   ├─ Verify writable
   └─ Cache resolved path in instance

3. On write failure:
   ├─ Log error via error_log()
   ├─ Mark storage unavailable
   └─ Degrade gracefully (don't persist data)
```

### Validation

**DO**: Validate path is writable when creating directory
**DON'T**: Validate path format/existence at configuration time

Reasons:
- Configuration happens at bootstrap (before any I/O)
- Validation failures should be silent (graceful degradation)
- Path may be created by other processes between config and use

---

## 6. Implementation Checklist

### Phase 1: Basic Storage
- [ ] Add `Debugger::$__storagePath` static property
- [ ] Implement `getStoragePath()` with lazy directory creation
- [ ] Implement `saveRequest()` with atomic writes
- [ ] Implement `readHistory()` and `writeHistory()` with file locking
- [ ] Implement `addToHistory()` with history limit enforcement

### Phase 2: Error Handling
- [ ] Handle directory creation failures gracefully
- [ ] Handle write failures gracefully
- [ ] Add error_log() calls for debugging
- [ ] Add unit tests for failure scenarios

### Phase 3: Cleanup
- [ ] Implement automatic old-file deletion in `addToHistory()`
- [ ] Implement `Debugger::cleanup()` static method
- [ ] Document cleanup strategy in README

### Phase 4: Testing
- [ ] Test on Linux
- [ ] Test on macOS
- [ ] Test on Windows
- [ ] Test with custom paths
- [ ] Test concurrent writes
- [ ] Test permission failures
- [ ] Test disk full scenarios

---

## 7. Example Implementation Snippets

### Complete getStoragePath() Method

```php
/**
 * Get the resolved storage path, creating directory if needed
 * 
 * @return string Storage path, or empty string if unavailable
 */
private function getStoragePath(): string
{
    // Return cached path if already resolved
    if ($this->resolvedStoragePath !== null) {
        return $this->resolvedStoragePath;
    }
    
    // Determine base path
    $basePath = static::$__storagePath;
    if ($basePath === '') {
        // Default: use system temp directory
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mintyphp-debug';
    }
    
    // Normalize path separators (already using DIRECTORY_SEPARATOR, but good practice)
    $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    
    // Create directory if it doesn't exist
    if (!is_dir($basePath)) {
        // Try with restrictive permissions first
        $created = @mkdir($basePath, 0700, true);
        
        if (!$created) {
            // Fallback: try with permissive permissions
            $created = @mkdir($basePath, 0777, true);
        }
        
        if (!$created) {
            // Creation failed - log and return empty
            error_log("MintyPHP Debugger: Failed to create storage directory: $basePath");
            $this->resolvedStoragePath = '';
            return '';
        }
    }
    
    // Verify directory is writable
    if (!is_writable($basePath)) {
        error_log("MintyPHP Debugger: Storage directory not writable: $basePath");
        $this->resolvedStoragePath = '';
        return '';
    }
    
    // Cache and return
    $this->resolvedStoragePath = $basePath;
    return $basePath;
}
```

### Atomic File Write Helper

```php
/**
 * Write data to file atomically (write to temp, then rename)
 * 
 * @param string $filename Target filename
 * @param string $data Data to write
 * @return bool Success
 */
private function writeFileAtomic(string $filename, string $data): bool
{
    $tempFile = $filename . '.tmp.' . getmypid();
    
    // Write to temp file with exclusive lock
    $bytesWritten = @file_put_contents($tempFile, $data, LOCK_EX);
    if ($bytesWritten === false) {
        @unlink($tempFile);
        return false;
    }
    
    // Atomic rename
    if (!@rename($tempFile, $filename)) {
        @unlink($tempFile);
        return false;
    }
    
    return true;
}
```

### Cross-Platform Path Builder

```php
/**
 * Build a path with proper separators
 * 
 * @param string ...$parts Path components
 * @return string Complete path
 */
private function buildPath(string ...$parts): string
{
    return implode(DIRECTORY_SEPARATOR, $parts);
}

// Usage:
$requestFile = $this->buildPath($storagePath, "$uuid.json");
$historyFile = $this->buildPath($storagePath, "session-$sessionId.json");
```

---

## 8. Recommendations Summary

| Aspect | Recommendation | Rationale |
|--------|---------------|-----------|
| **Default Path** | `sys_get_temp_dir() + '/mintyphp-debug'` | Cross-platform, always writable, OS cleanup |
| **Configuration** | `Debugger::$__storagePath` static property | Consistent with MintyPHP patterns |
| **Directory Creation** | Lazy (on first write) | Avoids I/O during bootstrap |
| **Permissions** | 0700, fallback to 0777 | Secure but compatible |
| **Error Handling** | Graceful degradation + error_log | Never crash application |
| **Cleanup** | Built-in history limit + manual method | Automatic for recent data, manual for old |
| **Atomic Writes** | Temp file + rename | Prevents corruption |
| **File Locking** | LOCK_EX on writes, LOCK_SH on reads | Prevents race conditions |
| **Path Separators** | `DIRECTORY_SEPARATOR` constant | Cross-platform compatibility |

---

## 9. Security Considerations

1. **File Permissions**: Use 0700 (owner-only) to prevent unauthorized access to debug data
2. **Path Validation**: Don't validate user-supplied paths - just fail gracefully if unusable
3. **Sensitive Data**: Debug data may contain session info, queries with parameters - restrict access
4. **Symlink Attacks**: Not a concern (using `sys_get_temp_dir()` + predictable subdir)
5. **Path Traversal**: Not exposed to user input (configured via static property in code)

---

## 10. Future Enhancements (Out of Scope)

- Size-based cleanup (e.g., keep max 100MB of debug data)
- Compression of old debug data
- Remote storage backends (S3, Redis)
- Web UI for browsing debug history
- Integration with external logging systems
- Automatic cleanup scheduling

These can be addressed in future iterations if needed.

---

## Conclusion

The recommended approach provides:
- **Zero-configuration** default that works everywhere
- **Flexibility** to override when needed
- **Robustness** through graceful error handling
- **Security** through restricted permissions
- **Simplicity** through standard PHP functions

This strategy aligns with MintyPHP's philosophy of sensible defaults with configuration options, while ensuring the debugger never interferes with application functionality even when storage fails.
