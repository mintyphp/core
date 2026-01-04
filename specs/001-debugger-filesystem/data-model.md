# Data Model: Debugger Filesystem Storage

**Date**: 2025-12-01  
**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Research**: [research.md](./research.md)

This document defines the data entities and their filesystem representation for the debugger storage system.

---

## Overview

The debugger storage system consists of three primary entities:
1. **Debug Request** - Individual request debug data stored as JSON files
2. **Debug History** - Ordered list of request UUIDs per browser session
3. **Browser Session** - Cookie-based session identifier independent of PHP sessions

---

## Entity 1: Debug Request

**Purpose**: Stores comprehensive debugging information for a single HTTP request.

### Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `log` | `array<string>` | Debug log messages |
| `queries` | `array<DebuggerQuery>` | Database queries executed |
| `apiCalls` | `array<DebuggerApiCall>` | External API calls made |
| `session` | `DebuggerSessionStates` | Session state before/after |
| `cache` | `array<DebuggerCacheCall>` | Cache operations |
| `start` | `float` | Request start timestamp (microtime) |
| `status` | `int` | HTTP response status code |
| `user` | `string` | System user running PHP process |
| `type` | `string` | Request completion type (ok, abort, error) |
| `duration` | `float` | Request duration in seconds |
| `memory` | `int` | Peak memory usage in bytes |
| `classes` | `array<string>` | Loaded PHP class files |
| `route` | `DebuggerRoute` | Routing information |
| `redirect` | `string` | Redirect location if any |

### Nested Objects

**DebuggerQuery**:
- `duration` (float) - Query execution time
- `query` (string) - Original SQL query
- `equery` (string) - Query with parameters replaced for display
- `params` (array) - Bound parameters
- `result` (mixed) - Query result
- `explain` (mixed) - EXPLAIN output

**DebuggerApiCall**:
- `duration` (float) - API call duration
- `method` (string) - HTTP method (GET, POST, etc.)
- `url` (string) - API endpoint URL
- `data` (array) - Request data sent
- `options` (array) - cURL options
- `headers` (array) - Request headers
- `status` (int) - HTTP response status
- `timing` (array) - Detailed timing breakdown
- `result` (mixed) - Response data

**DebuggerCacheCall**:
- `duration` (float) - Cache operation duration
- `command` (string) - Cache command (get, set, delete, etc.)
- `arguments` (array) - Command arguments
- `result` (mixed) - Operation result

**DebuggerSessionStates**:
- `before` (string) - Serialized session data before request
- `after` (string) - Serialized session data after request

**DebuggerRoute**:
- `method` (string) - HTTP method
- `csrfOk` (bool) - CSRF token validation result
- `request` (string) - Request URI
- `url` (string) - Parsed URL
- `dir` (string) - Directory path
- `viewFile` (string) - View file path
- `actionFile` (string) - Action file path
- `templateFile` (string) - Template file path
- `urlParameters` (array) - URL path parameters
- `getParameters` (array) - Query string parameters
- `postParameters` (array) - POST body parameters

### Storage Format

**Filename Pattern**: `{uuid}.json` where `{uuid}` is RFC 4122 v4 UUID

**Location**: `{storage_path}/{session_id}/{uuid}.json`

**Example Path**: `/tmp/mintyphp-debug/xK9j_mP4nQ7wR2vY8sZ5tL3h6fC1bN0a/550e8400-e29b-41d4-a716-446655440000.json`

**File Format**: JSON with pretty printing (for human readability)

**Example Content**:
```json
{
    "log": [
        "Router: Matched route /api/users",
        "DB: Connected to database",
        "Auth: User authenticated"
    ],
    "queries": [
        {
            "duration": 0.0023,
            "query": "SELECT * FROM users WHERE id = ?",
            "equery": "SELECT * FROM users WHERE id = 123",
            "params": [123],
            "result": {"id": 123, "name": "John"},
            "explain": null
        }
    ],
    "apiCalls": [],
    "session": {
        "before": "array(2) { ... }",
        "after": "array(3) { ... }"
    },
    "cache": [],
    "start": 1701446400.1234,
    "status": 200,
    "user": "www-data",
    "type": "ok",
    "duration": 0.045,
    "memory": 2097152,
    "classes": [
        "/var/www/src/Core/Router.php",
        "/var/www/src/Core/DB.php"
    ],
    "route": {
        "method": "GET",
        "csrfOk": true,
        "request": "/api/users/123",
        "url": "/api/users/123",
        "dir": "/api/users",
        "viewFile": "",
        "actionFile": "/var/www/pages/api/users.php",
        "templateFile": "",
        "urlParameters": {"id": "123"},
        "getParameters": {},
        "postParameters": {}
    },
    "redirect": ""
}
```

### Lifecycle

1. **Creation**: Generated when `Debugger::end()` is called at request completion
2. **Access**: Read by `Debugger::toolbar()` to display debug information
3. **Deletion**: Removed when history limit exceeded (oldest requests deleted first)
4. **Retention**: Persists until manually deleted or system temp cleanup (OS reboot)

### Size Considerations

- **Typical Size**: 5-50 KB per request
- **Large Requests**: Up to 500 KB (many queries, large API responses)
- **History Limit**: Default 10 requests per session = ~50-500 KB per session
- **Storage Impact**: Minimal for development environment

---

## Entity 2: Debug History

**Purpose**: Maintains ordered list of request UUIDs for a browser session, enabling history navigation.

### Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| UUID List | `array<string>` | Ordered array of request UUIDs, most recent first |

### Storage Format

**Filename Pattern**: `history.json`

**Location**: `{storage_path}/{session_id}/history.json`

**Example Path**: `/tmp/mintyphp-debug/xK9j_mP4nQ7wR2vY8sZ5tL3h6fC1bN0a/history.json`

**File Format**: JSON array of UUID strings

**Example Content**:
```json
[
    "550e8400-e29b-41d4-a716-446655440000",
    "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
    "7c9e6679-7425-40de-944b-e07fc1f90ae7",
    "886313e1-3b8a-5372-9b90-0c9aee199e5d",
    "9b2d5b90-7e90-4a4d-8a8e-1d7c9f3e2b1a"
]
```

### Operations

**Read**: 
```php
$history = json_decode(file_get_contents($historyPath), true) ?: [];
```

**Write** (with locking):
```php
$fp = fopen($historyPath, 'c+');
flock($fp, LOCK_EX);
// Read, modify, write
array_unshift($history, $newUuid);
if (count($history) > $limit) {
    $history = array_slice($history, 0, $limit);
}
rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($history, JSON_PRETTY_PRINT));
flock($fp, LOCK_UN);
fclose($fp);
```

### Lifecycle

1. **Creation**: Generated on first debug request in a browser session
2. **Updates**: Modified on each subsequent request (prepend new UUID)
3. **Limit Enforcement**: When limit exceeded, oldest UUIDs removed and corresponding request files deleted
4. **Persistence**: Survives browser close/reopen (until cookie expires or manual deletion)

### Constraints

- **Max Length**: Determined by `Debugger::$__history` (default: 10)
- **UUID Format**: RFC 4122 v4 format (36 characters with hyphens)
- **Ordering**: Most recent request first (array index 0)

---

## Entity 3: Browser Session Identifier

**Purpose**: Unique identifier for browser session, independent of PHP session system.

### Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| Identifier | `string` | 32-character URL-safe base64 string |

### Storage Format

**Storage Mechanism**: HTTP Cookie

**Cookie Configuration**:
- **Name**: `minty_debug_session`
- **Value**: 32-character string (24 bytes random → base64 URL-safe)
- **Expires**: `0` (session cookie, cleared on browser close)
- **Path**: `/`
- **Domain**: `''` (current domain)
- **Secure**: `false` (development uses HTTP)
- **HttpOnly**: `true` (prevents JavaScript access, XSS protection)
- **SameSite**: `Lax` (CSRF protection)

**Format**: `/^[A-Za-z0-9_-]{32}$/`

**Example Value**: `xK9j_mP4nQ7wR2vY8sZ5tL3h6fC1bN0a`

### Generation

```php
// Generate 24 bytes (192 bits) of random data
$bytes = random_bytes(24);

// Convert to URL-safe base64 (32 characters)
$identifier = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
```

### Lifecycle

1. **Creation**: Generated on first request with debugger enabled if cookie not present
2. **Validation**: Checked against regex pattern on each request
3. **Persistence**: Survives page navigation, persists until browser close
4. **Expiration**: Session cookie (cleared when browser closes)
5. **Renewal**: New identifier generated if cookie invalid or missing

### Security

- **Entropy**: 192 bits (collision probability < 10^-38)
- **XSS Protection**: HttpOnly prevents JavaScript access
- **CSRF Protection**: SameSite=Lax prevents cross-site requests
- **No Encryption**: Acceptable for development-only feature
- **No Sensitive Data**: Identifier is just a random tracking token

---

## Storage Directory Structure

### Complete Hierarchy

```
{storage_path}/                              # Configurable via Debugger::$__storagePath
└── debugger/                                # Root debugger storage directory
    ├── session-{id1}.json                   # History file for session 1
    ├── {id1}/                               # Request directory for session 1
    │   ├── {uuid1}.json                     # Request 1 data
    │   ├── {uuid2}.json                     # Request 2 data
    │   ├── {uuid3}.json                     # Request 3 data
    │   └── ...                              # Up to history limit requests
    ├── session-{id2}.json                   # History file for session 2
    ├── {id2}/                               # Request directory for session 2
    │   ├── {uuid4}.json                     # Request 4 data
    │   └── {uuid5}.json                     # Request 5 data
    └── ...                                  # Additional sessions
```

### Example (Real Paths)

```
/tmp/mintyphp-debug/
├── session-xK9j_mP4nQ7wR2vY8sZ5tL3h6fC1bN0a.json
├── xK9j_mP4nQ7wR2vY8sZ5tL3h6fC1bN0a/
│   ├── 550e8400-e29b-41d4-a716-446655440000.json
│   ├── 6ba7b810-9dad-11d1-80b4-00c04fd430c8.json
│   └── 7c9e6679-7425-40de-944b-e07fc1f90ae7.json
├── session-yL2k_nQ5oR8xS3wZ9tA6uM4i7gD2cO1b.json
└── yL2k_nQ5oR8xS3wZ9tA6uM4i7gD2cO1b/
    ├── 886313e1-3b8a-5372-9b90-0c9aee199e5d.json
    └── 9b2d5b90-7e90-4a4d-8a8e-1d7c9f3e2b1a.json
```

### Directory Permissions

- **Root**: `{storage_path}/` - 0755 or 0777
- **Session Directories**: `{storage_path}/{id}/` - 0755 or 0777
- **Files**: Request JSON files - 0644 (readable by owner and group)

### Cleanup Strategy

**Automatic** (built-in):
- When history limit exceeded, delete oldest request files
- Controlled by `Debugger::$__history` setting

**Manual** (optional):
- Provide `Debugger::cleanup(int $maxAgeDays)` static method
- Delete sessions older than specified age
- Called by developers/cron jobs

**OS-Level** (automatic):
- System temp directories cleared on reboot
- No manual intervention needed for default path

---

## Data Flow

### Request Lifecycle

```
1. Request Start
   ├─ Debugger::__construct()
   │  ├─ Check for debug session cookie
   │  ├─ Generate identifier if missing
   │  └─ Set cookie (if headers not sent)
   │
2. Request Execution
   ├─ Log debug messages
   ├─ Track database queries
   ├─ Track API calls
   └─ Track cache operations
   │
3. Request End
   └─ Debugger::end()
      ├─ Generate UUID for this request
      ├─ Serialize DebuggerRequest to JSON
      ├─ Write request file atomically
      │  └─ {storage_path}/{session_id}/{uuid}.json
      ├─ Update history file with locking
      │  ├─ Read current history
      │  ├─ Prepend new UUID
      │  ├─ Enforce history limit
      │  └─ Delete old request files if needed
      └─ Write updated history
         └─ {storage_path}/session-{session_id}.json
```

### Debugger Toolbar

```
1. Toolbar Request
   └─ Debugger::toolbar()
      ├─ Get browser session ID from cookie
      ├─ Read history file
      │  └─ {storage_path}/session-{session_id}.json
      ├─ Load current request UUID (first in history)
      ├─ Read request file
      │  └─ {storage_path}/{session_id}/{uuid}.json
      ├─ Deserialize JSON to DebuggerRequest
      └─ Render HTML toolbar with debug data
```

---

## Validation Rules

### UUID Validation

```php
// RFC 4122 v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)
```

### Session ID Validation

```php
// URL-safe base64: 32 characters, alphanumeric + - _
preg_match('/^[A-Za-z0-9_-]{32}$/', $sessionId)
```

### History Array Validation

```php
// Must be array
is_array($history)

// All elements must be valid UUIDs
array_all($history, fn($uuid) => preg_match('/^[0-9a-f]{8}-...$/i', $uuid))

// Max length enforced
count($history) <= $maxHistory
```

---

## Migration from $_SESSION

### Old Implementation

```php
// Stored in PHP session
$_SESSION['debugger'][] = $this->request;

// Limited by session size/lifetime
// Lost on session expiration
// Pollutes session inspection
```

### New Implementation

```php
// Stored on filesystem
$uuid = $this->generateUUID();
$requestPath = "{storagePath}/{sessionId}/{uuid}.json";
file_put_contents($requestPath, json_encode($this->request));

// Independent of session system
// Persists across browser sessions
// No session pollution
// Configurable retention
```

### Breaking Changes

**Applications that read `$_SESSION['debugger']`**:
- Must update to read from filesystem
- Use `Debugger::getStoragePath()` and parse JSON files
- History file lists available request UUIDs

**Toolbar Implementation**:
- Internal change only (no API changes)
- Toolbar HTML/JavaScript unchanged
- Reads from filesystem instead of session

---

## Performance Characteristics

### Storage Operations

| Operation | Typical Duration | Notes |
|-----------|-----------------|-------|
| Generate UUID | ~15μs | Cryptographically secure random |
| JSON encode request | ~100-500μs | Depends on data size |
| Atomic file write | ~500μs-1ms | Write-then-rename pattern |
| History update (locked) | ~1-2ms | Read, modify, write with flock |
| **Total per request** | **~2-4ms** | Acceptable for debug overhead |

### Concurrent Performance

| Scenario | Throughput | Notes |
|----------|-----------|-------|
| Single session | 500-1000 req/s | Limited by flock on history file |
| Multiple sessions | Unlimited | No contention (separate files) |
| 100+ concurrent to same session | ~100 req/s | Lock contention becomes bottleneck |

### Storage Space

| Metric | Typical Value | Notes |
|--------|--------------|-------|
| Request file size | 5-50 KB | Depends on queries/API calls |
| History file size | ~500 bytes | 10 UUIDs × 36 chars + JSON |
| Per session (10 requests) | 50-500 KB | Default history limit |
| 100 active sessions | 5-50 MB | Typical development scenario |

---

## Error Handling

### File Write Failures

**Scenario**: Disk full, permission denied, read-only filesystem

**Behavior**:
- `atomicWrite()` returns `false`
- Error logged via `error_log()`
- Request continues normally
- Debug data not saved (acceptable loss)

### History Update Failures

**Scenario**: Lock timeout, file corruption, JSON decode error

**Behavior**:
- `updateHistory()` returns `false`
- Request file already written (still accessible)
- History not updated (file exists but not in navigation)
- Error logged for developer awareness

### Invalid Cookie

**Scenario**: Malformed session ID cookie, expired cookie

**Behavior**:
- Cookie validation fails (regex check)
- New identifier generated
- New cookie set
- Fresh history started

### Missing Directory

**Scenario**: Storage directory doesn't exist or deleted

**Behavior**:
- `ensureDirectoryExists()` creates recursively
- Falls back to more permissive mode if needed
- Returns `false` if creation impossible
- Error logged, debug data not saved

---

## Testing Strategy

### Unit Tests

- UUID generation (format, uniqueness)
- Session ID generation (format, uniqueness)
- Atomic file write (success, failure, cleanup)
- History update (add, limit enforcement, locking)
- Cookie validation (valid/invalid formats)
- Path construction (correct hierarchy)

### Integration Tests

- Concurrent requests to same session
- History limit enforcement with file deletion
- Filesystem failure scenarios (read-only, full disk)
- Cookie lifecycle (creation, validation, renewal)
- Cross-platform path handling

### Test Data

Use `DebuggerRequest` objects with:
- Minimal data (empty arrays)
- Typical data (1-5 queries)
- Large data (100+ queries, large API responses)
- Invalid data (non-serializable objects)

---

## Summary

The filesystem-based debugger storage system provides:
- ✅ Persistent debug data across browser sessions
- ✅ No PHP session dependency
- ✅ No session pollution
- ✅ Configurable storage location
- ✅ Atomic operations (no data corruption)
- ✅ Concurrent request safety
- ✅ Graceful error handling
- ✅ Human-readable JSON format
- ✅ Minimal performance overhead (~2-4ms per request)
- ✅ Cross-platform compatibility

All entities and operations align with MintyPHP's constitution principles: simplicity, security, explicit behavior, native PHP patterns, and comprehensive testing.
